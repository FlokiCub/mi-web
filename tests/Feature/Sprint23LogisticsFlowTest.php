<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\RegionalDispatchRoute;
use App\Models\RegionalWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\RegionalLogisticsService;

class Sprint23LogisticsFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $driverUser;
    protected RegionalWarehouse $destinationWarehouse;
    protected Equipment $readyEquipment;
    protected RegionalLogisticsService $logisticsService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logisticsService = app(RegionalLogisticsService::class);

        // Crear usuarios con roles
        $this->adminUser = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);
        
        $this->driverUser = User::factory()->create([
            'role' => UserRole::DRIVER->value,
        ]);

        // Crear almacén destino
        $this->destinationWarehouse = RegionalWarehouse::create([
            'name' => 'Almacén Central Pinar del Río',
            'province' => 'Pinar del Río',
            'address' => 'Calle Principal #123',
            'manager_id' => $this->adminUser->id,
            'capacity' => 100,
        ]);

        // Crear cliente y equipo listo para despacho
        $customer = Customer::factory()->create();
        
        $this->readyEquipment = Equipment::factory()->create([
            'customer_id' => $customer->id,
            'status' => EquipmentStatus::READY_FOR_DISPATCH->value,
            'tracking_code' => 'TRACK-12345',
        ]);
    }

    public function test_can_create_a_regional_dispatch_route()
    {
        $route = $this->logisticsService->createRoute(
            $this->destinationWarehouse->id,
            $this->driverUser->id,
            'CUB-123',
            $this->adminUser->id
        );

        $this->assertDatabaseHas('regional_dispatch_routes', [
            'id' => $route->id,
            'destination_warehouse_id' => $this->destinationWarehouse->id,
            'driver_id' => $this->driverUser->id,
            'vehicle_plate' => 'CUB-123',
            'status' => 'PENDING',
        ]);
    }

    public function test_can_add_equipment_to_route()
    {
        $route = $this->logisticsService->createRoute(
            $this->destinationWarehouse->id,
            $this->driverUser->id,
            'CUB-123',
            $this->adminUser->id
        );

        $this->logisticsService->addEquipmentToRoute($route->id, [$this->readyEquipment->id]);

        $this->assertDatabaseHas('equipment_regional_dispatch_route', [
            'regional_dispatch_route_id' => $route->id,
            'equipment_id' => $this->readyEquipment->id,
        ]);
    }

    public function test_cannot_add_equipment_not_ready_for_dispatch()
    {
        $notReadyEquipment = Equipment::factory()->create([
            'status' => EquipmentStatus::PORT_ARRIVAL->value,
        ]);

        $route = $this->logisticsService->createRoute(
            $this->destinationWarehouse->id,
            $this->driverUser->id,
            'CUB-123',
            $this->adminUser->id
        );

        $this->expectException(\Exception::class);
        $this->logisticsService->addEquipmentToRoute($route->id, [$notReadyEquipment->id]);
    }

    public function test_can_dispatch_route()
    {
        $route = $this->logisticsService->createRoute(
            $this->destinationWarehouse->id,
            $this->driverUser->id,
            'CUB-123',
            $this->adminUser->id
        );

        $this->logisticsService->addEquipmentToRoute($route->id, [$this->readyEquipment->id]);
        
        $this->logisticsService->dispatchRoute($route->id, $this->adminUser->id);

        $this->assertDatabaseHas('regional_dispatch_routes', [
            'id' => $route->id,
            'status' => 'IN_TRANSIT',
        ]);

        $this->assertDatabaseHas('equipments', [
            'id' => $this->readyEquipment->id,
            'status' => EquipmentStatus::REGIONAL_TRANSIT->value,
        ]);
    }

    public function test_can_receive_route_at_destination()
    {
        $route = $this->logisticsService->createRoute(
            $this->destinationWarehouse->id,
            $this->driverUser->id,
            'CUB-123',
            $this->adminUser->id
        );

        $this->logisticsService->addEquipmentToRoute($route->id, [$this->readyEquipment->id]);
        $this->logisticsService->dispatchRoute($route->id, $this->adminUser->id);
        
        $this->logisticsService->receiveRouteAtDestination($route->id, $this->adminUser->id, 'Received in good condition');

        $this->assertDatabaseHas('regional_dispatch_routes', [
            'id' => $route->id,
            'status' => 'COMPLETED',
        ]);

        $this->assertDatabaseHas('equipments', [
            'id' => $this->readyEquipment->id,
            'status' => EquipmentStatus::IN_REGIONAL_WAREHOUSE->value,
        ]);
    }
}
