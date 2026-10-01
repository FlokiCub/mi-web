<?php

namespace App\Services;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\RegionalWarehouse;
use App\Models\WhatsAppNotification;
use Carbon\Carbon;

class DirectorDashboardService
{
    /**
     * Genera el conjunto completo de métricas ejecutivas para la Dirección.
     */
    public function getExecutiveMetrics(): array
    {
        $now = Carbon::now();

        // 1. Pipeline de Equipos por Estado Operativo
        $totalEquipments = Equipment::count();
        $inMaritimeTransit = Equipment::where('current_status', EquipmentStatus::IN_TRANSIT_SOLVE_CARGO)->count();
        $inCustoms = Equipment::whereIn('current_status', [EquipmentStatus::PORT_ARRIVAL, EquipmentStatus::CUSTOMS_INSPECTION])->count();
        $inPvp = Equipment::where('current_status', EquipmentStatus::IN_PVP_ASSEMBLY)->count();
        $readyForDispatch = Equipment::where('current_status', EquipmentStatus::READY_FOR_DISPATCH)->count();
        $inRegionalTransit = Equipment::where('current_status', EquipmentStatus::REGIONAL_TRANSIT)->count();
        $inRegionalWarehouses = Equipment::where('current_status', EquipmentStatus::IN_REGIONAL_WAREHOUSE)->count();
        $delivered = Equipment::where('current_status', EquipmentStatus::DELIVERED)->count();

        // 2. Alertas Críticas de Permanencia en Aduana / Puerto (Semáforo 48h / 72h)
        $customsDelayed48h = Equipment::customsDelayed(48)->get([
            'id', 'tracking_pin', 'vin_serial', 'brand', 'model', 'current_status', 'customs_entry_at', 'updated_at'
        ]);
        $customsCritical72h = Equipment::customsCritical(72)->get([
            'id', 'tracking_pin', 'vin_serial', 'brand', 'model', 'current_status', 'customs_entry_at', 'updated_at'
        ]);

        // 3. Métricas Financieras (Prepagado vs Recaudación en Cuba)
        $totalDeclaredValueUsd = (float) Equipment::sum('declared_value_usd');
        $prepaidAbroadCount = Equipment::where('duty_paid_abroad', true)->count();
        $pendingCollectionUsd = (float) Equipment::where('duty_paid_abroad', false)->sum('duty_amount_usd');
        $homeDeliveryCount = Equipment::where('home_delivery_requested', true)->count();

        // 4. Inventario y Custodia por Almacén Regional
        $warehousesInventory = RegionalWarehouse::withCount('equipments')
            ->get()
            ->map(function ($wh) {
                return [
                    'name' => $wh->name,
                    'province' => $wh->province,
                    'count' => $wh->equipments_count,
                ];
            });

        // 5. Estadísticas de Notificaciones WhatsApp
        $totalWaSent = WhatsAppNotification::where('status', 'sent')->count();
        $totalWaFailed = WhatsAppNotification::where('status', 'failed')->count();

        return [
            'summary' => [
                'total_equipments' => $totalEquipments,
                'active_in_pipeline' => $totalEquipments - $delivered,
                'delivered_count' => $delivered,
                'total_declared_usd' => $totalDeclaredValueUsd,
                'prepaid_abroad_count' => $prepaidAbroadCount,
                'pending_collection_usd' => $pendingCollectionUsd,
                'home_delivery_count' => $homeDeliveryCount,
                'wa_sent_count' => $totalWaSent,
                'wa_failed_count' => $totalWaFailed,
            ],
            'pipeline' => [
                'maritime_transit' => $inMaritimeTransit,
                'customs' => $inCustoms,
                'pvp_assembly' => $inPvp,
                'ready_dispatch' => $readyForDispatch,
                'regional_transit' => $inRegionalTransit,
                'regional_warehouses' => $inRegionalWarehouses,
                'delivered' => $delivered,
            ],
            'customs_alerts' => [
                'count' => $customsDelayed48h->count(),
                'warning_48h_count' => $customsDelayed48h->count(),
                'critical_72h_count' => $customsCritical72h->count(),
                'items' => $customsDelayed48h,
                'critical_items' => $customsCritical72h,
            ],
            'regional_distribution' => $warehousesInventory,
        ];
    }
}
