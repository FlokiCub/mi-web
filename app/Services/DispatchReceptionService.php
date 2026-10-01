<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DispatchReceptionService
{
    public function __construct(
        protected TariffService $tariffService,
        protected WhatsAppNotificationService $whatsAppService
    ) {}

    /**
     * Busca un equipo y genera el cotejo preliminar de datos con el manifiesto para el escáner.
     *
     * @param string $barcodeOrPin Código de barras, PIN, VIN o ID SolveCargo
     * @return array Datos del equipo y resumen de cotejo con el manifiesto
     */
    public function lookupManifestForScan(string $barcodeOrPin): array
    {
        $code = strtoupper(trim($barcodeOrPin));

        $equipment = Equipment::where('tracking_pin', $code)
            ->orWhere('vin_serial', $code)
            ->orWhere('solve_cargo_tracking_id', $code)
            ->first();

        if (!$equipment) {
            throw new InvalidArgumentException("No se encontró ningún paquete o equipo con el código \"{$code}\".");
        }

        $clientData = $equipment->client_data ?? [];
        $destinationProvince = $clientData['province'] ?? $clientData['destination_province'] ?? 'La Habana';

        // Pre-cálculo financiero
        $finance = $this->tariffService->calculateLiquidation(
            declaredValueUsd: (float) $equipment->declared_value_usd,
            businessType: $equipment->business_type,
            dutyPaidAbroad: (bool) $equipment->duty_paid_abroad,
            deliveryPaidAbroad: (bool) $equipment->delivery_paid_abroad,
            homeDeliveryRequested: (bool) $equipment->home_delivery_requested
        );

        return [
            'equipment' => $equipment,
            'manifest_comparison' => [
                'tracking_pin' => $equipment->tracking_pin,
                'solve_cargo_tracking_id' => $equipment->solve_cargo_tracking_id ?? 'SC-MANIFIESTO-'.substr($equipment->id, 0, 8),
                'brand_model' => "{$equipment->brand} {$equipment->model}",
                'equipment_type' => $equipment->equipment_type->value ?? 'Equipo',
                'vin_serial' => $equipment->vin_serial ?? 'N/A',
                'recipient_name' => $clientData['name'] ?? 'Cliente Registrado',
                'recipient_id' => $clientData['id_number'] ?? 'CI No especificado',
                'recipient_phone' => $clientData['phone'] ?? 'N/A',
                'destination_province' => $destinationProvince,
                'declared_value_usd' => (float) $equipment->declared_value_usd,
                'duty_paid_abroad' => (bool) $equipment->duty_paid_abroad,
                'delivery_paid_abroad' => (bool) $equipment->delivery_paid_abroad,
                'home_delivery_requested' => (bool) $equipment->home_delivery_requested,
            ],
            'finance' => $finance,
            'current_status' => $equipment->current_status->value,
            'current_status_label' => $equipment->current_status->label(),
        ];
    }

    /**
     * Procesa el escaneo de recepción y cotejo con el manifiesto en el Área de Despacho (Mariel).
     * Regla de Negocio: En despacho NO se ensamblan productos; pasan únicamente por el escáner
     * que confirma la coincidencia con el manifiesto y transicionan directamente a READY_FOR_DISPATCH.
     *
     * @param string $barcodeOrPin Código de barras, PIN, VIN o SolveCargo ID
     * @param array $manifestData Datos declarados en el manifiesto
     * @param string $receptionLocation Ubicación del escaneo (ej: 'Puerto del Mariel - Área de Despacho')
     * @param string|null $notes Notas del operador
     * @param User|null $operator Operador que realiza el escaneo
     * @return array Resultado de la verificación y estado actualizado
     */
    public function verifyAndClearForDispatch(
        string $barcodeOrPin,
        array $manifestData = [],
        string $receptionLocation = 'Puerto del Mariel - Área de Despacho',
        ?string $notes = null,
        ?User $operator = null
    ): array {
        $code = strtoupper(trim($barcodeOrPin));

        $equipment = Equipment::where('tracking_pin', $code)
            ->orWhere('vin_serial', $code)
            ->orWhere('solve_cargo_tracking_id', $code)
            ->first();

        if (!$equipment) {
            throw new InvalidArgumentException("No se encontró ningún equipo en el sistema con el código \"{$code}\".");
        }

        return DB::transaction(function () use ($equipment, $manifestData, $receptionLocation, $notes, $operator) {
            $fromStatus = $equipment->current_status;

            // Determinar si en el manifiesto viene marcado como pagado desde el exterior
            $dutyPaidAbroad = array_key_exists('duty_paid_abroad', $manifestData)
                ? (bool) $manifestData['duty_paid_abroad']
                : (bool) $equipment->duty_paid_abroad;

            $deliveryPaidAbroad = array_key_exists('delivery_paid_abroad', $manifestData)
                ? (bool) $manifestData['delivery_paid_abroad']
                : (bool) $equipment->delivery_paid_abroad;

            // Calcular liquidación arancelaria con el TariffService
            $finance = $this->tariffService->calculateLiquidation(
                declaredValueUsd: (float) $equipment->declared_value_usd,
                businessType: $equipment->business_type,
                dutyPaidAbroad: $dutyPaidAbroad,
                deliveryPaidAbroad: $deliveryPaidAbroad,
                homeDeliveryRequested: (bool) $equipment->home_delivery_requested
            );

            // Estado aduanal según prepago
            $customsStatus = $finance['is_totally_prepaid']
                ? 'duty_exempt_prepaid'
                : 'duty_pending_payment';

            $clientData = $equipment->client_data ?? [];
            $destinationProvince = $clientData['province'] ?? $clientData['destination_province'] ?? 'Destino Regional';

            // Actualizar a READY_FOR_DISPATCH (sin ensamblaje)
            $equipment->update([
                'current_status' => EquipmentStatus::READY_FOR_DISPATCH,
                'customs_status' => $customsStatus,
                'duty_paid_abroad' => $dutyPaidAbroad,
                'delivery_paid_abroad' => $deliveryPaidAbroad,
                'customs_entry_at' => $equipment->customs_entry_at ?? now(),
                'customs_cleared_at' => $equipment->customs_cleared_at ?? now(),
                'duty_amount_usd' => $finance['duty_amount_usd'],
                'shipping_fee_usd' => $finance['shipping_fee_usd'],
                'current_location_note' => $receptionLocation,
            ]);

            // Descripción inmutable del cotejo exitoso
            $financialNote = $finance['is_totally_prepaid']
                ? "✓ 100% Prepagado en Origen ($0.00 a cobrar en Cuba)."
                : "Saldo pendiente en Cuba: \${$finance['total_to_collect_usd']} USD ({$finance['total_to_collect_cup']} CUP).";

            $eventNotes = "COTEJO CON MANIFIESTO EXITOSO. Paquete verificado mediante escáner en despacho (Sin ensamblaje físico). Destino: {$destinationProvince}. {$financialNote}" .
                ($notes ? " Observaciones: {$notes}" : "");

            // Registrar en la bitácora inmutable
            EquipmentStatusHistory::create([
                'equipment_id' => $equipment->id,
                'from_status' => is_object($fromStatus) ? $fromStatus->value : $fromStatus,
                'to_status' => EquipmentStatus::READY_FOR_DISPATCH->value,
                'location' => $receptionLocation,
                'notes' => $eventNotes,
                'snapshot' => array_merge($equipment->toArray(), [
                    'manifest_check' => 'MATCH_CONFIRMED',
                    'finance' => $finance,
                    'destination_province' => $destinationProvince,
                ]),
                'changed_by_user_id' => $operator?->id,
                'recorded_at' => now(),
            ]);

            // Disparar Notificación Automática de WhatsApp al Cliente
            try {
                $this->whatsAppService->sendStatusUpdateNotification($equipment);
            } catch (\Throwable $e) {
                Log::error("Error al enviar WhatsApp tras cotejo en despacho para PIN {$equipment->tracking_pin}: " . $e->getMessage());
            }

            return [
                'success' => true,
                'manifest_match' => true,
                'equipment' => $equipment->fresh(),
                'finance' => $finance,
                'destination_province' => $destinationProvince,
                'message' => "✓ Paquete {$equipment->brand} {$equipment->model} (PIN: {$equipment->tracking_pin}) cotejado con éxito contra el manifiesto. Listo para despacho hacia {$destinationProvince}.",
            ];
        });
    }

    /**
     * Reporta una discrepancia entre el paquete escaneado y el manifiesto.
     *
     * @param string $barcodeOrPin Código escaneado
     * @param string $discrepancyReason Motivo de la discrepancia
     * @param string $location Ubicación
     * @param User|null $operator Operador
     * @return array Resultado del reporte
     */
    public function reportManifestDiscrepancy(
        string $barcodeOrPin,
        string $discrepancyReason,
        string $location = 'Puerto del Mariel - Área de Despacho',
        ?User $operator = null
    ): array {
        $code = strtoupper(trim($barcodeOrPin));

        $equipment = Equipment::where('tracking_pin', $code)
            ->orWhere('vin_serial', $code)
            ->orWhere('solve_cargo_tracking_id', $code)
            ->first();

        if (!$equipment) {
            throw new InvalidArgumentException("No se encontró ningún equipo con el código \"{$code}\".");
        }

        return DB::transaction(function () use ($equipment, $discrepancyReason, $location, $operator) {
            EquipmentStatusHistory::create([
                'equipment_id' => $equipment->id,
                'from_status' => $equipment->current_status->value,
                'to_status' => $equipment->current_status->value,
                'location' => $location,
                'notes' => "⚠️ DISCREPANCIA EN COTEJO CON MANIFIESTO: {$discrepancyReason}",
                'snapshot' => [
                    'manifest_check' => 'DISCREPANCY_FLAGGED',
                    'reason' => $discrepancyReason,
                    'equipment' => $equipment->toArray(),
                ],
                'changed_by_user_id' => $operator?->id,
                'recorded_at' => now(),
            ]);

            return [
                'success' => true,
                'manifest_match' => false,
                'equipment' => $equipment,
                'message' => "Discrepancia registrada para el paquete {$equipment->tracking_pin}: {$discrepancyReason}",
            ];
        });
    }

    /**
     * Alias compatible para recepción general.
     */
    public function receiveEquipmentAtCubaDispatch(
        string $barcodeOrPin,
        array $manifestData = [],
        string $receptionLocation = 'Puerto del Mariel - Área de Despacho',
        ?string $notes = null,
        ?User $operator = null
    ): array {
        return $this->verifyAndClearForDispatch($barcodeOrPin, $manifestData, $receptionLocation, $notes, $operator);
    }
}
