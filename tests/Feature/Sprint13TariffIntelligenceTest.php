<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\EquipmentStatus;
use App\Enums\EquipmentType;
use App\Models\Equipment;
use App\Models\ExchangeRate;
use App\Services\TariffService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint13TariffIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private TariffService $tariffService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->tariffService = app(TariffService::class);

        ExchangeRate::updateOrCreate(
            ['currency_from' => 'USD', 'currency_to' => 'CUP'],
            ['rate' => 350.00, 'is_active' => true]
        );
    }

    /**
     * Valida el cálculo arancelario B2C estándar (10% + $50 USD domicilio no prepago).
     */
    public function test_b2c_standard_calculation_with_home_delivery(): void
    {
        $result = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 1500.00,
            businessType: BusinessType::B2C,
            dutyPaidAbroad: false,
            deliveryPaidAbroad: false,
            homeDeliveryRequested: true
        );

        $this->assertEquals(150.00, $result['duty_amount_usd']);
        $this->assertEquals(50.00, $result['shipping_fee_usd']);
        $this->assertEquals(200.00, $result['total_to_collect_usd']);
        $this->assertEquals(70000.00, $result['total_to_collect_cup']); // 200 * 350 CUP
        $this->assertFalse($result['is_totally_prepaid']);
        $this->assertEquals('b2c_standard', $result['duty_exemption_reason']);
    }

    /**
     * Valida B2C con flete a domicilio prepagado en el exterior.
     */
    public function test_b2c_with_delivery_paid_abroad(): void
    {
        $result = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 2000.00,
            businessType: BusinessType::B2C,
            dutyPaidAbroad: false,
            deliveryPaidAbroad: true,
            homeDeliveryRequested: true
        );

        $this->assertEquals(200.00, $result['duty_amount_usd']);
        $this->assertEquals(0.00, $result['shipping_fee_usd']);
        $this->assertEquals(200.00, $result['total_to_collect_usd']);
        $this->assertFalse($result['is_totally_prepaid']);
    }

    /**
     * Valida producto 100% prepagado en el exterior ($0.00 a cobrar en Cuba).
     */
    public function test_equipment_fully_prepaid_abroad_is_completely_exempt_in_cuba(): void
    {
        $result = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 3500.00,
            businessType: BusinessType::B2C,
            dutyPaidAbroad: true,
            deliveryPaidAbroad: true,
            homeDeliveryRequested: true
        );

        $this->assertEquals(0.00, $result['duty_amount_usd']);
        $this->assertEquals(0.00, $result['shipping_fee_usd']);
        $this->assertEquals(0.00, $result['total_to_collect_usd']);
        $this->assertEquals(0.00, $result['total_to_collect_cup']);
        $this->assertTrue($result['is_totally_prepaid']);
        $this->assertEquals('prepaid_abroad', $result['duty_exemption_reason']);
    }

    /**
     * Valida B2B con documentación/XML de exención aduanal válida (0% arancel).
     */
    public function test_b2b_with_valid_customs_exemption_sets_zero_duty(): void
    {
        $validExemption = [
            'customs_declaration_number' => 'DEC-2026-CU-99882',
            'exemption_certificate_number' => 'MINCOM-EX-7761',
            'exemption_date' => Carbon::now()->subMonths(2)->toDateString(),
            'tariff_heading' => '8541.40.00', // Paneles fotovoltaicos
            'exemption_basis' => 'Resolución Ministerial 12/2024 - Incentivo Energía Renovable B2B',
        ];

        $result = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 10000.00,
            businessType: BusinessType::B2B,
            dutyPaidAbroad: false,
            deliveryPaidAbroad: false,
            homeDeliveryRequested: false,
            customsExemptionData: $validExemption
        );

        $this->assertEquals(0.00, $result['duty_amount_usd']);
        $this->assertEquals(0.00, $result['shipping_fee_usd']);
        $this->assertEquals(0.00, $result['total_to_collect_usd']);
        $this->assertTrue($result['is_totally_prepaid']);
        $this->assertEquals('b2b_exemption_xml', $result['duty_exemption_reason']);
    }

    /**
     * Valida B2B SIN exención válida o con certificado vencido (aplica fallback 10% B2C).
     */
    public function test_b2b_with_expired_or_missing_exemption_falls_back_to_10_percent(): void
    {
        // 1. Sin datos de exención
        $resultNoData = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 5000.00,
            businessType: BusinessType::B2B,
            dutyPaidAbroad: false,
            deliveryPaidAbroad: false,
            homeDeliveryRequested: false,
            customsExemptionData: null
        );

        $this->assertEquals(500.00, $resultNoData['duty_amount_usd']);
        $this->assertEquals('b2b_no_exemption_fallback', $resultNoData['duty_exemption_reason']);

        // 2. Con certificado vencido (+1 año de antigüedad)
        $expiredExemption = [
            'customs_declaration_number' => 'DEC-2024-OLD',
            'exemption_certificate_number' => 'MINCOM-OLD-11',
            'exemption_date' => Carbon::now()->subMonths(18)->toDateString(),
            'tariff_heading' => '8471.30.00',
            'exemption_basis' => 'Ley de Inversiones',
        ];

        $resultExpired = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 4000.00,
            businessType: BusinessType::B2B,
            dutyPaidAbroad: false,
            deliveryPaidAbroad: false,
            homeDeliveryRequested: false,
            customsExemptionData: $expiredExemption
        );

        $this->assertEquals(400.00, $resultExpired['duty_amount_usd']);
        $this->assertEquals('b2b_no_exemption_fallback', $resultExpired['duty_exemption_reason']);
    }

    /**
     * Valida el parseo real de un archivo XML de Manifiesto Aduanal SolveCargo.
     */
    public function test_customs_manifest_xml_parsing(): void
    {
        $xmlSample = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<CustomsManifest>
    <DeclarationNumber>ADU-MARIEL-2026-8831</DeclarationNumber>
    <ExemptionCertificate>CERT-ENERGIA-SOLAR-009</ExemptionCertificate>
    <ExemptionDate>2026-08-15</ExemptionDate>
    <TariffHeading>8541.40.10</TariffHeading>
    <ExemptionBasis>Decreto Ley 345 Energias Renovables</ExemptionBasis>
    <TotalValueUSD>12500.00</TotalValueUSD>
    <Items>
        <Item>
            <Description>Inversor Híbrido 5kW</Description>
            <Quantity>2</Quantity>
            <ValueUSD>3500.00</ValueUSD>
            <TariffHeading>8504.40.00</TariffHeading>
        </Item>
        <Item>
            <Description>Batería Litio 48V 100Ah</Description>
            <Quantity>4</Quantity>
            <ValueUSD>9000.00</ValueUSD>
            <TariffHeading>8507.60.00</TariffHeading>
        </Item>
    </Items>
</CustomsManifest>
XML;

        $parsed = $this->tariffService->parseCustomsXmlForExemption($xmlSample);

        $this->assertEquals('ADU-MARIEL-2026-8831', $parsed['customs_declaration_number']);
        $this->assertEquals('CERT-ENERGIA-SOLAR-009', $parsed['exemption_certificate_number']);
        $this->assertEquals('2026-08-15', $parsed['exemption_date']);
        $this->assertEquals('8541.40.10', $parsed['tariff_heading']);
        $this->assertEquals(12500.00, $parsed['total_value_usd']);
        $this->assertCount(2, $parsed['items']);

        // Ahora evaluamos la exención con los datos parseados
        $liquidation = $this->tariffService->calculateLiquidation(
            declaredValueUsd: $parsed['total_value_usd'],
            businessType: BusinessType::B2B,
            dutyPaidAbroad: false,
            customsExemptionData: $parsed
        );

        $this->assertEquals(0.00, $liquidation['duty_amount_usd']);
        $this->assertEquals('b2b_exemption_xml', $liquidation['duty_exemption_reason']);
    }

    /**
     * Valida el método calculateFromEquipment directamente con el modelo Eloquent.
     */
    public function test_calculate_from_equipment_model(): void
    {
        $equipment = Equipment::create([
            'tracking_pin' => 'BK-MODEL01',
            'vin_serial' => 'VIN-MODEL-TEST-001',
            'equipment_type' => EquipmentType::VEHICLE,
            'business_type' => BusinessType::B2C,
            'brand' => 'Yadea',
            'model' => 'E8S Pro',
            'declared_value_usd' => 1800.00,
            'duty_paid_abroad' => false,
            'delivery_paid_abroad' => false,
            'home_delivery_requested' => true,
            'current_status' => EquipmentStatus::PORT_ARRIVAL,
        ]);

        $result = $this->tariffService->calculateFromEquipment($equipment);

        $this->assertEquals(180.00, $result['duty_amount_usd']);
        $this->assertEquals(50.00, $result['shipping_fee_usd']);
        $this->assertEquals(230.00, $result['total_to_collect_usd']);
        $this->assertEquals(80500.00, $result['total_to_collect_cup']);
    }
}
