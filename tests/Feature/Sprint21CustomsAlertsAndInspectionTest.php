<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\User;
use App\Services\Customs\CustomsInspectionService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint21CustomsAlertsAndInspectionTest extends TestCase
{
    use RefreshDatabase;

    private CustomsInspectionService $customsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->customsService = app(CustomsInspectionService::class);
    }

    /**
     * Valida el cálculo de permanencia en aduana y los 3 estados del semáforo (Verde, Amarillo 48h, Rojo 72h).
     */
    public function test_customs_stay_hours_and_semaphore_calculation(): void
    {
        // 1. Equipo Verde (<48h)
        $greenEq = Equipment::create([
            'tracking_pin' => 'BK-SEM-GRN',
            'vin_serial' => 'VIN-SEM-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'customs_entry_at' => Carbon::now()->subHours(12),
        ]);

        $this->assertEquals(12, $greenEq->getCustomsStayHours());
        $this->assertEquals('green', $greenEq->getCustomsSemaphore());
        $this->assertStringContainsString('Normal', $greenEq->getCustomsSemaphoreLabel());

        // 2. Equipo Amarillo (48h - 72h)
        $yellowEq = Equipment::create([
            'tracking_pin' => 'BK-SEM-YEL',
            'vin_serial' => 'VIN-SEM-002',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'T9',
            'current_status' => EquipmentStatus::CUSTOMS_INSPECTION,
            'customs_entry_at' => Carbon::now()->subHours(50),
        ]);

        $this->assertEquals(50, $yellowEq->getCustomsStayHours());
        $this->assertEquals('yellow', $yellowEq->getCustomsSemaphore());
        $this->assertStringContainsString('Preventivo', $yellowEq->getCustomsSemaphoreLabel());

        // 3. Equipo Rojo (>72h Crítico)
        $redEq = Equipment::create([
            'tracking_pin' => 'BK-SEM-RED',
            'vin_serial' => 'VIN-SEM-003',
            'equipment_type' => EquipmentType::SOLAR_SYSTEM,
            'business_type' => BusinessType::B2B,
            'brand' => 'Growatt',
            'model' => 'SPF 5000',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'customs_entry_at' => Carbon::now()->subHours(80),
        ]);

        $this->assertEquals(80, $redEq->getCustomsStayHours());
        $this->assertEquals('red', $redEq->getCustomsSemaphore());
        $this->assertStringContainsString('Crítico', $redEq->getCustomsSemaphoreLabel());

        // 4. Scopes Eloquent de alerta aduanal
        $delayed48h = Equipment::customsDelayed(48)->pluck('tracking_pin')->toArray();
        $this->assertContains('BK-SEM-YEL', $delayed48h);
        $this->assertContains('BK-SEM-RED', $delayed48h);
        $this->assertNotContains('BK-SEM-GRN', $delayed48h);

        $critical72h = Equipment::customsCritical(72)->pluck('tracking_pin')->toArray();
        $this->assertContains('BK-SEM-RED', $critical72h);
        $this->assertNotContains('BK-SEM-YEL', $critical72h);
        $this->assertNotContains('BK-SEM-GRN', $critical72h);
    }

    /**
     * Valida el inicio de inspección física por un inspector de Aduana.
     */
    public function test_customs_inspector_can_start_physical_inspection(): void
    {
        $inspector = User::where('role', UserRole::ADUANA->value)->first();

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-INSP-01',
            'vin_serial' => 'VIN-INSPECT-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'customs_entry_at' => Carbon::now()->subHours(5),
            'client_data' => ['name' => 'Mario Valdés', 'phone' => '+53 5 333 4444'],
        ]);

        $updated = $this->customsService->startInspection(
            equipment: $equipment,
            inspector: $inspector,
            notes: 'Apertura de bulto y verificación de número de motor.'
        );

        $this->assertEquals(EquipmentStatus::CUSTOMS_INSPECTION, $updated->current_status);
        $this->assertEquals('under_inspection', $updated->customs_status);

        // Bitácora inmutable
        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $equipment->id,
            'to_status' => EquipmentStatus::CUSTOMS_INSPECTION->value,
            'changed_by_user_id' => $inspector->id,
        ]);

        // Notificación de WhatsApp generada
        $this->assertDatabaseHas('whatsapp_notifications', [
            'equipment_id' => $equipment->id,
            'recipient_phone' => '+53 5 333 4444',
            'event_trigger' => EquipmentStatus::CUSTOMS_INSPECTION->value,
        ]);
    }

    /**
     * Valida el libramiento aduanal: Los bultos cotejados pasan directamente a Listo para Despacho.
     */
    public function test_clear_customs_transitions_equipment_directly_to_ready_for_dispatch(): void
    {
        $inspector = User::where('role', UserRole::ADUANA->value)->first();

        // 1. Vehículo en inspección -> pasa directamente a READY_FOR_DISPATCH
        $vehicle = Equipment::create([
            'tracking_pin' => 'BK-CLEAR-VEH',
            'vin_serial' => 'VIN-CLEAR-VEH-01',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'current_status' => EquipmentStatus::CUSTOMS_INSPECTION,
            'customs_entry_at' => Carbon::now()->subHours(24),
        ]);

        $clearedVehicle = $this->customsService->clearCustoms(
            equipment: $vehicle,
            inspector: $inspector,
            clearanceNote: 'Aforo conforme, cotejo de paquete sin discrepancias.',
            customsDeclarationNumber: 'DECL-ADU-2026-7788'
        );

        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $clearedVehicle->current_status);
        $this->assertEquals('cleared', $clearedVehicle->customs_status);
        $this->assertNotNull($clearedVehicle->customs_cleared_at);
        $this->assertEquals('green', $clearedVehicle->getCustomsSemaphore());

        // 2. Kit Solar en inspección -> pasa directamente a READY_FOR_DISPATCH
        $solar = Equipment::create([
            'tracking_pin' => 'BK-CLEAR-SOL',
            'vin_serial' => 'VIN-CLEAR-SOL-01',
            'equipment_type' => EquipmentType::SOLAR_SYSTEM,
            'business_type' => BusinessType::B2B,
            'brand' => 'Growatt',
            'model' => 'Kit 5kW',
            'current_status' => EquipmentStatus::CUSTOMS_INSPECTION,
            'customs_entry_at' => Carbon::now()->subHours(10),
        ]);

        $clearedSolar = $this->customsService->clearCustoms(
            equipment: $solar,
            inspector: $inspector,
            clearanceNote: 'Exención B2B conforme, libramiento otorgado.',
            customsDeclarationNumber: 'DECL-B2B-2026-9911'
        );

        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $clearedSolar->current_status);
        $this->assertEquals('cleared', $clearedSolar->customs_status);
    }

    /**
     * Valida la retención preventiva en aduana por irregularidad.
     */
    public function test_customs_inspector_can_hold_equipment(): void
    {
        $inspector = User::where('role', UserRole::ADUANA->value)->first();

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-HOLD-01',
            'vin_serial' => 'VIN-HOLD-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'current_status' => EquipmentStatus::CUSTOMS_INSPECTION,
            'customs_entry_at' => Carbon::now()->subHours(30),
        ]);

        $heldEquipment = $this->customsService->holdInCustoms(
            equipment: $equipment,
            inspector: $inspector,
            reason: 'Número de motor no coincide con manifiesto SolveCargo.'
        );

        $this->assertEquals('held_in_customs', $heldEquipment->customs_status);
        $this->assertStringContainsString('Retenido en Aduana', $heldEquipment->current_location_note);
    }

    /**
     * Valida la protección RBAC en las rutas web de Aduana.
     */
    public function test_customs_web_routes_rbac_protection(): void
    {
        $cajero = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();
        $chofer = User::where('role', UserRole::LOGISTICA_CHOFER->value)->first();
        $aduana = User::where('role', UserRole::ADUANA->value)->first();
        $director = User::where('role', UserRole::DIRECTOR->value)->first();

        // 1. Usuarios no autorizados reciben 403
        $this->actingAs($cajero)->get(route('customs.index'))->assertStatus(403);
        $this->actingAs($chofer)->get(route('customs.index'))->assertStatus(403);

        // 2. Inspector de Aduana y Director acceden correctamente
        $this->actingAs($aduana)->get(route('customs.index'))->assertStatus(200)->assertSee('Inspección Aduanal');
        $this->actingAs($director)->get(route('customs.index'))->assertStatus(200)->assertSee('Inspección Aduanal');
    }

    /**
     * Valida los flujos de acción a través de endpoints HTTP de CustomsController.
     */
    public function test_customs_controller_http_actions(): void
    {
        $aduana = User::where('role', UserRole::ADUANA->value)->first();

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-HTTP-ADU',
            'vin_serial' => 'VIN-HTTP-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
            'customs_entry_at' => Carbon::now()->subHours(10),
        ]);

        // Iniciar inspección vía POST
        $response = $this->actingAs($aduana)->post(route('customs.start-inspection', $equipment), [
            'notes' => 'Iniciando aforo por ventanilla aduanal',
        ]);
        $response->assertRedirect();
        $this->assertEquals(EquipmentStatus::CUSTOMS_INSPECTION, $equipment->fresh()->current_status);

        // Aprobar libramiento vía POST
        $clearResponse = $this->actingAs($aduana)->post(route('customs.clear', $equipment), [
            'clearance_note' => 'Libramiento OK',
            'customs_declaration_number' => 'DECL-2026-FINAL',
        ]);
        $clearResponse->assertRedirect(route('customs.index'));
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $equipment->fresh()->current_status);
    }
}
