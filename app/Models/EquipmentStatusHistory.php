<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentStatusHistory extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $table = 'equipment_status_histories';

    protected $fillable = [
        'equipment_id',
        'from_status',
        'to_status',
        'location',
        'notes',
        'snapshot',
        'changed_by_user_id',
        'recorded_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
