<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\RegionalDispatchRoute;
use App\Models\RegionalWarehouse;
use App\Models\User;
use App\Services\PvpAssemblyService;
use App\Services\RegionalLogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalFlowTest extends TestCase
{
    use RefreshDatabase;

    private PvpAssemblyService $pvpService;
    private RegionalLogisticsService $logisticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pvpService = app(PvpAssemblyService::class);
        $this->logisticsService = app(RegionalLogisticsService::class);
    }

    public function test_pvp_inspection_certifies_and_sets_ready_for_dispatch(): void
    {
        $technician = User::factory()->create(['role' => UserRole::PVP_TECNICO]);

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-PVP01',
            'vin_serial' => 'VIN-PVP-TEST-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'G5 Pro',
            'current_status' => EquipmentStatus::IN_PVP_ASSEMBLY,
        ]);

        $checklist = [
            'battery_voltage_verified' => true,
            'electrical_system_tested' => true,
            'accessories_installed' => true,
            'cosmetic_inspection_passed' => true,
            'bom_details' => ['espejos' => 'OK', 'cargador' => 'OK'],
        ];

        $inspection = $this->pvpService->certifyPvpAssembly(
            equipment: $equipment,
            checklistData: $checklist,
            batteryVoltage: '72.6V',
            notes: 'Puesta a punto completa y batería testeada al 100%.',
            technician: $technician
        );

        $this->assertEquals('passed', $inspection->result_status);
        $this->assertEquals('72.6V', $inspection->battery_tested_voltage);

        // Equipo debe transicionar a READY_FOR_DISPATCH
        $equipment->refresh();
        $this->assertEquals(EquipmentStatus::READY_FOR_DISPATCH, $equipment->current_status);

        // Bitácora de auditoría generada
        $this->assertDatabaseHas('equipment_status_histories', [
            'equipment_id' => $equipment->id,
            'to_status' => EquipmentStatus::READY_FOR_DISPATCH->value,
            'changed_by_user_id' => $technician->id,
        ]);
    }

    public function test_regional_route_dispatch_and_reception(): void
    {
        $operator = User::factory()->create(['role' => UserRole::LOGISTICA_CHOFER]);

        $origin = RegionalWarehouse::create([
            'code' => 'HAB-PVP',
            'name' => 'Patio Central La Habana',
            'province' => 'La Habana',
        ]);

        $destination = RegionalWarehouse::create([
            'code' => 'STG-01',
            'name' => 'Almacén Regional Santiago de Cuba',
            'province' => 'Santiago de Cuba',
        ]);

        $equipment = Equipment::create([
            'tracking_pin' => 'BK-ROUTE01',
            'vin_serial' => 'VIN-ROUTE-001',
            'equipment_type' => EquipmentType::SOLAR_SYSTEM,
            'business_type' => BusinessType::B2B,
            'brand' => 'Growatt',
            'model' => 'Kit 5kW',
            'current_status' => EquipmentStatus::READY_FOR_DISPATCH,
        ]);

        // Crear Hoja de Ruta
        $route = RegionalDispatchRoute::create([
            'route_code' => 'RUTA-2026-ORIENTE-01',
            'origin_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'driver_name' => 'Ernesto Morales',
            'driver_phone' => '+53 5 888 9999',
            'truck_license_plate' => 'B 123 456',
            'status' => 'draft',
        ]);

        $route->equipments()->attach($equipment->id);

        // 1. Despachar ruta
        $dispatchedRoute = $this->logisticsService->dispatchRoute($route, $operator);
        $this->assertEquals('in_transit', $dispatchedRoute->status);
        
        $equipment->refresh();
        $this->assertEquals(EquipmentStatus::REGIONAL_TRANSIT, $equipment->current_status);

        // 2. Recepción en destino regional
        $receivedRoute = $this->logisticsService->receiveRouteAtDestination($dispatchedRoute, $operator);
        $this->assertEquals('arrived_destination', $receivedRoute->status);

        $equipment->refresh();
        $this->assertEquals(EquipmentStatus::IN_REGIONAL_WAREHOUSE, $equipment->current_status);
        $this->assertEquals($destination->id, $equipment->current_warehouse_id);
    }
}
