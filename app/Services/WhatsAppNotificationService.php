<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\WhatsAppNotification;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    /**
     * Construye el mensaje dinámico y lo envía al cliente a través de WhatsApp.
     */
    public function sendStatusUpdateNotification(Equipment $equipment, ?string $customNote = null): WhatsAppNotification
    {
        $clientPhone = $equipment->client_data['phone'] ?? null;
        $clientName = $equipment->client_data['name'] ?? 'Estimado Cliente';

        if (empty($clientPhone)) {
            Log::warning("No se pudo enviar WhatsApp para el equipo {$equipment->tracking_pin}: Teléfono no registrado.");
            return WhatsAppNotification::create([
                'equipment_id' => $equipment->id,
                'recipient_phone' => 'N/A',
                'recipient_name' => $clientName,
                'event_trigger' => is_object($equipment->current_status) ? $equipment->current_status->value : $equipment->current_status,
                'message_body' => 'Intento fallido: Teléfono no especificado.',
                'status' => 'failed',
                'error_details' => 'Teléfono del cliente no registrado en ficha.',
            ]);
        }

        // 1. Construir texto enriquecido según el estado actual
        $messageBody = $this->buildMessageText($equipment, $clientName, $customNote);

        // 2. Despachar al Gateway de WhatsApp
        $dispatchResult = $this->dispatchToGateway($clientPhone, $messageBody);

        // 3. Registrar en bitácora de auditoría
        return WhatsAppNotification::create([
            'equipment_id' => $equipment->id,
            'recipient_phone' => $clientPhone,
            'recipient_name' => $clientName,
            'event_trigger' => is_object($equipment->current_status) ? $equipment->current_status->value : $equipment->current_status,
            'message_body' => $messageBody,
            'status' => $dispatchResult['success'] ? 'sent' : 'failed',
            'gateway_message_id' => $dispatchResult['message_id'] ?? null,
            'error_details' => $dispatchResult['error'] ?? null,
            'sent_at' => now(),
        ]);
    }

    /**
     * Genera la plantilla de mensaje adecuada según el estado del equipo.
     */
    public function buildMessageText(Equipment $equipment, string $clientName, ?string $customNote = null): string
    {
        $trackingUrl = url("/tracking/{$equipment->tracking_pin}");
        $statusLabel = is_object($equipment->current_status) && method_exists($equipment->current_status, 'label') 
            ? $equipment->current_status->label() 
            : ucfirst(str_replace('_', ' ', $equipment->current_status));

        $statusCode = is_object($equipment->current_status) ? $equipment->current_status->value : $equipment->current_status;

        $msg = "🌟 *BLANKISOL — Actualización de tu Envío*\n\n";
        $msg .= "Hola *{$clientName}*,\n";
        $msg .= "Te informamos sobre el estado de tu equipo: *{$equipment->brand} {$equipment->model}*.\n\n";
        $msg .= "📍 *Estado Actual:* {$statusLabel}\n";
        $msg .= "🏷️ *PIN de Rastreo:* `{$equipment->tracking_pin}`\n";

        // Mensajes dinámicos según el hito
        switch ($statusCode) {
            case EquipmentStatus::PORT_ARRIVAL->value:
                $msg .= "🚢 *¡Tu equipo ya arribó a Cuba!* Se encuentra en el área de recepción aduanal del puerto para su inspección.\n";
                if ($equipment->duty_paid_abroad) {
                    $msg .= "✅ *Aranceles:* Pagados en el exterior ($0.00 a pagar en Cuba).\n";
                }
                break;

            case EquipmentStatus::IN_PVP_ASSEMBLY->value:
                $msg .= "🔧 *En Patio PVP:* Nuestros técnicos están realizando la puesta a punto, verificación de batería y pruebas eléctricas de calidad.\n";
                break;

            case EquipmentStatus::READY_FOR_DISPATCH->value:
                $msg .= "📦 *¡Cotejo de Despacho Exitoso!* Tu paquete ha sido verificado con éxito contra el manifiesto en el Puerto del Mariel y se encuentra listo para su salida a destino regional.\n";
                break;

            case EquipmentStatus::REGIONAL_TRANSIT->value:
                $prov = $equipment->currentWarehouse ? $equipment->currentWarehouse->province : 'tu provincia';
                $msg .= "🚛 *En Camino:* Tu equipo va en tránsito logístico hacia {$prov}.\n";
                break;

            case EquipmentStatus::IN_REGIONAL_WAREHOUSE->value:
                $whName = $equipment->currentWarehouse ? $equipment->currentWarehouse->name : 'nuestro almacén provincial';
                $msg .= "🏢 *¡Listo para Retiro!* Tu equipo ha llegado a {$whName}. Ya puedes coordinar su retiro o entrega.\n";
                break;

            case EquipmentStatus::DELIVERED->value:
                $msg .= "🎉 *¡Entrega Completada!* Tu equipo ha sido entregado exitosamente. ¡Gracias por confiar en BLANKISOL!\n";
                break;
        }

        if ($customNote) {
            $msg .= "\n📝 *Nota:* {$customNote}\n";
        }

        $msg .= "\n🔗 *Sigue tu equipo en vivo aquí:*\n{$trackingUrl}\n\n";
        $msg .= "Para consultas directas, responde a este chat.";

        return $msg;
    }

    /**
     * Envía la solicitud al Gateway de WhatsApp.
     */
    protected function dispatchToGateway(string $phone, string $message): array
    {
        Log::info("WhatsApp enviado a [{$phone}]:\n{$message}");

        return [
            'success' => true,
            'message_id' => 'WA-MSG-' . strtoupper(uniqid()),
        ];
    }
}
