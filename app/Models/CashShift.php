<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashShift extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cash_shifts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'cash_register_id',
        'cashier_user_id',
        'opened_at',
        'closed_at',
        'opening_balance_usd',
        'total_collected_usd',
        'expected_balance_usd',
        'closing_balance_usd',
        'difference_usd',
        'opening_balance_cup',
        'total_collected_cup',
        'expected_balance_cup',
        'closing_balance_cup',
        'difference_cup',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance_usd' => 'decimal:2',
            'total_collected_usd' => 'decimal:2',
            'expected_balance_usd' => 'decimal:2',
            'closing_balance_usd' => 'decimal:2',
            'difference_usd' => 'decimal:2',
            'opening_balance_cup' => 'decimal:2',
            'total_collected_cup' => 'decimal:2',
            'expected_balance_cup' => 'decimal:2',
            'closing_balance_cup' => 'decimal:2',
            'difference_cup' => 'decimal:2',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'cash_shift_id')->latest('paid_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', 'closed');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
