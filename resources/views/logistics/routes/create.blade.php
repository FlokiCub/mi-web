@extends('layouts.app', ['title' => 'Nueva Hoja de Ruta Regional'])

@section('page_title', 'Crear Hoja de Ruta Regional — Despacho Mariel')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

  <!-- Header -->
  <div class="flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="{{ route('logistics.routes.index') }}" class="p-2 rounded-xl bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition-colors">
        <i class="fa-solid fa-arrow-left"></i>
      </a>
      <div>
        <h3 class="text-xl font-black text-white">Nueva Hoja de Ruta de Traslado</h3>
        <p class="text-xs text-slate-400">Asigna chofer, camión y bultos listos para su salida a provincia</p>
      </div>
    </div>
  </div>

  @if($errors->any())
    <div class="p-4 rounded-2xl bg-rose-950/50 border border-rose-500/40 text-rose-200 text-xs space-y-1">
      <strong class="block font-bold">Por favor corrige los siguientes errores:</strong>
      <ul class="list-disc list-inside">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('logistics.routes.store') }}" class="space-y-6">
    @csrf

    <!-- Route Information Card -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
      <div class="flex items-center gap-2 pb-3 border-b border-slate-800">
        <i class="fa-solid fa-truck text-amber-400"></i>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">1. Datos del Convoy y Destino</h4>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Destination Warehouse -->
        <div>
          <label for="destination_warehouse_id" class="block text-xs font-semibold text-slate-300 mb-1">
            Almacén Provincial de Destino <span class="text-rose-400">*</span>
          </label>
          <select 
            id="destination_warehouse_id" 
            name="destination_warehouse_id" 
            required 
            class="w-full px-3 py-3 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 font-semibold"
          >
            <option value="">Selecciona la provincia / sucursal...</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ old('destination_warehouse_id') === $wh->id ? 'selected' : '' }}>
                📍 {{ $wh->province }} — {{ $wh->name }} ({{ $wh->address }})
              </option>
            @endforeach
          </select>
        </div>

        <!-- Truck License Plate -->
        <div>
          <label for="truck_license_plate" class="block text-xs font-semibold text-slate-300 mb-1">
            Chapa / Matrícula del Camión <span class="text-rose-400">*</span>
          </label>
          <input 
            type="text" 
            id="truck_license_plate" 
            name="truck_license_plate" 
            required 
            value="{{ old('truck_license_plate') }}"
            placeholder="Ej: B123456" 
            class="w-full px-3 py-3 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white uppercase font-mono font-bold focus:ring-2 focus:ring-amber-500"
          />
        </div>

        <!-- Driver Name -->
        <div>
          <label for="driver_name" class="block text-xs font-semibold text-slate-300 mb-1">
            Nombre del Chofer Asignado <span class="text-rose-400">*</span>
          </label>
          <input 
            type="text" 
            id="driver_name" 
            name="driver_name" 
            required 
            value="{{ old('driver_name') }}"
            placeholder="Ej: Yoelkis Hernández" 
            class="w-full px-3 py-3 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500"
          />
        </div>

        <!-- Driver Phone -->
        <div>
          <label for="driver_phone" class="block text-xs font-semibold text-slate-300 mb-1">
            Teléfono Móvil del Chofer
          </label>
          <input 
            type="text" 
            id="driver_phone" 
            name="driver_phone" 
            value="{{ old('driver_phone') }}"
            placeholder="Ej: +53 5 444 8899" 
            class="w-full px-3 py-3 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500"
          />
        </div>
      </div>

      <!-- Notes -->
      <div>
        <label for="notes" class="block text-xs font-semibold text-slate-300 mb-1">
          Observaciones de la Ruta / Manifiesto
        </label>
        <textarea 
          id="notes" 
          name="notes" 
          rows="2" 
          placeholder="Ej: Precinto de seguridad #PL-9021 en compartimento de carga principal"
          class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-slate-200"
        >{{ old('notes') }}</textarea>
      </div>
    </div>

    <!-- Package Selection Card -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-800">
        <div class="flex items-center gap-2">
          <i class="fa-solid fa-boxes-packing text-amber-400"></i>
          <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">2. Selección de Bultos para Embarque</h4>
        </div>
        <span class="text-xs text-slate-400 font-mono">
          <strong id="selected-count" class="text-amber-400">0</strong> seleccionados
        </span>
      </div>

      @if($readyEquipments->isEmpty())
        <div class="py-8 text-center text-slate-500 text-xs">
          No hay bultos en estado <strong>Listo para Despacho</strong> actualmente en Mariel. Pasa los equipos por el escáner de despacho para habilitarlos.
        </div>
      @else
        <div class="max-h-96 overflow-y-auto divide-y divide-slate-800/60 pr-2">
          @foreach($readyEquipments as $eq)
            <label class="py-3 flex items-center justify-between gap-3 hover:bg-slate-800/40 p-2 rounded-xl cursor-pointer transition-colors">
              <div class="flex items-center gap-3">
                <input 
                  type="checkbox" 
                  name="equipment_ids[]" 
                  value="{{ $eq->id }}" 
                  onchange="updateSelectedCount()"
                  class="equipment-checkbox w-4 h-4 rounded bg-slate-950 border-slate-700 text-amber-600 focus:ring-amber-500"
                />
                <div>
                  <p class="font-bold text-white text-xs">{{ $eq->brand }} {{ $eq->model }}</p>
                  <p class="text-[11px] text-slate-400 font-mono">
                    <span class="text-amber-400 font-bold">{{ $eq->tracking_pin }}</span>
                    <span>•</span>
                    <span>VIN: {{ $eq->vin_serial }}</span>
                    <span>•</span>
                    <span class="text-emerald-300">Destino: {{ $eq->client_data['province'] ?? 'La Habana' }}</span>
                  </p>
                </div>
              </div>

              <div>
                @if($eq->duty_paid_abroad)
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                    Prepagado $0
                  </span>
                @else
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                    Cobro en Cuba
                  </span>
                @endif
              </div>
            </label>
          @endforeach
        </div>
      @endif
    </div>

    <!-- Submit Button -->
    <div class="flex items-center justify-end gap-3">
      <a href="{{ route('logistics.routes.index') }}" class="px-5 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs transition-colors">
        Cancelar
      </a>
      <button 
        type="submit" 
        class="px-8 py-3.5 rounded-2xl bg-gradient-to-r from-amber-600 via-amber-500 to-yellow-600 hover:from-amber-500 hover:to-yellow-500 text-white font-bold text-xs shadow-xl shadow-amber-950/40 active:scale-98 transition-all flex items-center gap-2"
      >
        <i class="fa-solid fa-file-circle-check text-sm"></i>
        <span>Crear Hoja de Ruta y Asignar Bultos</span>
      </button>
    </div>
  </form>

</div>

<script>
  function updateSelectedCount() {
    const checkboxes = document.querySelectorAll('.equipment-checkbox:checked');
    document.getElementById('selected-count').textContent = checkboxes.length;
  }
</script>
@endsection
