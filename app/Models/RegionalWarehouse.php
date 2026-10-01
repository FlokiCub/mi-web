<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegionalWarehouse extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'regional_warehouses';

    protected $fillable = [
        'code',
        'name',
        'province',
        'address',
        'contact_phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class, 'current_warehouse_id');
    }
}
