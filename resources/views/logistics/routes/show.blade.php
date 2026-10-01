@extends('layouts.app', ['title' => 'Hoja de Ruta #' . $route->route_code])

@section('page_title', 'Hoja de Ruta #' . $route->route_code . ' — Manifiesto de Traslado')

@section('content')
<div class="space-y-6">

  <!-- Header Banner -->
  <div class="p-6 sm:p-8 rounded-3xl bg-slate-900 border border-slate-800 shadow-2xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
    <div class="space-y-2">
      <div class="flex items-center gap-3">
        <a href="{{ route('logistics.routes.index') }}" class="p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white transition-colors">
          <i class="fa-solid fa-arrow-left"></i>
        </a>
        <span class="px-3 py-1 rounded-full text-xs font-bold border {{ $route->statusBadgeClass() }}">
          {{ $route->statusLabel() }}
        </span>
        <span class="text-xs font-mono text-slate-400">Creada por {{ $route->createdBy->name ?? 'Operador' }}</span>
      </div>
      <h3 class="text-2xl sm:text-3xl font-black text-white font-mono tracking-tight">
        #{{ $route->route_code }}
      </h3>
      <p class="text-xs text-slate-400">
        Origen: <strong>Puerto del Mariel</strong> ➔ Destino: <strong class="text-amber-400">{{ $route->destinationWarehouse->province ?? 'N/A' }} ({{ $route->destinationWarehouse->name ?? 'N/A' }})</strong>
      </p>
    </div>

    <!-- Convoy Primary Action -->
    <div class="flex items-center gap-3">
      @if($route->isDraft())
        <form method="POST" action="{{ route('logistics.routes.dispatch', $route) }}" onsubmit="return confirm('¿Confirmas el despacho del convoy y salida del Puerto del Mariel? Se notificarán todos los clientes por WhatsApp.');">
          @csrf
          <button type="submit" class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs shadow-xl shadow-blue-950/40 active:scale-98 transition-all flex items-center gap-2">
            <i class="fa-solid fa-paper-plane text-sm"></i>
            <span>Despachar Convoy (Salida en Ruta)</span>
          </button>
        </form>
      @elseif($route->isInTransit())
        <form method="POST" action="{{ route('logistics.routes.receive', $route) }}" onsubmit="return confirm('¿Confirmas el arribo y recepción del convoy en el almacén de destino? Todos los bultos pasarán a Disponible en Almacén Regional.');">
          @csrf
          <button type="submit" class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-xl shadow-emerald-950/40 active:scale-98 transition-all flex items-center gap-2">
            <i class="fa-solid fa-warehouse text-sm"></i>
            <span>Confirmar Recepción en Almacén Provincial</span>
          </button>
        </form>
      @else
        <div class="px-5 py-3 rounded-2xl bg-emerald-950/50 border border-emerald-500/40 text-emerald-300 text-xs font-bold flex items-center gap-2">
          <i class="fa-solid fa-circle-check text-emerald-400"></i>
          <span>Recepción Completada el {{ $route->arrived_at?->format('d/m/Y H:i') }}</span>
        </div>
      @endif
    </div>
  </div>

  @if(session('success'))
    <div class="p-4 rounded-2xl bg-emerald-950/50 border border-emerald-500/40 text-emerald-200 text-xs flex items-center gap-3">
      <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  @if(session('error'))
    <div class="p-4 rounded-2xl bg-rose-950/50 border border-rose-500/40 text-rose-200 text-xs flex items-center gap-3">
      <i class="fa-solid fa-circle-xmark text-rose-400 text-base"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  <!-- Convoy Details Cards -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    
    <!-- Driver and Truck Card -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-sm space-y-3">
      <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
        <i class="fa-solid fa-id-card text-amber-400"></i>
        <span>Transportista / Camión</span>
      </h4>
      <div class="text-xs space-y-1">
        <p class="text-white font-bold text-sm">{{ $route->driver_name }}</p>
        <p class="text-slate-400 font-mono">Chapa / Matrícula: <strong class="text-white">{{ $route->truck_license_plate }}</strong></p>
        <p class="text-slate-400">Teléfono: <span class="text-slate-200">{{ $route->driver_phone ?? 'No especificado' }}</span></p>
      </div>
    </div>

    <!-- Origin / Destination Warehouse Card -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-sm space-y-3">
      <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
        <i class="fa-solid fa-location-dot text-blue-400"></i>
        <span>Ruta de Traslado</span>
      </h4>
      <div class="text-xs space-y-1">
        <p class="text-slate-300">Origen: <strong class="text-white">Puerto del Mariel (Terminal Central)</strong></p>
        <p class="text-slate-300">Destino: <strong class="text-emerald-400">{{ $route->destinationWarehouse->name }}</strong></p>
        <p class="text-slate-400 text-[11px]">{{ $route->destinationWarehouse->address }}</p>
      </div>
    </div>

    <!-- Chronology Card -->
    <div class="bg-slate-900/90 border border-slate-800 rounded-3xl p-6 shadow-sm space-y-3">
      <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-2">
        <i class="fa-solid fa-clock text-cyan-400"></i>
        <span>Cronología del Traslado</span>
      </h4>
      <div class="text-xs space-y-1 font-mono">
        <p class="text-slate-400">Creación: <span class="text-slate-200">{{ $route->created_at->format('d/m/Y H:i') }}</span></p>
        <p class="text-slate-400">Salida Despacho: <span class="text-blue-300">{{ $route->dispatched_at ? $route->dispatched_at->format('d/m/Y H:i') : 'En preparación' }}</span></p>
        <p class="text-slate-400">Llegada Almacén: <span class="text-emerald-300">{{ $route->arrived_at ? $route->arrived_at->format('d/m/Y H:i') : 'En ruta' }}</span></p>
      </div>
    </div>

  </div>

  <!-- Scanner Quick Actions -->
  @if($route->isDraft())
    <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
      <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
        <i class="fa-solid fa-barcode text-amber-400"></i>
        <span>Pistola Lectora — Agregar Bulto a esta Hoja de Ruta</span>
      </h4>
      <form method="POST" action="{{ route('logistics.routes.items.add', $route) }}" class="flex gap-3">
        @csrf
        <input 
          type="text" 
          name="barcode" 
          required 
          placeholder="Escanea el código de barras o escribe el PIN del bulto..." 
          class="flex-1 px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono uppercase font-bold focus:ring-2 focus:ring-amber-500"
        />
        <button type="submit" class="px-6 py-3 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs">
          Cargar en Camión
        </button>
      </form>
    </div>
  @elseif($route->isInTransit())
    <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3">
      <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center gap-2">
        <i class="fa-solid fa-barcode text-emerald-400"></i>
        <span>Pistola Lectora — Recepción Individual de Bulto en Muelle de Descarga</span>
      </h4>
      <form method="POST" action="{{ route('logistics.routes.scan-receive', $route) }}" class="flex gap-3">
        @csrf
        <input 
          type="text" 
          name="barcode" 
          required 
          placeholder="Escanea el código del bulto al bajar del camión..." 
          class="flex-1 px-4 py-3 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white font-mono uppercase font-bold focus:ring-2 focus:ring-emerald-500"
        />
        <button type="submit" class="px-6 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs">
          Confirmar Recepción
        </button>
      </form>
    </div>
  @endif

  <!-- Package Manifest Table -->
  <div class="bg-slate-900/90 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl space-y-4">
    <div class="p-6 border-b border-slate-800 flex items-center justify-between">
      <div>
        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">Manifiesto de Carga del Convoy</h4>
        <p class="text-[11px] text-slate-400">{{ $route->equipments->count() }} bultos asignados a esta hoja de ruta</p>
      </div>
      <button type="button" onclick="window.print()" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs flex items-center gap-2">
        <i class="fa-solid fa-print"></i>
        <span>Imprimir Manifiesto</span>
      </button>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs text-slate-300">
        <thead class="bg-slate-950 text-[11px] uppercase tracking-wider text-slate-400 font-bold border-b border-slate-800">
          <tr>
            <th class="py-3.5 px-4"># PIN de Rastreo</th>
            <th class="py-3.5 px-4">Equipo / Marca / Modelo</th>
            <th class="py-3.5 px-4">VIN / Serial</th>
            <th class="py-3.5 px-4">Destinatario</th>
            <th class="py-3.5 px-4">Liquidación Aduanal</th>
            <th class="py-3.5 px-4 text-center">Estado en Ruta</th>
            @if($route->isDraft())
              <th class="py-3.5 px-4 text-right">Acción</th>
            @endif
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60">
          @forelse($route->equipments as $item)
            <tr class="hover:bg-slate-800/40 transition-colors">
              <td class="py-4 px-4 font-mono font-bold text-white">
                <a href="{{ route('tracking.show', $item->tracking_pin) }}" target="_blank" class="text-amber-400 hover:underline">
                  {{ $item->tracking_pin }}
                </a>
              </td>
              <td class="py-4 px-4">
                <span class="font-bold text-white block">{{ $item->brand }} {{ $item->model }}</span>
                <span class="text-[11px] text-slate-400">{{ $item->equipment_type->label() }}</span>
              </td>
              <td class="py-4 px-4 font-mono text-slate-300">
                {{ $item->vin_serial }}
              </td>
              <td class="py-4 px-4">
                <span class="text-white font-medium block">{{ $item->client_data['name'] ?? 'Cliente' }}</span>
                <span class="text-[11px] text-slate-400">{{ $item->client_data['phone'] ?? 'N/A' }}</span>
              </td>
              <td class="py-4 px-4">
                @if($item->duty_paid_abroad)
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                    ✓ Prepagado $0
                  </span>
                @else
                  <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30">
                    Saldo: ${{ number_format($item->getOutstandingBalanceUsd(), 2) }} USD
                  </span>
                @endif
              </td>
              <td class="py-4 px-4 text-center">
                @php $rec = $item->pivot->reception_status; @endphp
                @if($rec === 'received')
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                    ✓ Recibido en Almacén
                  </span>
                @elseif($rec === 'in_transit')
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-500/20 text-blue-300 border border-blue-500/30">
                    🚛 En Carretera
                  </span>
                @else
                  <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                    📦 Asignado en Muelle
                  </span>
                @endif
              </td>
              @if($route->isDraft())
                <td class="py-4 px-4 text-right">
                  <form method="POST" action="{{ route('logistics.routes.items.remove', [$route, $item]) }}" onsubmit="return confirm('¿Remover este bulto de la hoja de ruta?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="p-1.5 rounded-lg bg-rose-950/60 hover:bg-rose-900 text-rose-300 text-xs">
                      <i class="fa-solid fa-trash"></i>
                    </button>
                  </form>
                </td>
              @endif
            </tr>
          @empty
            <tr>
              <td colspan="7" class="py-8 text-center text-slate-500">
                No hay bultos cargados en esta hoja de ruta aún. Usa el escáner superior para agregar paquetes.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
