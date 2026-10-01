<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'payments';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'receipt_number',
        'equipment_id',
        'cash_shift_id',
        'cashier_user_id',
        'concept',
        'amount_due_usd',
        'duty_amount_usd',
        'shipping_fee_usd',
        'amount_paid_usd',
        'amount_paid_cup',
        'exchange_rate_applied',
        'equivalent_total_usd',
        'payment_method',
        'status',
        'client_name',
        'client_id_card',
        'client_phone',
        'transaction_reference',
        'notes',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_due_usd' => 'decimal:2',
            'duty_amount_usd' => 'decimal:2',
            'shipping_fee_usd' => 'decimal:2',
            'amount_paid_usd' => 'decimal:2',
            'amount_paid_cup' => 'decimal:2',
            'exchange_rate_applied' => 'decimal:4',
            'equivalent_total_usd' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function cashShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
