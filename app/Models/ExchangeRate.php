<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'exchange_rates';

    protected $fillable = [
        'currency_from',
        'currency_to',
        'rate',
        'is_active',
        'set_by_user_id',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function setByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }
}
