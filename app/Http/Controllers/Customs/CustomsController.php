<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customs;

use App\Enums\EquipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Services\Customs\CustomsInspectionService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CustomsController extends Controller
{
    public function __construct(
        private readonly CustomsInspectionService $customsService
    ) {}

    /**
     * Panel principal de Aduana con semáforos 48h/72h y listado de equipos en puerto.
     */
    public function index(Request $request): View
    {
        $semaphoreFilter = $request->query('semaphore');
        $search = $request->query('search');

        $query = Equipment::inCustoms()->with(['statusHistories.changedBy', 'pvpInspections']);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('tracking_pin', 'ilike', "%{$search}%")
                  ->orWhere('vin_serial', 'ilike', "%{$search}%")
                  ->orWhere('brand', 'ilike', "%{$search}%")
                  ->orWhere('model', 'ilike', "%{$search}%");
            });
        }

        $equipments = $query->orderBy('customs_entry_at', 'asc')->paginate(15)->withQueryString();

        // Si se seleccionó filtro de semáforo en frontend, filtramos la colección
        if (!empty($semaphoreFilter) && in_array($semaphoreFilter, ['green', 'yellow', 'red'], true)) {
            $filteredItems = $equipments->getCollection()->filter(function (Equipment $eq) use ($semaphoreFilter) {
                return $eq->getCustomsSemaphore() === $semaphoreFilter;
            });
            $equipments->setCollection($filteredItems);
        }

        $metrics = $this->customsService->getSemaphoreMetrics();

        return view('customs.index', compact('equipments', 'metrics', 'semaphoreFilter', 'search'));
    }

    /**
     * Muestra el detalle aduanal de un equipo específico.
     */
    public function show(Equipment $equipment): View
    {
        $equipment->load(['statusHistories.changedBy', 'pvpInspections.technician', 'whatsAppNotifications']);

        return view('customs.show', compact('equipment'));
    }

    /**
     * Inicia la inspección física de un equipo.
     */
    public function startInspection(Request $request, Equipment $equipment): RedirectResponse
    {
        $user = Auth::user();
        $notes = $request->input('notes');

        try {
            $this->customsService->startInspection($equipment, $user, $notes);

            return redirect()->back()->with('success', "Inspección iniciada para el equipo PIN: {$equipment->tracking_pin}");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al iniciar inspección: ' . $e->getMessage());
        }
    }

    /**
     * Otorga libramiento aduanal.
     */
    public function clear(Request $request, Equipment $equipment): RedirectResponse
    {
        $validated = $request->validate([
            'clearance_note' => ['nullable', 'string', 'max:500'],
            'customs_declaration_number' => ['nullable', 'string', 'max:100'],
        ]);

        $user = Auth::user();

        try {
            $this->customsService->clearCustoms(
                equipment: $equipment,
                inspector: $user,
                clearanceNote: $validated['clearance_note'] ?? null,
                customsDeclarationNumber: $validated['customs_declaration_number'] ?? null
            );

            return redirect()->route('customs.index')
                ->with('success', "Libramiento aduanal concedido para equipo PIN: {$equipment->tracking_pin}. Transferido al siguiente paso.");
        } catch (DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al liberar equipo: ' . $e->getMessage());
        }
    }

    /**
     * Retiene el equipo en aduana.
     */
    public function hold(Request $request, Equipment $equipment): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $user = Auth::user();

        try {
            $this->customsService->holdInCustoms($equipment, $user, $validated['reason']);

            return redirect()->back()->with('warning', "Equipo {$equipment->tracking_pin} marcado en RETENCIÓN ADUANAL.");
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Error al retener equipo: ' . $e->getMessage());
        }
    }
}
