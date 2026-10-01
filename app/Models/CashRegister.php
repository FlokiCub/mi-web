<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CashRegister extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'cash_registers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'regional_warehouse_id',
        'code',
        'name',
        'status',
        'current_cash_shift_id',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function regionalWarehouse(): BelongsTo
    {
        return $this->belongsTo(RegionalWarehouse::class, 'regional_warehouse_id');
    }

    public function currentShift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'current_cash_shift_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(CashShift::class, 'cash_register_id')->latest('opened_at');
    }

    public function payments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, CashShift::class, 'cash_register_id', 'cash_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open' && !empty($this->current_cash_shift_id);
    }

    public function isClosed(): bool
    {
        return !$this->isOpen();
    }
}
