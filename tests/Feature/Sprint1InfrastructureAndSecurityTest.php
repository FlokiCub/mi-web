<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\User;
use App\Services\Security\RbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint1InfrastructureAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Valida que la conexión activa sea PostgreSQL y la base sea scgi_db.
     */
    public function test_database_is_postgresql(): void
    {
        $connection = config('database.default');
        $this->assertEquals('pgsql', $connection);

        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        $this->assertInstanceOf(\PDO::class, $pdo);

        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $this->assertEquals('pgsql', $driver);
    }

    /**
     * Valida la existencia y tipado de los 7 perfiles en la base de datos.
     */
    public function test_seven_roles_exist_and_cast_properly(): void
    {
        $rolesExpected = [
            UserRole::DIRECTOR,
            UserRole::ADUANA,
            UserRole::PVP_TECNICO,
            UserRole::DESPACHO_PUERTO,
            UserRole::LOGISTICA_CHOFER,
            UserRole::CAJERO_REGIONAL,
            UserRole::AUDITOR,
        ];

        foreach ($rolesExpected as $role) {
            $user = User::where('role', $role->value)->first();
            $this->assertNotNull($user, "Debe existir un usuario con el rol: {$role->value}");
            $this->assertInstanceOf(UserRole::class, $user->role);
            $this->assertEquals($role, $user->role);
        }
    }

    /**
     * Valida el comportamiento de autorización jerárquica de hasRole.
     */
    public function test_director_has_omni_access_and_roles_are_restricted(): void
    {
        $director = User::where('role', UserRole::DIRECTOR->value)->first();
        $aduana = User::where('role', UserRole::ADUANA->value)->first();
        $pvp = User::where('role', UserRole::PVP_TECNICO->value)->first();

        // El Director tiene acceso a todo
        $this->assertTrue($director->hasRole(UserRole::ADUANA));
        $this->assertTrue($director->hasRole(UserRole::CAJERO_REGIONAL));
        $this->assertTrue($director->isAdmin());

        // Aduana NO tiene acceso a PVP ni a Cajas
        $this->assertTrue($aduana->hasRole(UserRole::ADUANA));
        $this->assertFalse($aduana->hasRole(UserRole::PVP_TECNICO));
        $this->assertFalse($aduana->hasRole(UserRole::CAJERO_REGIONAL));

        // PVP Técnico solo tiene acceso a su perfil
        $this->assertTrue($pvp->hasRole(UserRole::PVP_TECNICO));
        $this->assertFalse($pvp->hasRole(UserRole::ADUANA));
    }

    /**
     * Valida la persistencia de los nuevos campos de equipos en PostgreSQL.
     */
    public function test_equipment_has_customs_and_delivery_persistence_fields(): void
    {
        $eq = Equipment::where('tracking_pin', 'BK-78492')->first();

        $this->assertNotNull($eq);
        $this->assertArrayHasKey('delivery_paid_abroad', $eq->toArray());
        $this->assertArrayHasKey('customs_entry_at', $eq->toArray());
        $this->assertArrayHasKey('customs_cleared_at', $eq->toArray());
        $this->assertArrayHasKey('pvp_certified_at', $eq->toArray());
        $this->assertArrayHasKey('delivered_at', $eq->toArray());

        // Probar scopes aduanales 48h y 72h
        $delayed48 = Equipment::customsDelayed(48)->count();
        $critical72 = Equipment::customsCritical(72)->count();

        $this->assertGreaterThanOrEqual(1, $delayed48, 'Debe haber equipos con alerta > 48h');
        $this->assertGreaterThanOrEqual(1, $critical72, 'Debe haber equipos con alerta crítica > 72h');
    }

    /**
     * Valida las reglas de transición del RbacService.
     */
    public function test_rbac_service_status_transition_matrix(): void
    {
        $rbac = app(RbacService::class);

        $director = User::where('role', UserRole::DIRECTOR->value)->first();
        $aduana = User::where('role', UserRole::ADUANA->value)->first();
        $pvp = User::where('role', UserRole::PVP_TECNICO->value)->first();
        $chofer = User::where('role', UserRole::LOGISTICA_CHOFER->value)->first();
        $equipment = Equipment::first();

        // Director puede hacer cualquier cambio
        $this->assertTrue($rbac->canTransitionStatus($director, $equipment, EquipmentStatus::DELIVERED));

        // Aduana puede cambiar a CUSTOMS_CLEARED pero NO a DELIVERED
        $this->assertTrue($rbac->canTransitionStatus($aduana, $equipment, EquipmentStatus::CUSTOMS_CLEARED));
        $this->assertFalse($rbac->canTransitionStatus($aduana, $equipment, EquipmentStatus::DELIVERED));

        // PVP Técnico puede cambiar a READY_FOR_DISPATCH pero NO a PORT_ARRIVAL
        $this->assertTrue($rbac->canTransitionStatus($pvp, $equipment, EquipmentStatus::READY_FOR_DISPATCH));
        $this->assertFalse($rbac->canTransitionStatus($pvp, $equipment, EquipmentStatus::PORT_ARRIVAL));

        // Chofer puede transicionar a REGIONAL_TRANSIT pero NO a CUSTOMS_INSPECTION
        $this->assertTrue($rbac->canTransitionStatus($chofer, $equipment, EquipmentStatus::REGIONAL_TRANSIT));
        $this->assertFalse($rbac->canTransitionStatus($chofer, $equipment, EquipmentStatus::CUSTOMS_INSPECTION));
    }

    /**
     * Valida la protección HTTP por Middleware RBAC.
     */
    public function test_route_middleware_protects_endpoints_by_role(): void
    {
        $cajero = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();
        $despacho = User::where('role', UserRole::DESPACHO_PUERTO->value)->first();
        $director = User::where('role', UserRole::DIRECTOR->value)->first();

        // Cajero intenta entrar al escáner de puerto Mariel -> 403 Forbidden
        $response = $this->actingAs($cajero)->get(route('dispatch.scanner'));
        $response->assertStatus(403);

        // Operador de Despacho entra al escáner de puerto Mariel -> 200 OK
        $response = $this->actingAs($despacho)->get(route('dispatch.scanner'));
        $response->assertStatus(200);

        // Cajero intenta entrar al panel de administración de usuarios -> 403 Forbidden
        $response = $this->actingAs($cajero)->get(route('admin.users.index'));
        $response->assertStatus(403);

        // Director entra al panel de administración de usuarios -> 200 OK
        $response = $this->actingAs($director)->get(route('admin.users.index'));
        $response->assertStatus(200);
    }
}
