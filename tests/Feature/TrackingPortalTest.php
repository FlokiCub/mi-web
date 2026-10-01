<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_search_page_loads(): void
    {
        $response = $this->get('/tracking');
        $response->assertStatus(200);
        $response->assertSee('BLANKISOL');
        $response->assertSee('Torre de Control SCGI');
    }

    public function test_can_search_equipment_by_pin_and_redirect(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'BK-998877',
            'vin_serial' => 'VIN-TEST-12345',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'current_status' => EquipmentStatus::IN_PVP_ASSEMBLY,
        ]);

        $response = $this->post('/tracking', ['pin' => 'BK-998877']);
        $response->assertRedirect('/tracking/BK-998877');

        $detailResponse = $this->get('/tracking/BK-998877');
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('BK-998877');
        $detailResponse->assertSee('Yadea G5 Pro');
    }

    public function test_tracking_api_endpoint_returns_json_resource(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'BK-JSON01',
            'vin_serial' => 'VIN-JSON-TEST',
            'equipment_type' => EquipmentType::SOLAR_SYSTEM,
            'business_type' => BusinessType::B2B,
            'brand' => 'Growatt',
            'model' => 'SPF 5000',
            'current_status' => EquipmentStatus::REGIONAL_TRANSIT,
        ]);

        $response = $this->getJson('/api/tracking/BK-JSON01');
        $response->assertStatus(200);
        $response->assertJsonPath('data.tracking_pin', 'BK-JSON01');
        $response->assertJsonPath('data.brand', 'Growatt');
    }
}
