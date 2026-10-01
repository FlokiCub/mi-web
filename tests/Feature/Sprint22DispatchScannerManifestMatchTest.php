<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\User;
use App\Services\DispatchReceptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint22DispatchScannerManifestMatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $directorUser;
    protected User $despachoUser;
    protected User $aduanaUser;
    protected User $cajeroUser;
    protected DispatchReceptionService $receptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->directorUser = User::where('role', UserRole::DIRECTOR->value)->first();
        $this->despachoUser = User::where('role', UserRole::DESPACHO_PUERTO->value)->first();
        $this->aduanaUser = User::where('role', UserRole::ADUANA->value)->first();
        $this->cajeroUser = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();

        $this->receptionService = app(DispatchReceptionService::class);
    }

    public function test_access_control_to_dispatch_scanner(): void
    {
        // Operador de Despacho puede acceder
        $response = $this->actingAs($this->despachoUser)->get(route('dispatch.scanner'));
        $response->assertOk();
        $response->assertViewIs('dispatch.scanner');

        // Director puede acceder
        $response = $this->actingAs($this->directorUser)->get(route('dispatch.scanner'));
        $response->assertOk();

        // Cajero no tiene permiso para el escáner de puerto (403)
        $response = $this->actingAs($this->cajeroUser)->get(route('dispatch.scanner'));
        $response->assertForbidden();
    }

    public function test_manifest_lookup_returns_accurate_comparison_data(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'DSP-LOOKUP1',
            'vin_serial' => 'VIN-MAN-998811',
            'solve_cargo_tracking_id' => 'SC-MARIEL-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Mishozuki',
            'model' => 'Eagle Pro 72V',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'declared_value_usd' => 1200.00,
            'duty_paid_abroad' => true,
            'delivery_paid_abroad' => true,
            'home_delivery_requested' => true,
            'client_data' => [
                'name' => 'Carlos Valdés',
                'id_number' => '85010212345',
                'phone' => '+5352009988',
                'province' => 'Matanzas',
            ],
        ]);

        $lookup = $this->receptionService->lookupManifestForScan('DSP-LOOKUP1');

        $this->assertEquals('DSP-LOOKUP1', $lookup['manifest_comparison']['tracking_pin']);
        $this->assertEquals('SC-MARIEL-001', $lookup['manifest_comparison']['solve_cargo_tracking_id']);
        $this->assertEquals('Carlos Valdés', $lookup['manifest_comparison']['recipient_name']);
        $this->assertEquals('Matanzas', $lookup['manifest_comparison']['destination_province']);
        $this->assertTrue($lookup['finance']['is_totally_prepaid']);
        $this->assertEquals(0.00, $lookup['finance']['total_to_collect_usd']);
    }

    public function test_scan_and_manifest_match_transitions_directly_to_ready_for_dispatch_without_assembly(): void
    {
        // En el área de despacho no se ensamblan los productos
        // Se cotejan con el manifiesto y pasan directamente a READY_FOR_DISPATCH
        $equipment = Equipment::create([
            'tracking_pin' => 'DSP-MATCH01',
            'vin_serial' => 'VIN-EAGLE-5544',
            'solve_cargo_tracking_id' => 'SC-CUBA-881122',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Ava',
            'model' => 'Puma 2000W',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'declared_value_usd' => 1500.00,
            'duty_paid_abroad' => true,
            'delivery_paid_abroad' => true,
            'home_delivery_requested' => true,
            'client_data' => [
                'name' => 'Lázaro Gómez',
                'province' => 'Villa Clara',
                'phone' => '+5353112233',
            ],
        ]);

        $result = $this->receptionService->verifyAndClearForDispatch(
            barcodeOrPin: 'DSP-MATCH01',
            manifestData: ['duty_paid_abroad' => true, 'delivery_paid_abroad' => true],
            receptionLocation: 'Puerto del Mariel - Área de Despacho',
            notes: 'Cotejo contra manifiesto SolveCargo #881122 conforme.',
            operator: $this->despachoUser
        );

        $this->assertTrue($result['success']);
        $this->assertTrue($result['manifest_match']);
        $this->assertEquals('Villa Clara', $result['destination_province']);

        $equipment->refresh();
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $equipment->current_status);
        $this->assertEquals('duty_exempt_prepaid', $equipment->customs_status);
        $this->assertNotNull($equipment->customs_cleared_at);
        $this->assertTrue($equipment->isTotallyPrepaidAbroad());
        $this->assertTrue($equipment->canBeDelivered());

        // Verificar registro de auditoría inmutable
        $history = EquipmentStatusHistory::where('equipment_id', $equipment->id)->latest('recorded_at')->first();
        $this->assertNotNull($history);
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH->value, $history->to_status);
        $this->assertStringContainsString('COTEJO CON MANIFIESTO EXITOSO', $history->notes);
        $this->assertEquals('MATCH_CONFIRMED', $history->snapshot['manifest_check']);
    }

    public function test_scan_and_manifest_match_with_pending_tariff_in_cuba(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'DSP-UNPAID1',
            'vin_serial' => 'VIN-UNPAID-001',
            'solve_cargo_tracking_id' => 'SC-CUBA-993322',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Mishozuki',
            'model' => 'Shark 3000W',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'declared_value_usd' => 1000.00,
            'duty_paid_abroad' => false,
            'delivery_paid_abroad' => false,
            'home_delivery_requested' => true,
            'client_data' => [
                'name' => 'Mario Pérez',
                'province' => 'Holguín',
            ],
        ]);

        $result = $this->receptionService->verifyAndClearForDispatch(
            barcodeOrPin: 'DSP-UNPAID1',
            manifestData: ['duty_paid_abroad' => false, 'delivery_paid_abroad' => false],
            operator: $this->despachoUser
        );

        $equipment->refresh();
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $equipment->current_status);
        $this->assertEquals('duty_pending_payment', $equipment->customs_status);
        $this->assertEquals(100.00, (float) $equipment->duty_amount_usd); // 10% de $1000 B2C
        $this->assertEquals(50.00, (float) $equipment->shipping_fee_usd); // Flete domicilio
        $this->assertFalse($equipment->isTotallyPrepaidAbroad());
        $this->assertFalse($equipment->canBeDelivered()); // Bloqueado hasta cobrar $150 USD
    }

    public function test_controller_scan_route_processes_and_returns_feedback(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'DSP-WEB-001',
            'vin_serial' => 'VIN-WEB-7722',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Ava',
            'model' => '500R',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'declared_value_usd' => 800.00,
            'duty_paid_abroad' => true,
            'client_data' => ['name' => 'Elena Díaz', 'province' => 'Cienfuegos'],
        ]);

        $response = $this->actingAs($this->despachoUser)->post(route('dispatch.scan.process'), [
            'barcode' => 'DSP-WEB-001',
            'duty_paid_abroad' => 1,
            'delivery_paid_abroad' => 0,
            'location' => 'Puerto del Mariel - Área de Despacho',
            'notes' => 'Inspección óptica de embalaje sin novedades.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $response->assertSessionHas('scan_result');

        $equipment->refresh();
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $equipment->current_status);
    }

    public function test_report_manifest_discrepancy(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'DSP-DISC-01',
            'vin_serial' => 'VIN-DISC-1122',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Mishozuki',
            'model' => 'GTX 1000',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'declared_value_usd' => 1000.00,
        ]);

        $result = $this->receptionService->reportManifestDiscrepancy(
            barcodeOrPin: 'DSP-DISC-01',
            discrepancyReason: 'El número de chasis físico difiere del manifiesto de embarque.',
            operator: $this->despachoUser
        );

        $this->assertTrue($result['success']);
        $this->assertFalse($result['manifest_match']);

        $equipment->refresh();
        // El estado no cambia a READY_FOR_DISPATCH al haber discrepancia
        $this->assertEquals(EquipmentStatus::PORT_ARRIVAL, $equipment->current_status);

        $history = EquipmentStatusHistory::where('equipment_id', $equipment->id)->latest('recorded_at')->first();
        $this->assertNotNull($history);
        $this->assertStringContainsString('DISCREPANCIA EN COTEJO CON MANIFIESTO', $history->notes);
    }
}
