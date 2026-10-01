<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentTrackingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tracking_pin' => $this->tracking_pin,
            'vin_serial' => $this->vin_serial,
            'equipment_type' => $this->equipment_type,
            'business_type' => $this->business_type,
            'brand' => $this->brand,
            'model' => $this->model,
            'color' => $this->color,
            'current_status' => [
                'code' => $this->current_status->value ?? $this->current_status,
                'label' => method_exists($this->current_status, 'label') ? $this->current_status->label() : $this->current_status,
                'color' => method_exists($this->current_status, 'color') ? $this->current_status->color() : 'primary',
            ],
            'customs_status' => $this->customs_status,
            'duty_paid_abroad' => (bool)$this->duty_paid_abroad,
            'home_delivery_requested' => (bool)$this->home_delivery_requested,
            'declared_value_usd' => (float)$this->declared_value_usd,
            'duty_amount_usd' => (float)$this->duty_amount_usd,
            'shipping_fee_usd' => (float)$this->shipping_fee_usd,
            'technical_specs' => $this->technical_specs ?? [],
            'location_note' => $this->current_location_note,
            'warehouse' => $this->currentWarehouse ? [
                'name' => $this->currentWarehouse->name,
                'province' => $this->currentWarehouse->province,
            ] : null,
            'timeline' => $this->statusHistories->map(function ($history) {
                return [
                    'status' => $history->to_status,
                    'location' => $history->location,
                    'notes' => $history->notes,
                    'date' => $history->recorded_at ? $history->recorded_at->format('d/m/Y H:i') : null,
                ];
            }),
            'created_at' => $this->created_at ? $this->created_at->format('d/m/Y') : null,
        ];
    }
}
