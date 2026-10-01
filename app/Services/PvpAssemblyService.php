<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\PvpInspection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PvpAssemblyService
{
    public function __construct(
        protected WhatsAppNotificationService $whatsAppService
    ) {}

    /**
     * Registra o aprueba la inspección técnica BOM y puesta a punto de un equipo en Patio PVP.
     */
    public function certifyPvpAssembly(
        Equipment $equipment,
        array $checklistData,
        string $batteryVoltage,
        ?string $notes = null,
        ?User $technician = null
    ): PvpInspection {
        return DB::transaction(function () use ($equipment, $checklistData, $batteryVoltage, $notes, $technician) {
            
            $batteryOk = !empty($checklistData['battery_voltage_verified']);
            $electricalOk = !empty($checklistData['electrical_system_tested']);
            $accessoriesOk = !empty($checklistData['accessories_installed']);
            $cosmeticsOk = !empty($checklistData['cosmetic_inspection_passed']);

            $allPassed = ($batteryOk && $electricalOk && $accessoriesOk && $cosmeticsOk);
            $resultStatus = $allPassed ? 'passed' : 'requires_repair';

            $inspection = PvpInspection::create([
                'equipment_id' => $equipment->id,
                'battery_voltage_verified' => $batteryOk,
                'electrical_system_tested' => $electricalOk,
                'accessories_installed' => $accessoriesOk,
                'cosmetic_inspection_passed' => $cosmeticsOk,
                'result_status' => $resultStatus,
                'battery_tested_voltage' => $batteryVoltage,
                'bom_checklist_details' => $checklistData['bom_details'] ?? [],
                'technician_notes' => $notes,
                'technician_user_id' => $technician?->id,
                'inspected_at' => now(),
            ]);

            // Si pasa todo el control de calidad, transiciona a Listo para Despacho Regional
            if ($allPassed) {
                $equipment->update([
                    'current_status' => EquipmentStatus::READY_FOR_DISPATCH,
                    'current_location_note' => 'Patio Central PVP - Zona de Despacho Listo',
                ]);

                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'from_status' => EquipmentStatus::IN_PVP_ASSEMBLY->value,
                    'to_status' => EquipmentStatus::READY_FOR_DISPATCH->value,
                    'location' => 'Patio Central PVP',
                    'notes' => "Puesta a punto y control de calidad APROBADO (Batería: {$batteryVoltage}). Listo para hoja de ruta regional.",
                    'changed_by_user_id' => $technician?->id,
                    'recorded_at' => now(),
                ]);

                // Notificar por WhatsApp
                $this->whatsAppService->sendStatusUpdateNotification($equipment);
            }

            return $inspection;
        });
    }
}
