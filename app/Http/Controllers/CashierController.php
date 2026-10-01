<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CashRegister;
use App\Models\CashShift;
use App\Models\Equipment;
use App\Models\Payment;
use App\Services\Finance\PaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CashierController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Muestra la interfaz del cajero regional: turno actual, caja y resumen del día.
     */
    public function index(): View
    {
        $user = Auth::user();
        $warehouseId = $user->regional_warehouse_id;

        // Buscar cajas del almacén del usuario (o todas si es Director)
        $registersQuery = CashRegister::with('currentShift')->where('is_active', true);
        if (!$user->isDirector() && $warehouseId) {
            $registersQuery->where('regional_warehouse_id', $warehouseId);
        }
        $cashRegisters = $registersQuery->get();

        // Buscar si el usuario tiene un turno actualmente abierto
        $activeShift = CashShift::with('cashRegister')
            ->where('cashier_user_id', $user->id)
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();

        // Pagos cobrados en el turno activo
        $recentPayments = $activeShift 
            ? $activeShift->payments()->with('equipment')->take(15)->get() 
            : collect();

        $activeRate = $this->paymentService->getActiveExchangeRate();

        return view('cashier.index', compact(
            'cashRegisters',
            'activeShift',
            'recentPayments',
            'activeRate'
        ));
    }

    /**
     * Apertura de turno de caja.
     */
    public function openShift(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_register_id' => ['required', 'uuid', 'exists:cash_registers,id'],
            'initial_usd' => ['nullable', 'numeric', 'min:0'],
            'initial_cup' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $register = CashRegister::findOrFail($validated['cash_register_id']);

        try {
            $this->paymentService->openCashShift(
                cashRegister: $register,
                cashier: Auth::user(),
                initialUsd: (float) ($validated['initial_usd'] ?? 0.0),
                initialCup: (float) ($validated['initial_cup'] ?? 0.0),
                notes: $validated['notes'] ?? null
            );

            return redirect()->route('cashier.index')
                ->with('success', "Turno de caja abierto correctamente en [{$register->name}].");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Cierre de turno de caja (Arqueo de billetes en gaveta).
     */
    public function closeShift(Request $request, CashShift $shift): RedirectResponse
    {
        $validated = $request->validate([
            'closing_usd' => ['required', 'numeric', 'min:0'],
            'closing_cup' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $closedShift = $this->paymentService->closeCashShift(
                shift: $shift,
                actualUsdInDrawer: (float) $validated['closing_usd'],
                actualCupInDrawer: (float) $validated['closing_cup'],
                notes: $validated['notes'] ?? null
            );

            $diffMsg = "USD Diff: \${$closedShift->difference_usd} | CUP Diff: \${$closedShift->difference_cup}";

            return redirect()->route('cashier.index')
                ->with('success', "Turno de caja cerrado exitosamente. Arqueo registrado ({$diffMsg}).");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Busca un equipo y evalúa su estado financiero para cobro o entrega.
     */
    public function lookupEquipment(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));

        $equipment = Equipment::with('currentWarehouse')
            ->where('tracking_pin', $code)
            ->orWhere('vin_serial', $code)
            ->orWhere('solve_cargo_tracking_id', $code)
            ->first();

        if (!$equipment) {
            return response()->json([
                'success' => false,
                'message' => "No se encontró ningún equipo con el código \"{$code}\".",
            ], 404);
        }

        $eligibility = $this->paymentService->verifyDeliveryEligibility($equipment);
        $rate = $this->paymentService->getActiveExchangeRate();
        $outstandingUsd = $equipment->getOutstandingBalanceUsd();
        $outstandingCup = round($outstandingUsd * $rate, 2);

        return response()->json([
            'success' => true,
            'equipment' => [
                'id' => $equipment->id,
                'tracking_pin' => $equipment->tracking_pin,
                'vin_serial' => $equipment->vin_serial,
                'brand' => $equipment->brand,
                'model' => $equipment->model,
                'business_type' => $equipment->business_type?->value ?? (string) $equipment->business_type,
                'current_status' => is_object($equipment->current_status) ? $equipment->current_status->value : $equipment->current_status,
                'status_label' => method_exists($equipment->current_status, 'label') ? $equipment->current_status->label() : (string) $equipment->current_status,
                'warehouse' => $equipment->currentWarehouse?->name,
                'is_totally_prepaid' => $equipment->isTotallyPrepaidAbroad(),
                'is_fully_paid' => $equipment->is_fully_paid,
                'financial_status' => $equipment->financial_status,
                'duty_amount_usd' => (float) $equipment->duty_amount_usd,
                'duty_paid_abroad' => (bool) $equipment->duty_paid_abroad,
                'shipping_fee_usd' => (float) $equipment->shipping_fee_usd,
                'delivery_paid_abroad' => (bool) $equipment->delivery_paid_abroad,
                'home_delivery_requested' => (bool) $equipment->home_delivery_requested,
                'outstanding_usd' => $outstandingUsd,
                'outstanding_cup' => $outstandingCup,
                'exchange_rate' => $rate,
                'client_name' => $equipment->client_data['name'] ?? 'N/A',
                'client_phone' => $equipment->client_data['phone'] ?? 'N/A',
                'eligibility' => $eligibility,
            ],
        ]);
    }

    /**
     * Procesa la transacción de pago para el equipo.
     */
    public function storePayment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'equipment_id' => ['required', 'uuid', 'exists:equipments,id'],
            'cash_shift_id' => ['required', 'uuid', 'exists:cash_shifts,id'],
            'amount_usd' => ['nullable', 'numeric', 'min:0'],
            'amount_cup' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'in:cash_usd,cash_cup,cash_mixed,transfer_cup,transfer_mlc'],
            'client_name' => ['nullable', 'string', 'max:120'],
            'client_id_card' => ['nullable', 'string', 'max:40'],
            'client_phone' => ['nullable', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $equipment = Equipment::findOrFail($validated['equipment_id']);
        $shift = CashShift::findOrFail($validated['cash_shift_id']);

        try {
            $payment = $this->paymentService->processEquipmentPayment(
                equipment: $equipment,
                cashShift: $shift,
                cashier: Auth::user(),
                paymentInput: $validated
            );

            return redirect()->route('cashier.receipt', $payment)
                ->with('success', "Pago registrado exitosamente. Recibo: {$payment->receipt_number}. Equipo liberado para entrega.");
        } catch (DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Muestra el recibo digital de comprobante de pago.
     */
    public function showReceipt(Payment $payment): View
    {
        $payment->load(['equipment', 'cashier', 'cashShift.cashRegister.regionalWarehouse']);

        return view('cashier.receipt', compact('payment'));
    }

    /**
     * Entrega formal del equipo al cliente.
     */
    public function deliver(Request $request, Equipment $equipment): RedirectResponse
    {
        $notes = $request->input('notes');

        try {
            $this->paymentService->deliverEquipment(
                equipment: $equipment,
                operator: Auth::user(),
                notes: $notes
            );

            return back()->with('success', "¡Entrega confirmada! El equipo {$equipment->tracking_pin} ha sido entregado exitosamente al cliente.");
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
