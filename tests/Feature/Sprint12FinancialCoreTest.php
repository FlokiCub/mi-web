<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Equipment;
use App\Models\Payment;
use App\Models\User;
use App\Models\WhatsAppNotification;
use App\Services\Finance\PaymentService;
use App\Services\Security\RbacService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint12FinancialCoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Valida el ciclo de vida de un turno de caja (Apertura y Cierre con arqueo multimoneda).
     */
    public function test_cash_shift_can_be_opened_and_closed_with_balances(): void
    {
        $paymentService = app(PaymentService::class);
        $cajera = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();
        $register = CashRegister::where('code', 'CAJA-HAB-01')->first();

        // 1. Apertura de caja con fondo inicial
        $shift = $paymentService->openCashShift(
            cashRegister: $register,
            cashier: $cajera,
            initialUsd: 150.00,
            initialCup: 25000.00,
            notes: 'Fondo inicial para cambio en ventanilla'
        );

        $this->assertInstanceOf(CashShift::class, $shift);
        $this->assertEquals('open', $shift->status);
        $this->assertEquals(150.00, (float) $shift->opening_balance_usd);
        $this->assertEquals(25000.00, (float) $shift->opening_balance_cup);
        $this->assertTrue($register->fresh()->isOpen());

        // 2. Cierre de caja con arqueo físico
        $closedShift = $paymentService->closeCashShift(
            shift: $shift,
            actualUsdInDrawer: 150.00,
            actualCupInDrawer: 24800.00, // Faltan 200 CUP en gaveta
            notes: 'Cuadre final de turno'
        );

        $this->assertEquals('closed', $closedShift->status);
        $this->assertEquals(0.00, (float) $closedShift->difference_usd);
        $this->assertEquals(-200.00, (float) $closedShift->difference_cup);
        $this->assertTrue($register->fresh()->isClosed());
    }

    /**
     * Valida que un producto 100% PREPAGADO en el exterior esté exento de cobro en Cuba y permita entrega directa.
     */
    public function test_equipment_totally_prepaid_abroad_is_exempt_from_collection_and_can_be_delivered_directly(): void
    {
        $paymentService = app(PaymentService::class);
        $director = User::where('role', UserRole::DIRECTOR->value)->first();
        $eqPrepaid = Equipment::where('tracking_pin', 'BK-10293')->first();

        $this->assertNotNull($eqPrepaid);
        $this->assertTrue($eqPrepaid->isTotallyPrepaidAbroad());
        $this->assertTrue($eqPrepaid->canBeDelivered());
        $this->assertEquals(0.00, $eqPrepaid->getOutstandingBalanceUsd());

        // Verificar elegibilidad
        $eligibility = $paymentService->verifyDeliveryEligibility($eqPrepaid);
        $this->assertTrue($eligibility['can_deliver']);
        $this->assertStringContainsString('100% Prepagado', $eligibility['reason']);

        // Intentar cobrarle a un equipo 100% prepagado debe lanzar excepción de dominio
        $register = CashRegister::first();
        $cajera = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();
        $shift = $paymentService->openCashShift($register, $cajera);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('PREPAGADO desde el exterior');

        $paymentService->processEquipmentPayment(
            equipment: $eqPrepaid,
            cashShift: $shift,
            cashier: $cajera,
            paymentInput: [
                'amount_usd' => 50.00,
                'amount_cup' => 0.00,
                'payment_method' => 'cash_usd',
            ]
        );
    }

    /**
     * Valida que un producto con saldo pendiente BLOQUEE la entrega física hasta que se liquide su deuda.
     */
    public function test_equipment_with_pending_debt_blocks_delivery_until_payment_is_completed(): void
    {
        $paymentService = app(PaymentService::class);
        $rbacService = app(RbacService::class);
        $cajera = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();
        $eqPending = Equipment::where('tracking_pin', 'BK-78492')->first();

        $this->assertNotNull($eqPending);
        $this->assertFalse($eqPending->isTotallyPrepaidAbroad());
        $this->assertFalse($eqPending->is_fully_paid);
        $this->assertFalse($eqPending->canBeDelivered());
        $this->assertGreaterThan(0.00, $eqPending->getOutstandingBalanceUsd());

        // RbacService bloquea la transición de estado a DELIVERED
        $canTransition = $rbacService->canTransitionStatus($cajera, $eqPending, EquipmentStatus::DELIVERED);
        $this->assertFalse($canTransition, 'RbacService debe impedir la transición a DELIVERED si tiene deuda pendiente');

        // PaymentService rechaza la entrega física con excepción de bloqueo financiero
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('BLOQUEO FINANCIERO');

        $paymentService->deliverEquipment($eqPending, $cajera);
    }

    /**
     * Valida el proceso completo de cobro multimoneda (USD + CUP), emisión de recibo y desbloqueo de entrega.
     */
    public function test_payment_processing_settles_debt_multicurrency_and_unlocks_delivery(): void
    {
        $paymentService = app(PaymentService::class);
        $cajera = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();
        $register = CashRegister::where('code', 'CAJA-HAB-01')->first();
        $eqPending = Equipment::where('tracking_pin', 'BK-78492')->first();

        // 1. Abrir turno
        $shift = $paymentService->openCashShift($register, $cajera);

        // Deuda total: $245.00 (arancel) + $50.00 (flete) = $295.00 USD
        $totalDebtUsd = $eqPending->getOutstandingBalanceUsd();
        $this->assertEquals(295.00, $totalDebtUsd);

        // Cliente paga: $95.00 USD en billetes + $200.00 USD equivalentes en CUP
        // Tasa activa: 350 CUP por USD => 200 * 350 = 70,000 CUP
        $paidUsd = 95.00;
        $paidCup = 70000.00; // 70000 / 350 = 200 USD

        $payment = $paymentService->processEquipmentPayment(
            equipment: $eqPending,
            cashShift: $shift,
            cashier: $cajera,
            paymentInput: [
                'amount_usd' => $paidUsd,
                'amount_cup' => $paidCup,
                'payment_method' => 'cash_mixed',
                'client_name' => 'Carlos Alberto Gómez Rodríguez',
                'client_id_card' => '85031409281',
                'client_phone' => '+53 5 234 5678',
                'notes' => 'Pago mixto en efectivo ventanilla',
            ]
        );

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertStringStartsWith('REC-', $payment->receipt_number);
        $this->assertEquals(295.00, (float) $payment->amount_due_usd);
        $this->assertEquals(95.00, (float) $payment->amount_paid_usd);
        $this->assertEquals(70000.00, (float) $payment->amount_paid_cup);
        $this->assertEquals(295.00, (float) $payment->equivalent_total_usd);

        // 2. Verificar actualización del equipo
        $eqPending->refresh();
        $this->assertTrue($eqPending->is_fully_paid);
        $this->assertEquals('settled_in_cuba', $eqPending->financial_status);
        $this->assertEquals(0.00, $eqPending->getOutstandingBalanceUsd());
        $this->assertTrue($eqPending->canBeDelivered());

        // 3. Verificar impacto en balances de la caja
        $shift->refresh();
        $this->assertEquals(95.00, (float) $shift->total_collected_usd);
        $this->assertEquals(70000.00, (float) $shift->total_collected_cup);

        // 4. Verificar que se disparó la notificación de WhatsApp
        $notif = WhatsAppNotification::where('equipment_id', $eqPending->id)
            ->where('message_body', 'like', '%¡Pago Confirmado!%')
            ->first();
        $this->assertNotNull($notif, 'Debe registrarse el envío de WhatsApp con el recibo de pago');

        // 5. Proceder a la entrega física exitosa
        $deliveredEq = $paymentService->deliverEquipment($eqPending, $cajera, 'Entregado en almacén');
        $this->assertEquals(EquipmentStatus::DELIVERED, $deliveredEq->current_status);
        $this->assertNotNull($deliveredEq->delivered_at);
    }

    /**
     * Valida que las rutas web del módulo de Cajas estén protegidas por RBAC.
     */
    public function test_cashier_web_routes_rbac_protection(): void
    {
        $chofer = User::where('role', UserRole::LOGISTICA_CHOFER->value)->first();
        $cajera = User::where('role', UserRole::CAJERO_REGIONAL->value)->first();
        $director = User::where('role', UserRole::DIRECTOR->value)->first();

        // Chofer intenta acceder a la caja regional -> 403 Forbidden
        $response = $this->actingAs($chofer)->get(route('cashier.index'));
        $response->assertStatus(403);

        // Cajera asignada entra a la caja regional -> 200 OK
        $response = $this->actingAs($cajera)->get(route('cashier.index'));
        $response->assertStatus(200);

        // Director entra a la caja regional -> 200 OK
        $response = $this->actingAs($director)->get(route('cashier.index'));
        $response->assertStatus(200);
    }
}
