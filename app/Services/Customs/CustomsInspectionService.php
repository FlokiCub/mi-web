<?php

declare(strict_types=1);

namespace App\Services\Customs;

use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\User;
use App\Services\Security\RbacService;
use App\Services\WhatsAppNotificationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomsInspectionService
{
    public function __construct(
        private readonly RbacService $rbacService,
        private readonly WhatsAppNotificationService $whatsAppService
    ) {}

    /**
     * Inicia la inspección aduanal física de un equipo en puerto.
     */
    public function startInspection(Equipment $equipment, User $inspector, ?string $notes = null): Equipment
    {
        if (!$this->rbacService->canTransitionStatus($inspector, $equipment, EquipmentStatus::CUSTOMS_INSPECTION)) {
            throw new DomainException("El usuario '{$inspector->name}' no tiene permisos para iniciar la inspección aduanal.");
        }

        try {
            return DB::transaction(function () use ($equipment, $inspector, $notes) {
                $previousStatus = $equipment->current_status;

                $equipment->update([
                    'current_status' => EquipmentStatus::CUSTOMS_INSPECTION,
                    'customs_status' => 'under_inspection',
                    'customs_entry_at' => $equipment->customs_entry_at ?? Carbon::now(),
                    'current_location_note' => $notes ?: 'En zona de inspección aduanal (Bahía Mariel)',
                ]);

                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'from_status' => $previousStatus->value,
                    'to_status' => EquipmentStatus::CUSTOMS_INSPECTION->value,
                    'changed_by_user_id' => $inspector->id,
                    'location' => 'Zona de Inspección Puerto Mariel',
                    'notes' => $notes ?: 'Inicio de inspección física por Aduana.',
                    'recorded_at' => Carbon::now(),
                ]);

                // Notificación WhatsApp
                $this->whatsAppService->sendStatusUpdateNotification($equipment);

                Log::info("Inspección aduanal iniciada para equipo {$equipment->tracking_pin} por {$inspector->name}");

                return $equipment->fresh();
            });
        } catch (\Throwable $e) {
            Log::error("Error iniciando inspección aduanal para {$equipment->tracking_pin}: " . $e->getMessage(), [
                'equipment_id' => $equipment->id,
                'inspector_id' => $inspector->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Otorga el libramiento aduanal al equipo y lo transiciona al siguiente paso (PVP para vehículos o Listo para Despacho para kits solares).
     */
    public function clearCustoms(
        Equipment $equipment,
        User $inspector,
        ?string $clearanceNote = null,
        ?string $customsDeclarationNumber = null
    ): Equipment {
        // En despacho no hay ensamblaje de bultos; cotejado con manifiesto pasa directo a READY_FOR_DISPATCH
        $targetStatus = EquipmentStatus::READY_FOR_DISPATCH;

        if (!$this->rbacService->canTransitionStatus($inspector, $equipment, $targetStatus)) {
            throw new DomainException("El usuario '{$inspector->name}' no tiene permisos para liberar el equipo de aduana.");
        }

        try {
            return DB::transaction(function () use ($equipment, $inspector, $clearanceNote, $customsDeclarationNumber, $targetStatus) {
                $previousStatus = $equipment->current_status;
                $now = Carbon::now();

                $specs = $equipment->technical_specs ?? [];
                if ($customsDeclarationNumber) {
                    $specs['customs_declaration_number'] = $customsDeclarationNumber;
                }

                $equipment->update([
                    'current_status' => $targetStatus,
                    'customs_status' => 'cleared',
                    'customs_cleared_at' => $now,
                    'technical_specs' => $specs,
                    'current_location_note' => 'Cotejo con manifiesto conforme -> Listo para despacho regional',
                ]);

                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'from_status' => $previousStatus->value,
                    'to_status' => $targetStatus->value,
                    'changed_by_user_id' => $inspector->id,
                    'location' => 'Aduana Puerto Mariel',
                    'notes' => $clearanceNote ?: "Libramiento aduanal aprobado. Declaración: " . ($customsDeclarationNumber ?: 'N/A'),
                    'recorded_at' => $now,
                ]);

                // Notificación WhatsApp
                $this->whatsAppService->sendStatusUpdateNotification($equipment);

                Log::info("Libramiento aduanal exitoso para {$equipment->tracking_pin} hacia {$targetStatus->value}");

                return $equipment->fresh();
            });
        } catch (\Throwable $e) {
            Log::error("Error en libramiento aduanal para {$equipment->tracking_pin}: " . $e->getMessage(), [
                'equipment_id' => $equipment->id,
                'inspector_id' => $inspector->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Retiene un equipo en aduana por irregularidad o inspección extraordinaria.
     */
    public function holdInCustoms(Equipment $equipment, User $inspector, string $reason): Equipment
    {
        try {
            return DB::transaction(function () use ($equipment, $inspector, $reason) {
                $previousStatus = $equipment->current_status;

                $equipment->update([
                    'customs_status' => 'held_in_customs',
                    'current_location_note' => "Retenido en Aduana: {$reason}",
                ]);

                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'from_status' => $previousStatus->value,
                    'to_status' => $previousStatus->value,
                    'changed_by_user_id' => $inspector->id,
                    'location' => 'Área de Retención Aduanal Mariel',
                    'notes' => "RETENCIÓN ADUANAL: {$reason}",
                    'recorded_at' => Carbon::now(),
                ]);

                Log::warning("Equipo {$equipment->tracking_pin} RETENIDO en aduana por {$inspector->name}. Motivo: {$reason}");

                return $equipment->fresh();
            });
        } catch (\Throwable $e) {
            Log::error("Error reteniendo equipo {$equipment->tracking_pin}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene el reporte y desglose de semáforos de permanencia aduanal.
     */
    public function getSemaphoreMetrics(): array
    {
        $inCustoms = Equipment::inCustoms()->get();

        $green = [];
        $yellow = [];
        $red = [];

        foreach ($inCustoms as $equipment) {
            $semaphore = $equipment->getCustomsSemaphore();
            if ($semaphore === 'red') {
                $red[] = $equipment;
            } elseif ($semaphore === 'yellow') {
                $yellow[] = $equipment;
            } else {
                $green[] = $equipment;
            }
        }

        return [
            'total_in_customs' => $inCustoms->count(),
            'green_count' => count($green),
            'yellow_count' => count($yellow), // >= 48h
            'red_count' => count($red),       // >= 72h (Crítico)
            'green_items' => $green,
            'yellow_items' => $yellow,
            'red_items' => $red,
        ];
    }
}
