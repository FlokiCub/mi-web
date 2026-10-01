<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class RbacService
{
    /**
     * Matriz de transiciones de estado permitidas por rol.
     *
     * @var array<string, list<EquipmentStatus>>
     */
    protected array $allowedTransitionsByRole = [
        UserRole::DIRECTOR->value => [
            EquipmentStatus::SUPPLIER_DISPATCHED,
            EquipmentStatus::IN_TRANSIT_SOLVE_CARGO,
            EquipmentStatus::PORT_ARRIVAL,
            EquipmentStatus::CUSTOMS_INSPECTION,
            EquipmentStatus::CUSTOMS_CLEARED,
            EquipmentStatus::IN_PVP_ASSEMBLY,
            EquipmentStatus::READY_FOR_DISPATCH,
            EquipmentStatus::REGIONAL_TRANSIT,
            EquipmentStatus::IN_REGIONAL_WAREHOUSE,
            EquipmentStatus::OUT_FOR_DELIVERY,
            EquipmentStatus::DELIVERED,
        ],
        UserRole::ADUANA->value => [
            EquipmentStatus::PORT_ARRIVAL,
            EquipmentStatus::CUSTOMS_INSPECTION,
            EquipmentStatus::CUSTOMS_CLEARED,
            EquipmentStatus::IN_PVP_ASSEMBLY,
            EquipmentStatus::READY_FOR_DISPATCH,
        ],
        UserRole::PVP_TECNICO->value => [
            EquipmentStatus::IN_PVP_ASSEMBLY,
            EquipmentStatus::READY_FOR_DISPATCH,
        ],
        UserRole::DESPACHO_PUERTO->value => [
            EquipmentStatus::PORT_ARRIVAL,
            EquipmentStatus::CUSTOMS_INSPECTION,
            EquipmentStatus::IN_PVP_ASSEMBLY,
            EquipmentStatus::READY_FOR_DISPATCH,
        ],
        UserRole::LOGISTICA_CHOFER->value => [
            EquipmentStatus::REGIONAL_TRANSIT,
            EquipmentStatus::IN_REGIONAL_WAREHOUSE,
        ],
        UserRole::CAJERO_REGIONAL->value => [
            EquipmentStatus::OUT_FOR_DELIVERY,
            EquipmentStatus::DELIVERED,
        ],
        UserRole::AUDITOR->value => [],
    ];

    /**
     * Valida si un usuario tiene autorización para transicionar un equipo a un nuevo estado.
     */
    public function canTransitionStatus(User $user, Equipment $equipment, EquipmentStatus $targetStatus): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isDirector()) {
            return true;
        }

        $roleValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
        $allowedStatuses = $this->allowedTransitionsByRole[$roleValue] ?? [];

        // Regla Financiera Estricta: Bloquear entrega si el equipo tiene saldo pendiente de pago
        if ($targetStatus === EquipmentStatus::DELIVERED && !$equipment->canBeDelivered()) {
            Log::warning("Intento de entrega bloqueado: El equipo [{$equipment->tracking_pin}] tiene saldo pendiente de cobro en Cuba.");
            return false;
        }

        $canTransition = in_array($targetStatus, $allowedStatuses, true);

        if (!$canTransition) {
            Log::warning("Intento no autorizado de cambio de estado: Usuario [{$user->id}|{$user->email}|Rol: {$roleValue}] intentó cambiar equipo [{$equipment->tracking_pin}] a estado [{$targetStatus->value}].");
        }

        return $canTransition;
    }

    /**
     * Verifica si un usuario tiene acceso a un módulo específico del sistema.
     *
     * @param string $module ('customs', 'pvp', 'dispatch', 'logistics', 'cashier', 'executive_dashboard', 'users')
     */
    public function canAccessModule(User $user, string $module): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->isDirector()) {
            return true;
        }

        return match ($module) {
            'customs' => $user->isAduana(),
            'pvp' => $user->isPvpTecnico(),
            'dispatch' => $user->isDespachoPuerto() || $user->isAduana(),
            'logistics' => $user->isLogisticaChofer(),
            'cashier' => $user->isCajeroRegional(),
            'executive_dashboard' => $user->isDirector() || $user->isAuditor(),
            'users' => $user->isDirector(),
            'tracking' => true, // Todos los usuarios autenticados pueden consultar tracking
            default => false,
        };
    }
}
