<?php

namespace App\Http\Controllers;

use App\Http\Resources\EquipmentTrackingResource;
use App\Models\Equipment;
use Illuminate\Http\Request;

class PublicTrackingController extends Controller
{
    /**
     * Show the public tracking search landing page.
     */
    public function index()
    {
        return view('tracking.index');
    }

    /**
     * Process tracking search query.
     */
    public function search(Request $request)
    {
        $request->validate([
            'pin' => ['required', 'string', 'max:50'],
        ], [
            'pin.required' => 'Por favor introduce tu PIN de rastreo o VIN/Serial.',
        ]);

        $query = strtoupper(trim($request->input('pin')));

        $equipment = Equipment::where('tracking_pin', $query)
            ->orWhere('vin_serial', $query)
            ->orWhere('solve_cargo_tracking_id', $query)
            ->first();

        if (!$equipment) {
            return back()->withInput()->with('error', "No se encontró ningún equipo con el código \"{$query}\". Verifica e intenta nuevamente.");
        }

        return redirect()->route('tracking.show', ['pin' => $equipment->tracking_pin]);
    }

    /**
     * Display tracking details for a specific equipment PIN.
     */
    public function show(string $pin)
    {
        $cleanPin = strtoupper(trim($pin));

        $equipment = Equipment::with(['statusHistories', 'currentWarehouse'])
            ->where('tracking_pin', $cleanPin)
            ->orWhere('vin_serial', $cleanPin)
            ->firstOrFail();

        return view('tracking.show', compact('equipment'));
    }

    /**
     * API Endpoint for real-time tracking lookup.
     */
    public function apiLookup(string $pin)
    {
        $cleanPin = strtoupper(trim($pin));

        $equipment = Equipment::with(['statusHistories', 'currentWarehouse'])
            ->where('tracking_pin', $cleanPin)
            ->orWhere('vin_serial', $cleanPin)
            ->first();

        if (!$equipment) {
            return response()->json([
                'success' => false,
                'message' => 'Equipo no encontrado.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new EquipmentTrackingResource($equipment),
        ]);
    }
}
