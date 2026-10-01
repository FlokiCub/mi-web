<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\EquipmentStatus;
use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Equipment;
use App\Models\EquipmentStatusHistory;
use App\Models\ExchangeRate;
use App\Models\Payment;
use App\Models\User;
use App\Services\WhatsAppNotificationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentService
{
    public function __construct(
        protected WhatsAppNotificationService $whatsAppService
    ) {}

    /**
     * Abre un nuevo turno de caja para un cajero en un almacén regional.
     */
    public function openCashShift(
        CashRegister $cashRegister,
        User $cashier,
        float $initialUsd = 0.00,
        float $initialCup = 0.00,
        ?string $notes = null
    ): CashShift {
        if ($cashRegister->isOpen()) {
            throw new DomainException("La caja [{$cashRegister->code}] ya tiene un turno abierto activo.");
        }

        if (!$cashier->isDirector() && !$cashier->canAccessWarehouse($cashRegister->regional_warehouse_id)) {
            throw new DomainException("El usuario [{$cashier->name}] no está asignado al almacén de esta caja.");
        }

        return DB::transaction(function () use ($cashRegister, $cashier, $initialUsd, $initialCup, $notes) {
            $shift = CashShift::create([
                'cash_register_id' => $cashRegister->id,
                'cashier_user_id' => $cashier->id,
                'opened_at' => now(),
                'opening_balance_usd' => $initialUsd,
                'total_collected_usd' => 0.00,
                'expected_balance_usd' => $initialUsd,
                'opening_balance_cup' => $initialCup,
                'total_collected_cup' => 0.00,
                'expected_balance_cup' => $initialCup,
                'status' => 'open',
                'notes' => $notes,
            ]);

            $cashRegister->update([
                'status' => 'open',
                'current_cash_shift_id' => $shift->id,
            ]);

            Log::info("Turno de caja abierto: Caja [{$cashRegister->code}] por Cajero [{$cashier->email}]. Fondo inicial: \${$initialUsd} USD / \${$initialCup} CUP.");

            return $shift;
        });
    }

    /**
     * Cierra un turno de caja y calcula arqueo y diferencias en USD y CUP.
     */
    public function closeCashShift(
        CashShift $shift,
        float $actualUsdInDrawer,
        float $actualCupInDrawer,
        ?string $notes = null
    ): CashShift {
        if (!$shift->isOpen()) {
            throw new DomainException("El turno de caja ya se encuentra cerrado.");
        }

        return DB::transaction(function () use ($shift, $actualUsdInDrawer, $actualCupInDrawer, $notes) {
            $expectedUsd = (float) $shift->expected_balance_usd;
            $expectedCup = (float) $shift->expected_balance_cup;

            $diffUsd = round($actualUsdInDrawer - $expectedUsd, 2);
            $diffCup = round($actualCupInDrawer - $expectedCup, 2);

            $shift->update([
                'closed_at' => now(),
                'closing_balance_usd' => $actualUsdInDrawer,
                'difference_usd' => $diffUsd,
                'closing_balance_cup' => $actualCupInDrawer,
                'difference_cup' => $diffCup,
                'status' => 'closed',
                'notes' => $notes ? ($shift->notes . " | Cierre: " . $notes) : $shift->notes,
            ]);

            $shift->cashRegister->update([
                'status' => 'closed',
                'current_cash_shift_id' => null,
            ]);

            Log::info("Turno de caja cerrado [{$shift->id}]. Diferencia USD: \${$diffUsd} | Diferencia CUP: \${$diffCup}.");

            return $shift->fresh();
        });
    }

    /**
     * Procesa el cobro de aranceles y flete para un equipo en Cuba (Multimoneda USD/CUP).
     *
     * @param array{
     *    amount_usd: float,
     *    amount_cup: float,
     *    payment_method: string,
     *    client_name?: string,
     *    client_id_card?: string,
     *    client_phone?: string,
     *    transaction_reference?: string,
     *    notes?: string
     * } $paymentInput
     */
    public function processEquipmentPayment(
        Equipment $equipment,
        CashShift $cashShift,
        User $cashier,
        array $paymentInput
    ): Payment {
        if (!$cashShift->isOpen()) {
            throw new DomainException("El turno de caja especificado está cerrado. No se pueden procesar cobros.");
        }

        // 1. Validar si ya está prepagado o liquidado
        if ($equipment->isTotallyPrepaidAbroad()) {
            throw new DomainException("Este equipo llegó 100% PREPAGADO desde el exterior. Está exento de cobro en Cuba.");
        }

        if ($equipment->is_fully_paid) {
            throw new DomainException("El equipo ya fue liquidado anteriormente en Cuba bajo un recibo de pago.");
        }

        $balanceDueUsd = $equipment->getOutstandingBalanceUsd();
        if ($balanceDueUsd <= 0.00) {
            throw new DomainException("El equipo no tiene saldo pendiente de cobro.");
        }

        // 2. Obtener tasa de cambio activa
        $exchangeRate = $this->getActiveExchangeRate();

        $paidUsd = (float) ($paymentInput['amount_usd'] ?? 0.00);
        $paidCup = (float) ($paymentInput['amount_cup'] ?? 0.00);

        // Equivalente en USD del pago en CUP
        $cupEquivalentUsd = $exchangeRate > 0 ? round($paidCup / $exchangeRate, 2) : 0.00;
        $totalEquivalentPaidUsd = round($paidUsd + $cupEquivalentUsd, 2);

        // Validar que el monto cubra el total adeudado
        if ($totalEquivalentPaidUsd < $balanceDueUsd) {
            $missingUsd = round($balanceDueUsd - $totalEquivalentPaidUsd, 2);
            $missingCup = round($missingUsd * $exchangeRate, 2);
            throw new DomainException("Monto insuficiente. Saldo requerido: \${$balanceDueUsd} USD. Se recibió el equivalente a \${$totalEquivalentPaidUsd} USD. Faltan \${$missingUsd} USD ({$missingCup} CUP).");
        }

        return DB::transaction(function () use (
            $equipment,
            $cashShift,
            $cashier,
            $paymentInput,
            $balanceDueUsd,
            $paidUsd,
            $paidCup,
            $exchangeRate,
            $totalEquivalentPaidUsd
        ) {
            // Generar número de recibo consecutivo
            $receiptNumber = $this->generateReceiptNumber();

            $clientName = $paymentInput['client_name'] 
                ?? $equipment->client_data['name'] 
                ?? 'Cliente Titular';

            $clientPhone = $paymentInput['client_phone'] 
                ?? $equipment->client_data['phone'] 
                ?? null;

            // 3. Crear el pago
            $payment = Payment::create([
                'receipt_number' => $receiptNumber,
                'equipment_id' => $equipment->id,
                'cash_shift_id' => $cashShift->id,
                'cashier_user_id' => $cashier->id,
                'concept' => 'duty_and_delivery',
                'amount_due_usd' => $balanceDueUsd,
                'duty_amount_usd' => $equipment->duty_paid_abroad ? 0.00 : (float) $equipment->duty_amount_usd,
                'shipping_fee_usd' => ($equipment->home_delivery_requested && !$equipment->delivery_paid_abroad) 
                    ? (float) $equipment->shipping_fee_usd 
                    : 0.00,
                'amount_paid_usd' => $paidUsd,
                'amount_paid_cup' => $paidCup,
                'exchange_rate_applied' => $exchangeRate,
                'equivalent_total_usd' => $totalEquivalentPaidUsd,
                'payment_method' => $paymentInput['payment_method'] ?? 'cash_mixed',
                'status' => 'completed',
                'client_name' => $clientName,
                'client_id_card' => $paymentInput['client_id_card'] ?? null,
                'client_phone' => $clientPhone,
                'transaction_reference' => $paymentInput['transaction_reference'] ?? null,
                'notes' => $paymentInput['notes'] ?? null,
                'paid_at' => now(),
            ]);

            // 4. Actualizar totales del turno de caja
            $cashShift->update([
                'total_collected_usd' => round((float) $cashShift->total_collected_usd + $paidUsd, 2),
                'expected_balance_usd' => round((float) $cashShift->expected_balance_usd + $paidUsd, 2),
                'total_collected_cup' => round((float) $cashShift->total_collected_cup + $paidCup, 2),
                'expected_balance_cup' => round((float) $cashShift->expected_balance_cup + $paidCup, 2),
            ]);

            // 5. Marcar equipo como TOTALMENTE PAGADO
            $equipment->update([
                'financial_status' => 'settled_in_cuba',
                'is_fully_paid' => true,
                'settled_at' => now(),
                'settled_by_user_id' => $cashier->id,
            ]);

            // 6. Registrar en bitácora inmutable
            EquipmentStatusHistory::create([
                'equipment_id' => $equipment->id,
                'from_status' => is_object($equipment->current_status) ? $equipment->current_status->value : $equipment->current_status,
                'to_status' => is_object($equipment->current_status) ? $equipment->current_status->value : $equipment->current_status,
                'location' => "Caja Almacén ({$cashShift->cashRegister->name})",
                'notes' => "LIQUIDACIÓN CONFIRMADA — Recibo #{$receiptNumber}. Monto cobrado: \${$paidUsd} USD + \${$paidCup} CUP (Tasa: {$exchangeRate}). Equipo habilitado para entrega final.",
                'changed_by_user_id' => $cashier->id,
                'snapshot' => [
                    'receipt_number' => $receiptNumber,
                    'paid_usd' => $paidUsd,
                    'paid_cup' => $paidCup,
                    'exchange_rate' => $exchangeRate,
                ],
                'recorded_at' => now(),
            ]);

            // 7. Notificar al cliente por WhatsApp con el comprobante de pago
            $paymentNote = "✅ *¡Pago Confirmado!* Hemos recibido tu liquidación por aranceles/servicios (Recibo #{$receiptNumber}). Tu equipo está autorizado para entrega inmediata.";
            $this->whatsAppService->sendStatusUpdateNotification($equipment, $paymentNote);

            Log::info("Pago completado exitosamente: Recibo [{$receiptNumber}] para equipo [{$equipment->tracking_pin}].");

            return $payment;
        });
    }

    /**
     * Valida si un equipo cumple con los prerrequisitos estrictos para ser entregado.
     *
     * @return array{can_deliver: bool, reason: string, balance_due_usd: float}
     */
    public function verifyDeliveryEligibility(Equipment $equipment): array
    {
        if ($equipment->isTotallyPrepaidAbroad()) {
            return [
                'can_deliver' => true,
                'reason' => '✓ 100% Prepagado en el Exterior (Exento de cobro en Cuba). Autorizado para entrega.',
                'balance_due_usd' => 0.00,
            ];
        }

        if ($equipment->is_fully_paid) {
            return [
                'can_deliver' => true,
                'reason' => '✓ Liquidado en Cuba mediante pago en caja. Autorizado para entrega.',
                'balance_due_usd' => 0.00,
            ];
        }

        $balanceDue = $equipment->getOutstandingBalanceUsd();

        return [
            'can_deliver' => false,
            'reason' => "⛔ BLOQUEO FINANCIERO: El equipo tiene saldo pendiente de cobro de \${$balanceDue} USD. Debe liquidarse antes de la entrega.",
            'balance_due_usd' => $balanceDue,
        ];
    }

    /**
     * Ejecuta la entrega física del equipo al cliente, bloqueando si hay deuda pendiente.
     */
    public function deliverEquipment(Equipment $equipment, User $operator, ?string $notes = null): Equipment
    {
        $eligibility = $this->verifyDeliveryEligibility($equipment);

        if (!$eligibility['can_deliver']) {
            throw new DomainException($eligibility['reason']);
        }

        return DB::transaction(function () use ($equipment, $operator, $notes) {
            $fromStatus = $equipment->current_status;

            $equipment->update([
                'current_status' => EquipmentStatus::DELIVERED,
                'delivered_at' => now(),
                'current_location_note' => 'Entregado al cliente final',
            ]);

            EquipmentStatusHistory::create([
                'equipment_id' => $equipment->id,
                'from_status' => is_object($fromStatus) ? $fromStatus->value : $fromStatus,
                'to_status' => EquipmentStatus::DELIVERED->value,
                'location' => 'Punto de Entrega / Domicilio',
                'notes' => 'ENTREGA COMPLETADA AL CLIENTE. ' . ($notes ?: 'Sin incidencias.'),
                'changed_by_user_id' => $operator->id,
                'recorded_at' => now(),
            ]);

            // Disparar WhatsApp de entrega exitosa
            $this->whatsAppService->sendStatusUpdateNotification($equipment);

            Log::info("Equipo [{$equipment->tracking_pin}] entregado satisfactoriamente por operador [{$operator->email}].");

            return $equipment->fresh();
        });
    }

    /**
     * Obtiene la tasa de cambio activa USD -> CUP.
     */
    public function getActiveExchangeRate(): float
    {
        $rate = ExchangeRate::where('currency_from', 'USD')
            ->where('currency_to', 'CUP')
            ->where('is_active', true)
            ->latest()
            ->value('rate');

        return (float) ($rate ?: 350.00);
    }

    protected function generateReceiptNumber(): string
    {
        $datePrefix = Carbon::now()->format('Ymd');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        
        return "REC-{$datePrefix}-{$random}";
    }
}
