<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\RegionalDispatchRoute;
use App\Models\RegionalWarehouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RegionalLogisticsService
{
    public function __construct(
        protected WhatsAppNotificationService $whatsAppService
    ) {}

    /**
     * Crea una nueva Hoja de Ruta de traslado regional y le asocia los equipos listos para despacho.
     *
     * @param array $data Datos de la ruta (driver_name, driver_phone, truck_license_plate, destination_warehouse_id, notes)
     * @param array $equipmentIds Lista de UUIDs de equipos en READY_FOR_DISPATCH
     * @param User $creator Usuario que crea la hoja de ruta
     * @return RegionalDispatchRoute
     */
    public function createRoute(array $data, array $equipmentIds, User $creator): RegionalDispatchRoute
    {
        return DB::transaction(function () use ($data, $equipmentIds, $creator) {
            $destination = RegionalWarehouse::findOrFail($data['destination_warehouse_id']);
            $originWarehouseId = $data['origin_warehouse_id'] ?? RegionalWarehouse::where('code', 'WH-MARIEL')->value('id') ?? RegionalWarehouse::first()?->id;

            // Generar código único de hoja de ruta si no viene especificado
            $routeCode = $data['route_code'] ?? 'HR-' . strtoupper(substr($destination->province, 0, 3)) . '-' . date('Ymd') . '-' . strtoupper(Str::random(4));

            $route = RegionalDispatchRoute::create([
                'route_code' => $routeCode,
                'origin_warehouse_id' => $originWarehouseId,
                'destination_warehouse_id' => $destination->id,
                'driver_name' => $data['driver_name'],
                'driver_phone' => $data['driver_phone'] ?? null,
                'truck_license_plate' => strtoupper($data['truck_license_plate']),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $creator->id,
            ]);

            if (!empty($equipmentIds)) {
                $validEquipments = Equipment::whereIn('id', $equipmentIds)
                    ->where('current_status', EquipmentStatus::READY_FOR_DISPATCH)
                    ->get();

                foreach ($validEquipments as $eq) {
                    $route->equipments()->attach($eq->id, [
                        'reception_status' => 'assigned',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $route->fresh(['originWarehouse', 'destinationWarehouse', 'equipments']);
        });
    }

    /**
     * Agrega un equipo a una hoja de ruta en estado borrador mediante escaneo de código/PIN.
     */
    public function addEquipmentToRoute(RegionalDispatchRoute $route, string $barcodeOrPin): array
    {
        if (!$route->isDraft()) {
            throw new InvalidArgumentException("Solo se pueden agregar bultos a hojas de ruta en estado borrador.");
        }

        $code = strtoupper(trim($barcodeOrPin));
        $equipment = Equipment::where('tracking_pin', $code)
            ->orWhere('vin_serial', $code)
            ->orWhere('solve_cargo_tracking_id', $code)
            ->first();

        if (!$equipment) {
            throw new InvalidArgumentException("No se encontró ningún bulto con el código \"{$code}\".");
        }

        if ($equipment->current_status !== EquipmentStatus::READY_FOR_DISPATCH) {
            throw new InvalidArgumentException("El equipo {$equipment->tracking_pin} está en estado \"{$equipment->current_status->label()}\". Debe estar en \"Listo para Despacho\" para cargarse en ruta.");
        }

        if ($route->equipments()->where('equipment_id', $equipment->id)->exists()) {
            throw new InvalidArgumentException("El equipo {$equipment->tracking_pin} ya está incluido en esta hoja de ruta.");
        }

        $route->equipments()->attach($equipment->id, [
            'reception_status' => 'assigned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'success' => true,
            'equipment' => $equipment,
            'message' => "Bulto {$equipment->brand} {$equipment->model} (PIN: {$equipment->tracking_pin}) cargado en Hoja de Ruta #{$route->route_code}.",
        ];
    }

    /**
     * Remueve un equipo de una hoja de ruta en borrador.
     */
    public function removeEquipmentFromRoute(RegionalDispatchRoute $route, Equipment $equipment): bool
    {
        if (!$route->isDraft()) {
            throw new InvalidArgumentException("Solo se pueden remover bultos de hojas de ruta en estado borrador.");
        }

        $route->equipments()->detach($equipment->id);
        return true;
    }

    /**
     * Despacha el convoy / camión con la hoja de ruta hacia su provincia de destino.
     * Transiciona todos los equipos a REGIONAL_TRANSIT y dispara WhatsApp.
     */
    public function dispatchRoute(RegionalDispatchRoute $route, ?User $operator = null): RegionalDispatchRoute
    {
        if ($route->equipments()->count() === 0) {
            throw new InvalidArgumentException("No se puede despachar una hoja de ruta vacía. Asigna al menos 1 bulto.");
        }

        return DB::transaction(function () use ($route, $operator) {
            $route->update([
                'status' => 'in_transit',
                'dispatched_at' => now(),
            ]);

            $destination = $route->destinationWarehouse;

            foreach ($route->equipments as $equipment) {
                $fromStatus = $equipment->current_status;

                $equipment->update([
                    'current_status' => EquipmentStatus::REGIONAL_TRANSIT,
                    'current_location_note' => "En Tránsito Ruta {$route->route_code} (Camión: {$route->truck_license_plate}) hacia {$destination->name}",
                ]);

                // Actualizar pivot
                $route->equipments()->updateExistingPivot($equipment->id, [
                    'reception_status' => 'in_transit',
                    'updated_at' => now(),
                ]);

                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'from_status' => is_object($fromStatus) ? $fromStatus->value : $fromStatus,
                    'to_status' => EquipmentStatus::REGIONAL_TRANSIT->value,
                    'location' => "Ruta de Distribución (Camión: {$route->truck_license_plate})",
                    'notes' => "Despachado en Hoja de Ruta #{$route->route_code} con destino a {$destination->province}. Chofer: {$route->driver_name}.",
                    'changed_by_user_id' => $operator?->id,
                    'recorded_at' => now(),
                ]);

                // Notificación WhatsApp de salida en ruta
                try {
                    $this->whatsAppService->sendStatusUpdateNotification($equipment);
                } catch (\Throwable $e) {
                    Log::error("Error enviando WhatsApp en despacho de ruta para PIN {$equipment->tracking_pin}: " . $e->getMessage());
                }
            }

            return $route->fresh(['originWarehouse', 'destinationWarehouse', 'equipments']);
        });
    }

    /**
     * Confirma la recepción del convoy en el almacén regional de destino.
     * Transiciona todos los equipos recibidos a IN_REGIONAL_WAREHOUSE y actualiza almacén actual.
     */
    public function receiveRouteAtDestination(
        RegionalDispatchRoute $route,
        ?User $regionalReceiver = null,
        array $verifiedEquipmentIds = []
    ): RegionalDispatchRoute {
        return DB::transaction(function () use ($route, $regionalReceiver, $verifiedEquipmentIds) {
            $route->update([
                'status' => 'arrived_destination',
                'arrived_at' => now(),
            ]);

            $destination = $route->destinationWarehouse;
            $equipments = $route->equipments;

            foreach ($equipments as $equipment) {
                // Si se especificó una lista de bultos verificados, solo procesar esos o todos si está vacío
                if (!empty($verifiedEquipmentIds) && !in_array($equipment->id, $verifiedEquipmentIds, true)) {
                    continue;
                }

                $fromStatus = $equipment->current_status;

                $equipment->update([
                    'current_status' => EquipmentStatus::IN_REGIONAL_WAREHOUSE,
                    'current_warehouse_id' => $destination->id,
                    'current_location_note' => "Almacén Regional: {$destination->name} ({$destination->province})",
                ]);

                $route->equipments()->updateExistingPivot($equipment->id, [
                    'reception_status' => 'received',
                    'received_at' => now(),
                    'updated_at' => now(),
                ]);

                EquipmentStatusHistory::create([
                    'equipment_id' => $equipment->id,
                    'from_status' => is_object($fromStatus) ? $fromStatus->value : $fromStatus,
                    'to_status' => EquipmentStatus::IN_REGIONAL_WAREHOUSE->value,
                    'location' => $destination->name,
                    'notes' => "Recepción confirmada en sucursal regional ({$destination->province}). Disponible para entrega o liquidación en ventanilla.",
                    'changed_by_user_id' => $regionalReceiver?->id,
                    'recorded_at' => now(),
                ]);

                // Notificación WhatsApp de arribo a almacén de destino
                try {
                    $this->whatsAppService->sendStatusUpdateNotification($equipment);
                } catch (\Throwable $e) {
                    Log::error("Error enviando WhatsApp en recepción regional para PIN {$equipment->tracking_pin}: " . $e->getMessage());
                }
            }

            return $route->fresh(['originWarehouse', 'destinationWarehouse', 'equipments']);
        });
    }

    /**
     * Confirma la recepción individual de un paquete en el almacén de destino por escaneo.
     */
    public function receiveSingleEquipmentAtDestination(
        RegionalDispatchRoute $route,
        string $barcodeOrPin,
        ?User $regionalReceiver = null
    ): array {
        $code = strtoupper(trim($barcodeOrPin));
        $equipment = $route->equipments()
            ->where(function ($q) use ($code) {
                $q->where('tracking_pin', $code)
                  ->orWhere('vin_serial', $code)
                  ->orWhere('solve_cargo_tracking_id', $code);
            })->first();

        if (!$equipment) {
            throw new InvalidArgumentException("El bulto \"{$code}\" no pertenece a la Hoja de Ruta #{$route->route_code}.");
        }

        return DB::transaction(function () use ($route, $equipment, $regionalReceiver) {
            $destination = $route->destinationWarehouse;
            $fromStatus = $equipment->current_status;

            $equipment->update([
                'current_status' => EquipmentStatus::IN_REGIONAL_WAREHOUSE,
                'current_warehouse_id' => $destination->id,
                'current_location_note' => "Almacén Regional: {$destination->name} ({$destination->province})",
            ]);

            $route->equipments()->updateExistingPivot($equipment->id, [
                'reception_status' => 'received',
                'received_at' => now(),
                'updated_at' => now(),
            ]);

            EquipmentStatusHistory::create([
                'equipment_id' => $equipment->id,
                'from_status' => is_object($fromStatus) ? $fromStatus->value : $fromStatus,
                'to_status' => EquipmentStatus::IN_REGIONAL_WAREHOUSE->value,
                'location' => $destination->name,
                'notes' => "Bulto escaneado y recibido individualmente en {$destination->name}.",
                'changed_by_user_id' => $regionalReceiver?->id,
                'recorded_at' => now(),
            ]);

            try {
                $this->whatsAppService->sendStatusUpdateNotification($equipment);
            } catch (\Throwable $e) {
                Log::error("Error enviando WhatsApp: " . $e->getMessage());
            }

            return [
                'success' => true,
                'equipment' => $equipment->fresh(),
                'message' => "✓ Bulto {$equipment->tracking_pin} recibido con éxito en {$destination->name}.",
            ];
        });
    }
}
