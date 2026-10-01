<?php

declare(strict_types=1);

namespace App\Http\Controllers\Logistics;

use App\Enums\EquipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\RegionalDispatchRoute;
use App\Models\RegionalWarehouse;
use App\Services\RegionalLogisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LogisticsRouteController extends Controller
{
    public function __construct(
        protected RegionalLogisticsService $logisticsService
    ) {}

    /**
     * Listado general de Hojas de Ruta de traslado regional.
     */
    public function index(Request $request): View
    {
        $query = RegionalDispatchRoute::with(['destinationWarehouse', 'originWarehouse', 'equipments'])
            ->latest('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('destination_id')) {
            $query->where('destination_warehouse_id', $request->input('destination_id'));
        }

        $routes = $query->paginate(15);

        // Métricas
        $inTransitCount = RegionalDispatchRoute::where('status', 'in_transit')->count();
        $draftCount = RegionalDispatchRoute::where('status', 'draft')->count();
        $completedCount = RegionalDispatchRoute::whereIn('status', ['arrived_destination', 'completed'])->count();
        $packagesInTransitCount = Equipment::where('current_status', EquipmentStatus::REGIONAL_TRANSIT)->count();
        $readyForDispatchCount = Equipment::where('current_status', EquipmentStatus::READY_FOR_DISPATCH)->count();

        $warehouses = RegionalWarehouse::where('is_active', true)->orderBy('province')->get();

        return view('logistics.routes.index', compact(
            'routes',
            'inTransitCount',
            'draftCount',
            'completedCount',
            'packagesInTransitCount',
            'readyForDispatchCount',
            'warehouses'
        ));
    }

    /**
     * Formulario de creación de una nueva Hoja de Ruta.
     */
    public function create(): View
    {
        $warehouses = RegionalWarehouse::where('is_active', true)->orderBy('province')->get();
        $readyEquipments = Equipment::where('current_status', EquipmentStatus::READY_FOR_DISPATCH)
            ->latest('updated_at')
            ->get();

        return view('logistics.routes.create', compact('warehouses', 'readyEquipments'));
    }

    /**
     * Guarda una nueva Hoja de Ruta y asocia los equipos seleccionados.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'destination_warehouse_id' => ['required', 'uuid', 'exists:regional_warehouses,id'],
            'origin_warehouse_id' => ['nullable', 'uuid', 'exists:regional_warehouses,id'],
            'driver_name' => ['required', 'string', 'max:100'],
            'driver_phone' => ['nullable', 'string', 'max:50'],
            'truck_license_plate' => ['required', 'string', 'max:20'],
            'route_code' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
            'equipment_ids' => ['nullable', 'array'],
            'equipment_ids.*' => ['uuid', 'exists:equipments,id'],
        ], [
            'destination_warehouse_id.required' => 'Debes seleccionar el almacén provincial de destino.',
            'driver_name.required' => 'Debes ingresar el nombre del chofer asignado.',
            'truck_license_plate.required' => 'Debes ingresar la chapa / matrícula del camión.',
        ]);

        try {
            $route = $this->logisticsService->createRoute(
                data: $validated,
                equipmentIds: $request->input('equipment_ids', []),
                creator: Auth::user()
            );

            return redirect()->route('logistics.routes.show', $route)
                ->with('success', "Hoja de Ruta #{$route->route_code} creada exitosamente con {$route->equipments()->count()} bultos asignados.");
        } catch (\Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Vista de detalle y manifiesto de la Hoja de Ruta.
     */
    public function show(RegionalDispatchRoute $route): View
    {
        $route->load(['destinationWarehouse', 'originWarehouse', 'equipments', 'createdBy']);
        $readyEquipments = Equipment::where('current_status', EquipmentStatus::READY_FOR_DISPATCH)
            ->whereNotIn('id', $route->equipments->pluck('id'))
            ->get();

        return view('logistics.routes.show', compact('route', 'readyEquipments'));
    }

    /**
     * Agrega un bulto escaneado a la hoja de ruta en borrador.
     */
    public function addItem(Request $request, RegionalDispatchRoute $route): JsonResponse|RedirectResponse
    {
        $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        try {
            $result = $this->logisticsService->addEquipmentToRoute($route, $request->input('barcode'));

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('success', $result['message']);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remueve un bulto de una hoja de ruta en borrador.
     */
    public function removeItem(RegionalDispatchRoute $route, Equipment $equipment): RedirectResponse
    {
        try {
            $this->logisticsService->removeEquipmentFromRoute($route, $equipment);
            return back()->with('success', "Bulto {$equipment->tracking_pin} removido de la hoja de ruta.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Despacha la hoja de ruta en convoy (Salida de Puerto Mariel hacia destino provincial).
     */
    public function dispatchConvoy(RegionalDispatchRoute $route): RedirectResponse
    {
        try {
            $this->logisticsService->dispatchRoute($route, Auth::user());
            return back()->with('success', "¡Convoy despachado! Hoja de Ruta #{$route->route_code} ahora está en tránsito hacia {$route->destinationWarehouse->province}.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Confirma la recepción del convoy completo en el almacén de destino.
     */
    public function receiveConvoy(RegionalDispatchRoute $route): RedirectResponse
    {
        try {
            $this->logisticsService->receiveRouteAtDestination($route, Auth::user());
            return back()->with('success', "¡Convoy recibido con éxito! Todos los bultos han ingresado al Almacén Regional {$route->destinationWarehouse->name}.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Recepción individual de un paquete mediante escaneo en destino.
     */
    public function scanReceive(Request $request, RegionalDispatchRoute $route): JsonResponse|RedirectResponse
    {
        $request->validate([
            'barcode' => ['required', 'string'],
        ]);

        try {
            $result = $this->logisticsService->receiveSingleEquipmentAtDestination(
                $route,
                $request->input('barcode'),
                Auth::user()
            );

            if ($request->wantsJson()) {
                return response()->json($result);
            }

            return back()->with('success', $result['message']);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }
    }
}
