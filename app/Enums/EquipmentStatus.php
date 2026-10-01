<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case SUPPLIER_DISPATCHED = 'supplier_dispatched';
    case IN_TRANSIT_SOLVE_CARGO = 'in_transit_solve_cargo';
    case PORT_ARRIVAL = 'port_arrival';
    case CUSTOMS_INSPECTION = 'customs_inspection';
    case CUSTOMS_CLEARED = 'customs_cleared';
    case IN_PVP_ASSEMBLY = 'in_pvp_assembly';
    case READY_FOR_DISPATCH = 'ready_for_dispatch';
    case REGIONAL_TRANSIT = 'regional_transit';
    case IN_REGIONAL_WAREHOUSE = 'in_regional_warehouse';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';

    public function label(): string
    {
        return match($this) {
            self::SUPPLIER_DISPATCHED => 'Despachado por Proveedor',
            self::IN_TRANSIT_SOLVE_CARGO => 'En Tránsito Internacional (SolveCargo)',
            self::PORT_ARRIVAL => 'Arribo a Puerto / Aduana',
            self::CUSTOMS_INSPECTION => 'En Inspección Aduanal',
            self::CUSTOMS_CLEARED => 'Liberado de Aduana',
            self::IN_PVP_ASSEMBLY => 'En Patio / Ensamblaje PVP',
            self::READY_FOR_DISPATCH => 'Listo para Despacho Regional',
            self::REGIONAL_TRANSIT => 'En Tránsito a Sucursal Regional',
            self::IN_REGIONAL_WAREHOUSE => 'En Almacén Regional',
            self::OUT_FOR_DELIVERY => 'En Ruta de Entrega Final',
            self::DELIVERED => 'Entregado a Cliente',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::SUPPLIER_DISPATCHED, self::IN_TRANSIT_SOLVE_CARGO => 'info',
            self::PORT_ARRIVAL, self::CUSTOMS_INSPECTION => 'warning',
            self::CUSTOMS_CLEARED, self::IN_PVP_ASSEMBLY, self::READY_FOR_DISPATCH => 'primary',
            self::REGIONAL_TRANSIT, self::IN_REGIONAL_WAREHOUSE, self::OUT_FOR_DELIVERY => 'secondary',
            self::DELIVERED => 'success',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::SUPPLIER_DISPATCHED, self::IN_TRANSIT_SOLVE_CARGO => 'bg-sky-500/10 text-sky-400 border border-sky-500/20',
            self::PORT_ARRIVAL, self::CUSTOMS_INSPECTION => 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
            self::CUSTOMS_CLEARED, self::IN_PVP_ASSEMBLY => 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20',
            self::READY_FOR_DISPATCH, self::REGIONAL_TRANSIT, self::IN_REGIONAL_WAREHOUSE => 'bg-purple-500/10 text-purple-400 border border-purple-500/20',
            self::OUT_FOR_DELIVERY => 'bg-blue-500/10 text-blue-400 border border-blue-500/20',
            self::DELIVERED => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
        };
    }
}
