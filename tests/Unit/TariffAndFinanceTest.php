<?php

namespace Tests\Unit;

use App\Enums\BusinessType;
use App\Models\ExchangeRate;
use App\Services\TariffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TariffAndFinanceTest extends TestCase
{
    use RefreshDatabase;

    private TariffService $tariffService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tariffService = app(TariffService::class);

        ExchangeRate::create([
            'currency_from' => 'USD',
            'currency_to' => 'CUP',
            'rate' => 350.00,
            'is_active' => true,
        ]);
    }

    public function test_b2c_not_prepaid_charges_10_percent_duty_and_50_delivery(): void
    {
        $calc = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 2000.00,
            businessType: BusinessType::B2C,
            dutyPaidAbroad: false,
            deliveryPaidAbroad: false,
            homeDeliveryRequested: true
        );

        $this->assertEquals(200.00, $calc['duty_amount_usd']);
        $this->assertEquals(50.00, $calc['shipping_fee_usd']);
        $this->assertEquals(250.00, $calc['total_to_collect_usd']);
        $this->assertEquals(87500.00, $calc['total_to_collect_cup']);
        $this->assertFalse($calc['is_totally_prepaid']);
    }

    public function test_equipment_fully_prepaid_abroad_charges_zero_in_cuba(): void
    {
        $calc = $this->tariffService->calculateLiquidation(
            declaredValueUsd: 5000.00,
            businessType: BusinessType::B2C,
            dutyPaidAbroad: true,
            deliveryPaidAbroad: true,
            homeDeliveryRequested: true
        );

        $this->assertEquals(0.00, $calc['duty_amount_usd']);
        $this->assertEquals(0.00, $calc['shipping_fee_usd']);
        $this->assertEquals(0.00, $calc['total_to_collect_usd']);
        $this->assertEquals(0.00, $calc['total_to_collect_cup']);
        $this->assertTrue($calc['is_totally_prepaid']);
    }
}
