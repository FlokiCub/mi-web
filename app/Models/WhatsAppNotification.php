<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppNotification extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'whatsapp_notifications';

    protected $fillable = [
        'equipment_id',
        'recipient_phone',
        'recipient_name',
        'event_trigger',
        'message_body',
        'status',
        'gateway_message_id',
        'error_details',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class, 'equipment_id');
    }
}
