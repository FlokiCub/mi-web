<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvpInspection extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'pvp_inspections';

    protected $fillable = [
        'equipment_id',
        'battery_voltage_verified',
        'electrical_system_tested',
        'accessories_installed',
        'cosmetic_inspection_passed',
        'result_status',
        'battery_tested_voltage',
        'bom_checklist_details',
        'technician_notes',
        'technician_user_id',
        'inspected_at',
    ];

    protected $casts = [
        'battery_voltage_verified' => 'boolean',
        'electrical_system_tested' => 'boolean',
        'accessories_installed' => 'boolean',
        'cosmetic_inspection_passed' => 'boolean',
        'bom_checklist_details' => 'array',
        'inspected_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }
}
