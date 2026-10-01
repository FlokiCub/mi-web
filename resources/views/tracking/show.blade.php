<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Seguimiento: {{ $equipment->tracking_pin }} - BLANKISOL SCGI</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
  </style>
</head>
<body class="min-h-full flex flex-col antialiased bg-slate-950 text-slate-100 selection:bg-indigo-500 selection:text-white pb-12">

  <!-- Navigation Bar -->
  <header class="sticky top-0 z-50 bg-slate-950/85 backdrop-blur-xl border-b border-slate-800/80 px-6 py-4">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <a href="{{ route('tracking.index') }}" class="flex items-center gap-3 group">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/30 group-hover:scale-105 transition-transform">
          <i class="fa-solid fa-satellite-dish text-sm"></i>
        </div>
        <div>
          <span class="font-extrabold text-sm tracking-tight text-white block">BLANKISOL</span>
          <span class="text-[9px] font-bold tracking-widest text-indigo-400 uppercase">Trazabilidad SCGI</span>
        </div>
      </a>

      <div class="flex items-center gap-3">
        <a href="{{ route('tracking.index') }}" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 text-xs font-semibold text-slate-300 transition-all">
          <i class="fa-solid fa-arrow-left"></i>
          <span class="hidden sm:inline">Nueva Consulta</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Container -->
  <main class="flex-1 max-w-5xl mx-auto w-full px-4 sm:px-6 py-8 space-y-6">

    <!-- Top Card: Equipment Overview -->
    <div class="p-6 sm:p-8 rounded-3xl bg-slate-900/90 border border-slate-800/90 shadow-2xl backdrop-blur-xl relative overflow-hidden">
      <div class="absolute top-0 right-0 w-96 h-96 bg-indigo-600/10 blur-3xl pointer-events-none -z-10"></div>

      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-800/80">
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-2">
            <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
              PIN: {{ $equipment->tracking_pin }}
            </span>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-slate-800 text-slate-300 border border-slate-700">
              {{ $equipment->business_type->value ?? $equipment->business_type }}
            </span>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-500/15 text-purple-300 border border-purple-500/30">
              {{ is_object($equipment->equipment_type) && method_exists($equipment->equipment_type, 'label') ? $equipment->equipment_type->label() : ucfirst($equipment->equipment_type) }}
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
            {{ $equipment->brand }} {{ $equipment->model }}
          </h1>
          <p class="text-xs text-slate-400 mt-1 font-mono">
            VIN / N° Serie: <strong class="text-slate-200">{{ $equipment->vin_serial }}</strong>
            @if($equipment->solve_cargo_tracking_id)
              • SolveCargo: <strong class="text-indigo-400">{{ $equipment->solve_cargo_tracking_id }}</strong>
            @endif
          </p>
        </div>

        <!-- Live Status Badge -->
        <div class="flex flex-col items-start md:items-end gap-2">
          <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-indigo-500/15 border border-indigo-500/30 shadow-lg shadow-indigo-950/40">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span class="text-xs sm:text-sm font-bold text-indigo-200">
              {{ is_object($equipment->current_status) && method_exists($equipment->current_status, 'label') ? $equipment->current_status->label() : ucfirst(str_replace('_', ' ', $equipment->current_status)) }}
            </span>
          </div>
          @if($equipment->current_location_note)
            <span class="text-xs text-slate-400 flex items-center gap-1.5">
              <i class="fa-solid fa-location-dot text-indigo-400"></i>
              <span>{{ $equipment->current_location_note }}</span>
            </span>
          @endif
        </div>
      </div>

      <!-- Stepper / Lifecycle Progress Bar -->
      <div class="pt-8">
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-6">Fase del Proceso Logístico</h3>
        
        @php
          $stages = [
            ['id' => 'supplier_dispatched', 'name' => 'Proveedor', 'icon' => 'fa-industry'],
            ['id' => 'in_transit_solve_cargo', 'name' => 'Tránsito Marítimo', 'icon' => 'fa-ship'],
            ['id' => 'port_arrival', 'name' => 'Arribo a Puerto', 'icon' => 'fa-anchor'],
            ['id' => 'customs_inspection', 'name' => 'Aduana & Aranceles', 'icon' => 'fa-shield-halved'],
            ['id' => 'in_pvp_assembly', 'name' => 'Patio PVP Ensamblaje', 'icon' => 'fa-wrench'],
            ['id' => 'regional_transit', 'name' => 'Distribución Regional', 'icon' => 'fa-truck-fast'],
            ['id' => 'delivered', 'name' => 'Entregado', 'icon' => 'fa-circle-check'],
          ];

          $stageOrder = array_column($stages, 'id');
          $currentStatusCode = is_object($equipment->current_status) ? $equipment->current_status->value : $equipment->current_status;
          $currentIndex = array_search($currentStatusCode, $stageOrder);
          if ($currentIndex === false) $currentIndex = 2; // fallback
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
          @foreach($stages as $index => $stage)
            @php
              $isCompleted = $index <= $currentIndex;
              $isCurrent = $index === $currentIndex;
            @endphp
            <div class="p-3 rounded-2xl border text-center transition-all {{ $isCurrent ? 'bg-indigo-600/20 border-indigo-500 shadow-lg shadow-indigo-950/40' : ($isCompleted ? 'bg-slate-950/70 border-emerald-500/40 text-slate-300' : 'bg-slate-950/30 border-slate-800/60 opacity-40') }}">
              <div class="w-8 h-8 rounded-xl mx-auto flex items-center justify-center text-sm mb-2 {{ $isCurrent ? 'bg-indigo-600 text-white animate-bounce' : ($isCompleted ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-800 text-slate-500') }}">
                <i class="fa-solid {{ $stage['icon'] }}"></i>
              </div>
              <p class="text-[11px] font-bold text-white line-clamp-1">{{ $stage['name'] }}</p>
              <span class="text-[9px] font-semibold mt-0.5 block {{ $isCurrent ? 'text-indigo-400' : ($isCompleted ? 'text-emerald-400' : 'text-slate-500') }}">
                {{ $isCurrent ? 'En Proceso' : ($isCompleted ? 'Completado' : 'Pendiente') }}
              </span>
            </div>
          @endforeach
        </div>
      </div>

    </div>

    <!-- 2 Column Details: Timeline + Technical & Financial Info -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Left Column: Detailed Timeline Log (2 cols) -->
      <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800/90 rounded-3xl p-6 sm:p-8 shadow-xl">
        <div class="flex items-center justify-between pb-4 border-b border-slate-800 mb-6">
          <div>
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
              <i class="fa-solid fa-clock-rotate-left text-indigo-400"></i>
              <span>Bitácora de Trazabilidad</span>
            </h2>
            <p class="text-xs text-slate-400 mt-0.5">Historial inmutable de cambios y custodia</p>
          </div>
          <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 font-mono text-xs">
            {{ $equipment->statusHistories->count() }} eventos
          </span>
        </div>

        <!-- Timeline list -->
        <div class="relative pl-6 space-y-6 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-800">
          @forelse($equipment->statusHistories as $history)
            <div class="relative group">
              <!-- Bullet -->
              <div class="absolute -left-6 top-1 w-4 h-4 rounded-full bg-slate-900 border-2 border-indigo-500 group-hover:scale-125 transition-transform"></div>

              <div class="p-4 rounded-2xl bg-slate-950/70 border border-slate-800/80 hover:border-slate-700 transition-colors">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 mb-1.5">
                  <h3 class="text-sm font-bold text-white">
                    {{ ucfirst(str_replace('_', ' ', $history->to_status)) }}
                  </h3>
                  <span class="text-[11px] font-mono text-slate-400">
                    {{ $history->recorded_at ? $history->recorded_at->format('d/m/Y H:i') : '' }}
                  </span>
                </div>

                @if($history->location)
                  <p class="text-xs text-indigo-400 font-medium flex items-center gap-1.5 mb-1">
                    <i class="fa-solid fa-map-pin text-[10px]"></i>
                    <span>{{ $history->location }}</span>
                  </p>
                @endif

                @if($history->notes)
                  <p class="text-xs text-slate-300 leading-relaxed bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/60 mt-2">
                    {{ $history->notes }}
                  </p>
                @endif
              </div>
            </div>
          @empty
            <div class="p-6 text-center text-xs text-slate-500">
              No hay eventos adicionales registrados.
            </div>
          @endforelse
        </div>
      </div>

      <!-- Right Column: Specs, Customs & Actions (1 col) -->
      <div class="space-y-6">

        <!-- Technical Specs Card -->
        <div class="p-6 rounded-3xl bg-slate-900/80 border border-slate-800/90 shadow-xl space-y-4">
          <h2 class="text-sm font-bold text-white flex items-center gap-2 pb-3 border-b border-slate-800">
            <i class="fa-solid fa-microchip text-indigo-400"></i>
            <span>Ficha Técnica</span>
          </h2>

          <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
              <span class="text-slate-400">Marca / Modelo:</span>
              <strong class="text-white">{{ $equipment->brand }} {{ $equipment->model }}</strong>
            </div>

            @if($equipment->color)
              <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-slate-400">Color:</span>
                <strong class="text-slate-200">{{ $equipment->color }}</strong>
              </div>
            @endif

            @if(!empty($equipment->technical_specs))
              @foreach($equipment->technical_specs as $key => $val)
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
                  <span class="text-slate-400 capitalize">{{ str_replace('_', ' ', $key) }}:</span>
                  <strong class="text-indigo-300 font-mono">{{ is_array($val) ? json_encode($val) : $val }}</strong>
                </div>
              @endforeach
            @endif
          </div>
        </div>

        <!-- Customs & Financial Summary Card -->
        <div class="p-6 rounded-3xl bg-slate-900/80 border border-slate-800/90 shadow-xl space-y-4">
          <h2 class="text-sm font-bold text-white flex items-center gap-2 pb-3 border-b border-slate-800">
            <i class="fa-solid fa-file-invoice-dollar text-emerald-400"></i>
            <span>Estado Aduanal & Entrega</span>
          </h2>

          <div class="space-y-2 text-xs">
            <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
              <span class="text-slate-400">Arancel Aduanal:</span>
              @if($equipment->duty_paid_abroad)
                <span class="font-bold text-emerald-400">✓ Pagado en Origen (B2B)</span>
              @else
                <span class="font-bold text-amber-400">Regulado en Aduana (B2C)</span>
              @endif
            </div>

            <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
              <span class="text-slate-400">Envío a Domicilio ($50 USD):</span>
              @if($equipment->home_delivery_requested)
                <span class="font-bold text-indigo-400">✓ Solicitado</span>
              @else
                <span class="font-bold text-slate-400">Retiro en Sucursal</span>
              @endif
            </div>

            @if($equipment->currentWarehouse)
              <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
                <span class="text-slate-400">Almacén Regional:</span>
                <strong class="text-white">{{ $equipment->currentWarehouse->name }}</strong>
              </div>
            @endif
          </div>
        </div>

        <!-- WhatsApp Direct Contact / Notification -->
        @php
          $waText = urlencode("Hola equipo BLANKISOL, deseo consultar el estado de mi equipo PIN: {$equipment->tracking_pin} ({$equipment->brand} {$equipment->model}) VIN: {$equipment->vin_serial}.");
        @endphp
        <div class="p-6 rounded-3xl bg-gradient-to-br from-emerald-950/50 to-slate-900 border border-emerald-500/30 shadow-xl text-center space-y-3">
          <div class="w-10 h-10 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 mx-auto flex items-center justify-center text-lg">
            <i class="fa-brands fa-whatsapp"></i>
          </div>
          <div>
            <h2 class="text-sm font-bold text-white">¿Deseas soporte o notificaciones?</h2>
            <p class="text-xs text-slate-400 mt-0.5">Recibe asistencia inmediata de un asesor de BLANKISOL.</p>
          </div>
          <a 
            href="https://wa.me/?text={{ $waText }}" 
            target="_blank"
            class="w-full inline-flex items-center justify-center gap-2 py-3 px-4 rounded-2xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-950/50 active:scale-95 transition-all"
          >
            <i class="fa-brands fa-whatsapp text-sm"></i>
            <span>Contactar vía WhatsApp</span>
          </a>
        </div>

      </div>

    </div>

  </main>

  <footer class="border-t border-slate-800/80 py-6 px-6 text-center text-xs text-slate-400 mt-auto">
    <span>BLANKISOL SCGI — Trazabilidad Logística Inteligente</span>
  </footer>

</body>
</html>
