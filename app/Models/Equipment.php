<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'equipments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tracking_pin',
        'vin_serial',
        'solve_cargo_tracking_id',
        'equipment_type',
        'business_type',
        'brand',
        'model',
        'color',
        'current_status',
        'customs_status',
        'declared_value_usd',
        'duty_amount_usd',
        'shipping_fee_usd',
        'home_delivery_requested',
        'duty_paid_abroad',
        'delivery_paid_abroad',
        'customs_entry_at',
        'customs_cleared_at',
        'pvp_certified_at',
        'delivered_at',
        'financial_status',
        'is_fully_paid',
        'settled_at',
        'settled_by_user_id',
        'client_data',
        'technical_specs',
        'current_warehouse_id',
        'current_location_note',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'equipment_type' => EquipmentType::class,
            'business_type' => BusinessType::class,
            'current_status' => EquipmentStatus::class,
            'declared_value_usd' => 'decimal:2',
            'duty_amount_usd' => 'decimal:2',
            'shipping_fee_usd' => 'decimal:2',
            'home_delivery_requested' => 'boolean',
            'duty_paid_abroad' => 'boolean',
            'delivery_paid_abroad' => 'boolean',
            'customs_entry_at' => 'datetime',
            'customs_cleared_at' => 'datetime',
            'pvp_certified_at' => 'datetime',
            'delivered_at' => 'datetime',
            'is_fully_paid' => 'boolean',
            'settled_at' => 'datetime',
            'client_data' => 'array',
            'technical_specs' => 'array',
        ];
    }

    // ==========================================
    // Relaciones Eloquent
    // ==========================================

    public function statusHistories(): HasMany
    {
        return $this->hasMany(EquipmentStatusHistory::class, 'equipment_id')->orderBy('recorded_at', 'desc');
    }

    public function pvpInspections(): HasMany
    {
        return $this->hasMany(PvpInspection::class, 'equipment_id')->latest('inspected_at');
    }

    public function dispatchRoutes(): BelongsToMany
    {
        return $this->belongsToMany(
            RegionalDispatchRoute::class,
            'dispatch_route_equipments',
            'equipment_id',
            'dispatch_route_id'
        )->withPivot('reception_status', 'received_at')->withTimestamps();
    }

    public function whatsAppNotifications(): HasMany
    {
        return $this->hasMany(WhatsAppNotification::class, 'equipment_id')->latest('sent_at');
    }

    public function currentWarehouse(): BelongsTo
    {
        return $this->belongsTo(RegionalWarehouse::class, 'current_warehouse_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ==========================================
    // Query Scopes de Negocio
    // ==========================================

    public function scopeInCustoms(Builder $query): Builder
    {
        return $query->whereIn('current_status', [
            EquipmentStatus::PORT_ARRIVAL,
            EquipmentStatus::CUSTOMS_INSPECTION,
        ]);
    }

    /**
     * Equipos en aduana que superan el umbral preventivo (48h).
     */
    public function scopeCustomsDelayed(Builder $query, int $hours = 48): Builder
    {
        $threshold = Carbon::now()->subHours($hours);

        return $query->inCustoms()
            ->where(function (Builder $q) use ($threshold) {
                $q->where('customs_entry_at', '<=', $threshold)
                  ->orWhere(function (Builder $sub) use ($threshold) {
                      $sub->whereNull('customs_entry_at')
                          ->where('updated_at', '<=', $threshold);
                  });
            });
    }

    /**
     * Equipos en aduana que superan el umbral crítico de retención (72h).
     */
    public function scopeCustomsCritical(Builder $query, int $hours = 72): Builder
    {
        return $this->scopeCustomsDelayed($query, $hours);
    }

    /**
     * Calcula las horas transcurridas en aduana/puerto desde el arribo.
     */
    public function getCustomsStayHours(): int
    {
        $start = $this->customs_entry_at ?? $this->created_at ?? Carbon::now();
        $end = $this->customs_cleared_at ?? Carbon::now();

        return (int) $start->diffInHours($end);
    }

    /**
     * Obtiene el color de semáforo de permanencia aduanal.
     * @return 'green'|'yellow'|'red'
     */
    public function getCustomsSemaphore(): string
    {
        if ($this->customs_cleared_at !== null) {
            return 'green';
        }

        $hours = $this->getCustomsStayHours();

        if ($hours >= 72) {
            return 'red';
        }

        if ($hours >= 48) {
            return 'yellow';
        }

        return 'green';
    }

    /**
     * Etiqueta legible del semáforo aduanal.
     */
    public function getCustomsSemaphoreLabel(): string
    {
        return match ($this->getCustomsSemaphore()) {
            'red' => '🔴 Crítico (>72h)',
            'yellow' => '🟡 Preventivo (48h-72h)',
            'green' => '🟢 Normal (<48h)',
        };
    }

    public function scopeInWarehouse(Builder $query, string $warehouseId): Builder
    {
        return $query->where('current_warehouse_id', $warehouseId)
            ->where('current_status', EquipmentStatus::IN_REGIONAL_WAREHOUSE);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'equipment_id')->latest('paid_at');
    }

    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by_user_id');
    }

    // ==========================================
    // Lógica Financiera y Reglas de Negocio
    // ==========================================

    /**
     * Determina si el equipo llegó a Cuba 100% pagado desde el exterior.
     */
    public function isTotallyPrepaidAbroad(): bool
    {
        if (!$this->duty_paid_abroad) {
            return false;
        }

        if ($this->home_delivery_requested && !$this->delivery_paid_abroad) {
            return false;
        }

        return true;
    }

    /**
     * Determina si el equipo ya está completamente liquidado y exento de cobro en Cuba.
     */
    public function isFullySettled(): bool
    {
        return $this->is_fully_paid || $this->isTotallyPrepaidAbroad();
    }

    /**
     * Calcula el saldo neto pendiente de cobro en USD.
     */
    public function getOutstandingBalanceUsd(): float
    {
        if ($this->isTotallyPrepaidAbroad() || $this->is_fully_paid) {
            return 0.00;
        }

        $duty = $this->duty_paid_abroad ? 0.00 : (float) $this->duty_amount_usd;
        $delivery = ($this->home_delivery_requested && !$this->delivery_paid_abroad) 
            ? (float) $this->shipping_fee_usd 
            : 0.00;

        $totalDue = round($duty + $delivery, 2);
        $totalPaid = (float) $this->payments()->where('status', 'completed')->sum('equivalent_total_usd');

        return max(0.00, round($totalDue - $totalPaid, 2));
    }

    /**
     * Regla de Negocio: Solo se puede entregar si está 100% pagado.
     */
    public function canBeDelivered(): bool
    {
        return $this->isFullySettled() || $this->getOutstandingBalanceUsd() <= 0.00;
    }

    // ==========================================
    // Scopes Financieros
    // ==========================================

    public function scopeFullyPaid(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('is_fully_paid', true)
              ->orWhere(function (Builder $sub) {
                  $sub->where('duty_paid_abroad', true)
                      ->where(function (Builder $deliv) {
                          $deliv->where('home_delivery_requested', false)
                                ->orWhere('delivery_paid_abroad', true);
                      });
              });
        });
    }

    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('is_fully_paid', false)
            ->where(function (Builder $q) {
                $q->where('duty_paid_abroad', false)
                  ->orWhere(function (Builder $sub) {
                      $sub->where('home_delivery_requested', true)
                          ->where('delivery_paid_abroad', false);
                  });
            });
    }

    public function scopeReadyForDispatch(Builder $query): Builder
    {
        return $query->where('current_status', EquipmentStatus::READY_FOR_DISPATCH);
    }
}
