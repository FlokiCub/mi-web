<?php

namespace App\Enums;

enum EquipmentType: string
{
    case VEHICLE = 'vehicle';
    case SOLAR_SYSTEM = 'solar_system';
    case BATTERY_PACK = 'battery_pack';
    case ACCESSORY = 'accessory';

    public function label(): string
    {
        return match($this) {
            self::VEHICLE => 'Vehículo Eléctrico',
            self::SOLAR_SYSTEM => 'Sistema Solar Fotovoltaico',
            self::BATTERY_PACK => 'Banco de Baterías',
            self::ACCESSORY => 'Accesorio / Componente',
        };
    }
}
