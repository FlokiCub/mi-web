<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal de Trazabilidad y Tracking - BLANKISOL SCGI</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Plus Jakarta Sans', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace; }
    @keyframes pulseGlow {
      0%, 100% { opacity: 0.4; transform: scale(1); }
      50% { opacity: 0.7; transform: scale(1.05); }
    }
    .glow-bg {
      animation: pulseGlow 6s ease-in-out infinite;
    }
  </style>
</head>
<body class="min-h-full flex flex-col antialiased bg-slate-950 text-slate-100 selection:bg-indigo-500 selection:text-white relative overflow-x-hidden">

  <!-- Ambient background glow effects -->
  <div class="fixed top-0 left-1/2 -translate-x-1/2 w-[800px] h-[400px] bg-gradient-to-b from-indigo-600/20 via-purple-600/10 to-transparent blur-[120px] pointer-events-none glow-bg -z-10"></div>
  <div class="fixed bottom-0 right-0 w-[500px] h-[300px] bg-emerald-600/10 blur-[140px] pointer-events-none -z-10"></div>

  <!-- Header / Navigation -->
  <header class="sticky top-0 z-50 bg-slate-950/80 backdrop-blur-xl border-b border-slate-800/80 px-6 py-4">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <!-- Logo -->
      <a href="{{ route('home') }}" class="flex items-center gap-3 group">
        <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-indigo-500 via-indigo-600 to-purple-600 flex items-center justify-center text-white shadow-lg shadow-indigo-500/25 group-hover:scale-105 transition-transform duration-300">
          <i class="fa-solid fa-satellite-dish text-base"></i>
        </div>
        <div>
          <span class="font-extrabold text-base tracking-tight text-white block">BLANKISOL</span>
          <span class="text-[10px] font-bold tracking-widest text-indigo-400 uppercase">Torre de Control SCGI</span>
        </div>
      </a>

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-slate-700 text-xs font-semibold text-slate-300 transition-all">
          <i class="fa-solid fa-lock text-indigo-400"></i>
          <span>Acceso Operadores</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Hero & Search Box -->
  <main class="flex-1 flex flex-col justify-center items-center px-6 py-16 max-w-4xl mx-auto w-full text-center">
    
    <!-- Badge -->
    <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-indigo-950/60 border border-indigo-500/30 text-indigo-300 text-xs font-semibold mb-6 shadow-inner backdrop-blur-md">
      <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
      <span>Integración SolveCargo & Aduana en Tiempo Real</span>
    </div>

    <!-- Title -->
    <h1 class="text-3xl sm:text-5xl md:text-6xl font-black text-white tracking-tight leading-tight sm:leading-none max-w-3xl">
      Rastrea tu equipo con <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-300 to-pink-400">precisión total</span>
    </h1>

    <p class="mt-5 text-sm sm:text-base text-slate-400 max-w-xl leading-relaxed">
      Ingresa el código PIN único de tu vehículo eléctrico o sistema solar para conocer su ubicación exacta, estado aduanal y fecha estimada de entrega.
    </p>

    <!-- Search Card -->
    <div class="mt-10 w-full max-w-2xl bg-slate-900/90 border border-slate-800/90 rounded-3xl p-4 sm:p-5 shadow-2xl shadow-indigo-950/50 backdrop-blur-2xl">
      
      @if(session('error'))
        <div class="mb-4 p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-medium flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <i class="fa-solid fa-circle-exclamation text-sm"></i>
            <span>{{ session('error') }}</span>
          </div>
          <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200">✕</button>
        </div>
      @endif

      <form method="POST" action="{{ route('tracking.search') }}" class="flex flex-col sm:flex-row gap-3">
        @csrf
        <div class="relative flex-1">
          <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
            <i class="fa-solid fa-barcode text-base text-indigo-400"></i>
          </div>
          <input 
            type="text" 
            name="pin" 
            value="{{ old('pin') }}" 
            required 
            autocomplete="off"
            placeholder="Introduce tu PIN (Ej: BK-78492) o VIN / Serial"
            class="w-full pl-12 pr-4 py-4 rounded-2xl bg-slate-950/90 border @error('pin') border-rose-500 @else border-slate-700/80 @enderror text-white placeholder-slate-500 font-mono text-sm sm:text-base focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all uppercase tracking-wider"
          />
        </div>

        <button 
          type="submit" 
          class="px-8 py-4 rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold text-sm sm:text-base shadow-xl shadow-indigo-600/30 hover:shadow-indigo-600/50 active:scale-98 transition-all flex items-center justify-center gap-2"
        >
          <i class="fa-solid fa-magnifying-glass text-sm"></i>
          <span>Consultar</span>
        </button>
      </form>

      <!-- Quick demo test pins -->
      <div class="mt-4 pt-4 border-t border-slate-800/80 flex flex-wrap items-center justify-between gap-2 text-xs">
        <span class="text-slate-400 text-[11px] font-medium">Ejemplos de búsqueda:</span>
        <div class="flex flex-wrap gap-2">
          <button type="button" onclick="setPin('BK-78492')" class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-indigo-600/20 border border-slate-800 hover:border-indigo-500/40 text-slate-300 font-mono text-[11px] transition-colors">
            BK-78492 (Vehículo)
          </button>
          <button type="button" onclick="setPin('BK-SOLAR01')" class="px-2.5 py-1 rounded-lg bg-slate-950 hover:bg-indigo-600/20 border border-slate-800 hover:border-indigo-500/40 text-slate-300 font-mono text-[11px] transition-colors">
            BK-SOLAR01 (Solar)
          </button>
        </div>
      </div>

    </div>

    <!-- 3 Step Process -->
    <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-6 w-full text-left">
      <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 hover:border-indigo-500/30 transition-all group">
        <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl font-black mb-4 group-hover:scale-110 transition-transform">
          1
        </div>
        <h3 class="font-bold text-base text-white mb-1">Despacho & Tránsito</h3>
        <p class="text-xs text-slate-400 leading-relaxed">
          Seguimiento del contenedor en altamar a través de la integración de SolveCargo.
        </p>
      </div>

      <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 hover:border-purple-500/30 transition-all group">
        <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-xl font-black mb-4 group-hover:scale-110 transition-transform">
          2
        </div>
        <h3 class="font-bold text-base text-white mb-1">Aduana & Ensamblaje</h3>
        <p class="text-xs text-slate-400 leading-relaxed">
          Inspección arancelaria, validación B2B/B2C y puesta a punto técnica en patio PVP.
        </p>
      </div>

      <div class="p-6 rounded-3xl bg-slate-900/50 border border-slate-800/80 hover:border-emerald-500/30 transition-all group">
        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl font-black mb-4 group-hover:scale-110 transition-transform">
          3
        </div>
        <h3 class="font-bold text-base text-white mb-1">Distribución & Entrega</h3>
        <p class="text-xs text-slate-400 leading-relaxed">
          Recepción en caja regional o entrega directa en el domicilio del cliente final.
        </p>
      </div>
    </div>

  </main>

  <!-- Footer -->
  <footer class="border-t border-slate-800/80 py-6 px-6 text-center text-xs text-slate-400 mt-auto">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
      <span>BLANKISOL &copy; {{ date('Y') }} — Torre de Control SCGI</span>
      <div class="flex items-center gap-4 text-slate-400">
        <span class="flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Sistemas Operativos
        </span>
      </div>
    </div>
  </footer>

  <script>
    function setPin(val) {
      const input = document.querySelector('input[name="pin"]');
      input.value = val;
      input.focus();
    }
  </script>
</body>
</html>
