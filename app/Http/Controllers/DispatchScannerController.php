<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Services\DispatchReceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DispatchScannerController extends Controller
{
    public function __construct(
        protected DispatchReceptionService $receptionService
    ) {}

    /**
     * Muestra la interfaz del escáner de despacho y cotejo con el manifiesto.
     */
    public function index(): View
    {
        // Últimos paquetes verificados listos para despacho
        $recentVerified = Equipment::where('current_status', EquipmentStatus::READY_FOR_DISPATCH)
            ->latest('updated_at')
            ->take(10)
            ->get();

        $readyForDispatchCount = Equipment::where('current_status', EquipmentStatus::READY_FOR_DISPATCH)->count();
        $inPortArrivalCount = Equipment::where('current_status', EquipmentStatus::PORT_ARRIVAL)->count();
        $pendingTransitCount = Equipment::whereIn('current_status', [
            EquipmentStatus::SUPPLIER_DISPATCHED,
            EquipmentStatus::IN_TRANSIT_SOLVE_CARGO,
            EquipmentStatus::CUSTOMS_INSPECTION,
        ])->count();

        return view('dispatch.scanner', compact(
            'recentVerified',
            'readyForDispatchCount',
            'inPortArrivalCount',
            'pendingTransitCount'
        ));
    }

    /**
     * Consulta asíncrona de datos del manifiesto para cotejo previo en el escáner.
     */
    public function lookupManifest(Request $request): JsonResponse
    {
        $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        try {
            $data = $this->receptionService->lookupManifestForScan($request->input('barcode'));
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Procesa el cotejo con el manifiesto y pasa el equipo directamente a READY_FOR_DISPATCH.
     */
    public function processScan(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'barcode' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:150'],
            'duty_paid_abroad' => ['nullable', 'boolean'],
            'delivery_paid_abroad' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ], [
            'barcode.required' => 'Debes escanear o ingresar el código del paquete o PIN.',
        ]);

        try {
            $result = $this->receptionService->verifyAndClearForDispatch(
                barcodeOrPin: $request->input('barcode'),
                manifestData: [
                    'duty_paid_abroad' => $request->boolean('duty_paid_abroad'),
                    'delivery_paid_abroad' => $request->boolean('delivery_paid_abroad'),
                ],
                receptionLocation: $request->input('location') ?: 'Puerto del Mariel - Área de Despacho',
                notes: $request->input('notes'),
                operator: Auth::user()
            );

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('scan_result', $result)->with('success', $result['message']);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Registra una discrepancia detectada durante el escaneo contra el manifiesto.
     */
    public function reportDiscrepancy(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'barcode' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:150'],
        ], [
            'barcode.required' => 'Debes especificar el código del paquete.',
            'reason.required' => 'Debes describir el motivo de la discrepancia.',
        ]);

        try {
            $result = $this->receptionService->reportManifestDiscrepancy(
                barcodeOrPin: $request->input('barcode'),
                discrepancyReason: $request->input('reason'),
                location: $request->input('location') ?: 'Puerto del Mariel - Área de Despacho',
                operator: Auth::user()
            );

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('warning', $result['message']);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
