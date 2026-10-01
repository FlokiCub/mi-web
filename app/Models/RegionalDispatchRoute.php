<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RegionalDispatchRoute extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'regional_dispatch_routes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'route_code',
        'origin_warehouse_id',
        'destination_warehouse_id',
        'driver_name',
        'driver_phone',
        'truck_license_plate',
        'status',
        'dispatched_at',
        'arrived_at',
        'notes',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'arrived_at' => 'datetime',
        ];
    }

    public function originWarehouse(): BelongsTo
    {
        return $this->belongsTo(RegionalWarehouse::class, 'origin_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(RegionalWarehouse::class, 'destination_warehouse_id');
    }

    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(
            Equipment::class,
            'dispatch_route_equipments',
            'dispatch_route_id',
            'equipment_id'
        )->withPivot('reception_status', 'received_at')->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // ==========================================
    // Scopes y Helpers de Negocio
    // ==========================================

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', ['arrived_destination', 'completed']);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft' || $this->status === null;
    }

    public function isInTransit(): bool
    {
        return $this->status === 'in_transit';
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['arrived_destination', 'completed'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'in_transit' => 'En Tránsito Provincial',
            'arrived_destination', 'completed' => 'Recepción Confirmada en Destino',
            default => 'Borrador / En Carga',
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'in_transit' => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
            'arrived_destination', 'completed' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
            default => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
        };
    }

    public function equipmentCount(): int
    {
        return $this->equipments()->count();
    }

    public function totalDeclaredValue(): float
    {
        return (float) $this->equipments()->sum('declared_value_usd');
    }
}
