<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\User;
use App\Services\DispatchReceptionService;
use App\Services\PvpAssemblyService;
use App\Services\WhatsAppNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    private DispatchReceptionService $receptionService;
    private PvpAssemblyService $pvpService;
    private WhatsAppNotificationService $waService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receptionService = app(DispatchReceptionService::class);
        $this->pvpService = app(PvpAssemblyService::class);
        $this->waService = app(WhatsAppNotificationService::class);
    }

    public function test_automatic_whatsapp_sent_upon_cuba_port_arrival(): void
    {
        $operator = User::factory()->create(['role' => UserRole::ADUANA]);

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-WA01',
            'vin_serial' => 'VIN-WA-TEST-01',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro Lithium',
            'current_status' => EquipmentStatus::IN_TRANSIT_SOLVE_CARGO,
            'client_data' => [
                'name' => 'Alejandro Morales',
                'phone' => '+53 5 222 3333',
            ],
        ]);

        $this->receptionService->receiveEquipmentAtCubaDispatch(
            barcodeOrPin: 'BK-WA01',
            manifestData: ['duty_paid_abroad' => false],
            receptionLocation: 'Puerto del Mariel',
            operator: $operator
        );

        // Validar que se creó el registro de auditoría de WhatsApp
        $this->assertDatabaseHas('whatsapp_notifications', [
            'equipment_id' => $equipment->id,
            'recipient_phone' => '+53 5 222 3333',
            'recipient_name' => 'Alejandro Morales',
            'event_trigger' => EquipmentStatus::READY_FOR_DISPATCH->value,
            'status' => 'sent',
        ]);
    }

    public function test_whatsapp_message_contains_pin_and_tracking_link(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'BK-LINK99',
            'vin_serial' => 'VIN-LINK-99',
            'equipment_type' => EquipmentType::SOLAR_SYSTEM,
            'business_type' => BusinessType::B2B,
            'brand' => 'Growatt',
            'model' => 'Kit 5kW',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'client_data' => [
                'name' => 'Ing. Carlos Ramos',
                'phone' => '+53 5 999 8888',
            ],
        ]);

        $notification = $this->waService->sendStatusUpdateNotification($equipment);

        $this->assertEquals('sent', $notification->status);
        $this->assertStringContainsString('BK-LINK99', $notification->message_body);
        $this->assertStringContainsString('/tracking/BK-LINK99', $notification->message_body);
        $this->assertStringContainsString('Growatt Kit 5kW', $notification->message_body);
    }
}
