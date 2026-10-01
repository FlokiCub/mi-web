<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\User;
use App\Services\DispatchReceptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchReceptionTest extends TestCase
{
    use RefreshDatabase;

    private DispatchReceptionService $receptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->receptionService = app(DispatchReceptionService::class);
    }

    public function test_scan_reception_updates_status_to_port_arrival(): void
    {
        $operator = User::factory()->create(['role' => 'aduana']);

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-SCAN01',
            'vin_serial' => 'VIN-SCAN-TEST-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'declared_value_usd' => 2000.00,
            'current_status' => EquipmentStatus::IN_TRANSIT_SOLVE_CARGO,
            'duty_paid_abroad' => false,
        ]);

        $result = $this->receptionService->receiveEquipmentAtCubaDispatch(
            barcodeOrPin: 'BK-SCAN01',
            manifestData: ['duty_paid_abroad' => false],
            receptionLocation: 'Puerto del Mariel - Bahía 4',
            notes: 'Descargado de buque e inspeccionado.',
            operator: $operator
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $result['equipment']->current_status);
        $this->assertEquals('Puerto del Mariel - Bahía 4', $result['equipment']->current_location_note);
        
        // Verifica que al no estar prepagado, adeuda $200 USD en Cuba
        $this->assertEquals(200.00, $result['finance']['duty_amount_usd']);
        $this->assertFalse($result['finance']['is_totally_prepaid']);

        // Verifica historial inmutable
        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $equipment->id,
            'to_status' => EquipmentStatus::READY_FOR_DISPATCH->value,
            'changed_by_user_id' => $operator->id,
        ]);
    }

    public function test_scan_reception_with_prepaid_customs_sets_zero_duty_in_cuba(): void
    {
        $operator = User::factory()->create(['role' => 'aduana']);

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-FREE01',
            'vin_serial' => 'VIN-FREE-TEST-999',
            'equipment_type' => EquipmentType::SOLAR_SYSTEM,
            'business_type' => BusinessType::B2B,
            'brand' => 'Growatt',
            'model' => 'Kit 5kW',
            'declared_value_usd' => 4500.00,
            'current_status' => EquipmentStatus::IN_TRANSIT_SOLVE_CARGO,
            'duty_paid_abroad' => false,
        ]);

        // Manifiesto indica que los aranceles y comisiones ya fueron pagados en el exterior
        $result = $this->receptionService->receiveEquipmentAtCubaDispatch(
            barcodeOrPin: 'BK-FREE01',
            manifestData: ['duty_paid_abroad' => true],
            receptionLocation: 'Terminal Central Mariel',
            notes: 'Arancel cubierto en origen.',
            operator: $operator
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $result['equipment']->current_status);
        $this->assertTrue($result['equipment']->duty_paid_abroad);
        
        // Total a cobrar en Cuba debe ser exactamente $0.00
        $this->assertEquals(0.00, $result['finance']['total_to_collect_usd']);
        $this->assertEquals(0.00, $result['finance']['total_to_collect_cup']);
        $this->assertTrue($result['finance']['is_totally_prepaid']);
        $this->assertEquals('duty_exempt_prepaid', $result['equipment']->customs_status);
    }
}
