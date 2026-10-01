<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Enums\UserRole;
use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\ExchangeRate;
use App\Models\PvpInspection;
use App\Models\RegionalDispatchRoute;
use App\Models\RegionalWarehouse;
use App\Models\User;
use App\Models\WhatsAppNotification;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ==========================================
        // 1. Almacenes Regionales
        // ==========================================
        $warehouseHabana = RegionalWarehouse::updateOrCreate(
            ['code' => 'HAB-01'],
            [
                'name' => 'Centro de Distribución Central La Habana',
                'province' => 'La Habana',
                'address' => 'Av. Independencia km 4.5, Boyeros',
                'contact_phone' => '+53 7 888 1234',
                'is_active' => true,
            ]
        );

        $warehouseSantiago = RegionalWarehouse::updateOrCreate(
            ['code' => 'STG-01'],
            [
                'name' => 'Almacén Regional Oriente Santiago',
                'province' => 'Santiago de Cuba',
                'address' => 'Carretera Central km 2',
                'contact_phone' => '+53 22 654 321',
                'is_active' => true,
            ]
        );

        $warehouseHolguin = RegionalWarehouse::updateOrCreate(
            ['code' => 'HLG-01'],
            [
                'name' => 'Almacén Regional Holguín',
                'province' => 'Holguín',
                'address' => 'Zona Industrial Holguín',
                'contact_phone' => '+53 24 456 789',
                'is_active' => true,
            ]
        );

        // ==========================================
        // 1.1 Cajas Regionales por Sucursal
        // ==========================================
        \App\Models\CashRegister::updateOrCreate(
            ['code' => 'CAJA-HAB-01'],
            [
                'regional_warehouse_id' => $warehouseHabana->id,
                'name' => 'Caja Principal Ventanilla Habana',
                'status' => 'closed',
                'is_active' => true,
            ]
        );

        \App\Models\CashRegister::updateOrCreate(
            ['code' => 'CAJA-STG-01'],
            [
                'regional_warehouse_id' => $warehouseSantiago->id,
                'name' => 'Caja Principal Santiago de Cuba',
                'status' => 'closed',
                'is_active' => true,
            ]
        );

        \App\Models\CashRegister::updateOrCreate(
            ['code' => 'CAJA-HLG-01'],
            [
                'regional_warehouse_id' => $warehouseHolguin->id,
                'name' => 'Caja Ventanilla Holguín',
                'status' => 'closed',
                'is_active' => true,
            ]
        );

        // ==========================================
        // 2. Usuarios con los 7 Perfiles RBAC
        // ==========================================
        $password = Hash::make('Password123!');

        $users = [
            [
                'name' => 'Director General',
                'username' => 'director',
                'email' => 'director@scgi.cu',
                'role' => UserRole::DIRECTOR,
                'phone' => '+53 5 111 0001',
                'regional_warehouse_id' => null,
            ],
            [
                'name' => 'Inspector Carlos Aduana',
                'username' => 'aduana_carlos',
                'email' => 'aduana@scgi.cu',
                'role' => UserRole::ADUANA,
                'phone' => '+53 5 111 0002',
                'regional_warehouse_id' => null,
            ],
            [
                'name' => 'Ing. Pedro PVP Técnico',
                'username' => 'tecnico_pedro',
                'email' => 'pvp@scgi.cu',
                'role' => UserRole::PVP_TECNICO,
                'phone' => '+53 5 111 0003',
                'regional_warehouse_id' => null,
            ],
            [
                'name' => 'Operador Ramón Despacho',
                'username' => 'despacho_ramon',
                'email' => 'despacho@scgi.cu',
                'role' => UserRole::DESPACHO_PUERTO,
                'phone' => '+53 5 111 0004',
                'regional_warehouse_id' => null,
            ],
            [
                'name' => 'Chofer Manuel Logística',
                'username' => 'chofer_manuel',
                'email' => 'chofer@scgi.cu',
                'role' => UserRole::LOGISTICA_CHOFER,
                'phone' => '+53 5 111 0005',
                'regional_warehouse_id' => null,
            ],
            [
                'name' => 'Cajera Ana Habana',
                'username' => 'cajera_ana',
                'email' => 'cajero.habana@scgi.cu',
                'role' => UserRole::CAJERO_REGIONAL,
                'phone' => '+53 5 111 0006',
                'regional_warehouse_id' => $warehouseHabana->id,
            ],
            [
                'name' => 'Auditor Financiero Roberto',
                'username' => 'auditor_roberto',
                'email' => 'auditor@scgi.cu',
                'role' => UserRole::AUDITOR,
                'phone' => '+53 5 111 0007',
                'regional_warehouse_id' => null,
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'password' => $password,
                    'role' => $userData['role'],
                    'phone' => $userData['phone'],
                    'regional_warehouse_id' => $userData['regional_warehouse_id'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
        }

        $directorUser = User::where('email', 'director@scgi.cu')->first();
        $pvpUser = User::where('email', 'pvp@scgi.cu')->first();

        // ==========================================
        // 3. Tasa de Cambio Activa
        // ==========================================
        ExchangeRate::updateOrCreate(
            ['currency_from' => 'USD', 'currency_to' => 'CUP'],
            [
                'rate' => 350.0000,
                'is_active' => true,
                'set_by_user_id' => $directorUser?->id,
            ]
        );

        // ==========================================
        // 4. Equipos de Demostración y Trazabilidad
        // ==========================================

        // Equipo 1: Moto Eléctrica B2C en Patio PVP
        $eq1 = Equipment::updateOrCreate(
            ['tracking_pin' => 'BK-78492'],
            [
                'vin_serial' => 'VIN-2026-YADEA-G5-99881',
                'solve_cargo_tracking_id' => 'SC-2026-MIA-00921',
                'equipment_type' => EquipmentType::VEHICLE,
                'business_type' => BusinessType::B2C,
                'brand' => 'Yadea',
                'model' => 'G5 Pro Lithium',
                'color' => 'Gris Titanio',
                'current_status' => EquipmentStatus::IN_PVP_ASSEMBLY,
                'customs_status' => 'duty_pending_payment',
                'declared_value_usd' => 2450.00,
                'duty_amount_usd' => 245.00,
                'shipping_fee_usd' => 50.00,
                'home_delivery_requested' => true,
                'duty_paid_abroad' => false,
                'delivery_paid_abroad' => false,
                'customs_entry_at' => Carbon::now()->subDays(2),
                'customs_cleared_at' => Carbon::now()->subDays(1),
                'current_warehouse_id' => $warehouseHabana->id,
                'current_location_note' => 'Patio PVP Central — Bahía de Ensamblaje #3',
                'client_data' => [
                    'name' => 'Carlos Alberto Gómez Rodríguez',
                    'phone' => '+53 5 234 5678',
                    'delivery_address' => 'Calle 23 #456 entre G y H, Vedado, Plaza de la Revolución, La Habana',
                ],
                'technical_specs' => [
                    'motor_power' => '3000W Bosch',
                    'battery_type' => '72V 32Ah Litio NMC',
                    'max_speed' => '65 km/h',
                ],
                'created_by_user_id' => $directorUser?->id,
            ]
        );

        EquipmentStatusHistory::firstOrCreate(
            ['equipment_id' => $eq1->id, 'to_status' => EquipmentStatus::IN_PVP_ASSEMBLY->value],
            [
                'from_status' => EquipmentStatus::CUSTOMS_CLEARED->value,
                'location' => 'Patio PVP Central',
                'notes' => 'Traslado completado desde terminal aduanal hacia patio de ensamblaje.',
                'changed_by_user_id' => $directorUser?->id,
                'recorded_at' => Carbon::now()->subHours(12),
            ]
        );

        // Equipo 2: Sistema Solar Fotovoltaico B2B (100% Prepagado)
        $eq2 = Equipment::updateOrCreate(
            ['tracking_pin' => 'BK-10293'],
            [
                'vin_serial' => 'SOLAR-HUA-10KW-48192',
                'solve_cargo_tracking_id' => 'SC-2026-MIA-00955',
                'equipment_type' => EquipmentType::SOLAR_SYSTEM,
                'business_type' => BusinessType::B2B,
                'brand' => 'Huawei Solar',
                'model' => 'Smart Energy Center 10kW + LUNA 15kWh',
                'color' => 'Blanco Perla',
                'current_status' => EquipmentStatus::READY_FOR_DISPATCH,
                'customs_status' => 'duty_exempt_prepaid',
                'declared_value_usd' => 8900.00,
                'duty_amount_usd' => 0.00,
                'shipping_fee_usd' => 0.00,
                'home_delivery_requested' => false,
                'duty_paid_abroad' => true,
                'delivery_paid_abroad' => true,
                'customs_entry_at' => Carbon::now()->subDays(4),
                'customs_cleared_at' => Carbon::now()->subDays(2),
                'pvp_certified_at' => Carbon::now()->subHours(6),
                'current_warehouse_id' => $warehouseHabana->id,
                'current_location_note' => 'Área de Despacho Regional — Pallet #14',
                'client_data' => [
                    'name' => 'Empresa Mixta SolCaribe S.A.',
                    'phone' => '+53 5 987 6543',
                    'contact_person' => 'Lic. Roberto Valdés',
                ],
                'technical_specs' => [
                    'inverter_capacity' => '10 kVA Trifásico',
                    'battery_storage' => '15 kWh Litio LFP',
                    'panels_included' => 20,
                ],
                'created_by_user_id' => $directorUser?->id,
            ]
        );

        // Inspección técnica aprobada para el equipo solar
        PvpInspection::firstOrCreate(
            ['equipment_id' => $eq2->id],
            [
                'battery_voltage_verified' => true,
                'electrical_system_tested' => true,
                'accessories_installed' => true,
                'cosmetic_inspection_passed' => true,
                'result_status' => 'passed',
                'battery_tested_voltage' => '54.2V DC Nominal',
                'bom_checklist_details' => [
                    'inverter_firmware_updated' => true,
                    'dc_isolators_tested' => true,
                    'backup_box_connected' => true,
                ],
                'technician_notes' => 'Sistema verificado en banco de prueba. Cero anomalías.',
                'technician_user_id' => $pvpUser?->id,
                'inspected_at' => Carbon::now()->subHours(6),
            ]
        );

        // Equipo 3: Equipo Retenido en Aduana para Alerta Crítica (Más de 50 horas en Puerto)
        $eq3 = Equipment::updateOrCreate(
            ['tracking_pin' => 'BK-55441'],
            [
                'vin_serial' => 'VIN-2026-NIO-ES6-00441',
                'solve_cargo_tracking_id' => 'SC-2026-MIA-00812',
                'equipment_type' => EquipmentType::VEHICLE,
                'business_type' => BusinessType::B2C,
                'brand' => 'NIO',
                'model' => 'ES6 Performance SUV',
                'color' => 'Azul Marino',
                'current_status' => EquipmentStatus::PORT_ARRIVAL,
                'customs_status' => 'customs_hold',
                'declared_value_usd' => 12500.00,
                'duty_amount_usd' => 1250.00,
                'shipping_fee_usd' => 50.00,
                'home_delivery_requested' => true,
                'duty_paid_abroad' => false,
                'delivery_paid_abroad' => false,
                'customs_entry_at' => Carbon::now()->subHours(54), // > 48 horas (Alerta preventiva activa)
                'current_warehouse_id' => null,
                'current_location_note' => 'Terminal de Contenedores Mariel — Muelle 2',
                'client_data' => [
                    'name' => 'Alejandro Morales Cruz',
                    'phone' => '+53 5 333 4455',
                    'delivery_address' => 'Playa, La Habana',
                ],
                'technical_specs' => [
                    'motor' => 'Dual Motor AWD 400kW',
                    'battery' => '100 kWh',
                ],
                'created_by_user_id' => $directorUser?->id,
            ]
        );

        // Equipo 4: Equipo con Alerta Crítica (> 76 horas en Puerto)
        $eq4 = Equipment::updateOrCreate(
            ['tracking_pin' => 'BK-99112'],
            [
                'vin_serial' => 'VIN-2026-BYD-YUAN-11029',
                'solve_cargo_tracking_id' => 'SC-2026-MIA-00778',
                'equipment_type' => EquipmentType::VEHICLE,
                'business_type' => BusinessType::B2C,
                'brand' => 'BYD',
                'model' => 'Yuan Plus EV',
                'color' => 'Blanco Nieve',
                'current_status' => EquipmentStatus::CUSTOMS_INSPECTION,
                'customs_status' => 'customs_hold',
                'declared_value_usd' => 18000.00,
                'duty_amount_usd' => 1800.00,
                'shipping_fee_usd' => 50.00,
                'home_delivery_requested' => true,
                'duty_paid_abroad' => false,
                'delivery_paid_abroad' => false,
                'customs_entry_at' => Carbon::now()->subHours(76), // > 72 horas (Alerta CRÍTICA roja activa)
                'current_warehouse_id' => null,
                'current_location_note' => 'Aduana Puerto Mariel — Área de Aforo Físico',
                'client_data' => [
                    'name' => 'Mayelín Sánchez Díaz',
                    'phone' => '+53 5 777 8899',
                    'delivery_address' => 'Santiago de Cuba',
                ],
                'technical_specs' => [
                    'motor' => 'Permanent Magnet 150kW',
                    'battery' => 'Blade Battery 60.48 kWh',
                ],
                'created_by_user_id' => $directorUser?->id,
            ]
        );
    }
}
