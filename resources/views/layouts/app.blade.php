<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-900 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? 'Panel' }} - {{ config('app.name') }}</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    [x-cloak] { display: none !important; }
  </style>
</head>
<body class="h-full flex antialiased bg-slate-950 text-slate-100 selection:bg-indigo-500 selection:text-white">

  <!-- Sidebar -->
  <aside class="w-64 bg-slate-900/90 border-r border-slate-800 flex flex-col shrink-0">
    <!-- Brand -->
    <div class="h-16 flex items-center gap-3 px-6 border-b border-slate-800">
      <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-500/30">
        <i class="fa-solid fa-shield-halved text-sm"></i>
      </div>
      <div>
        <h1 class="font-extrabold text-sm tracking-tight text-white">Laravel Fullstack</h1>
        <p class="text-[11px] text-slate-400 font-medium">Panel Administrativo</p>
      </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
      @if(auth()->user()->isAdmin())
        <div class="px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
          Administración
        </div>
        
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.dashboard') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
          <i class="fa-solid fa-chart-pie w-5 text-center {{ request()->routeIs('admin.dashboard') ? 'text-white' : 'text-indigo-400' }}"></i>
          <span>Dashboard</span>
        </a>

        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('admin.users.*') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
          <i class="fa-solid fa-users-gear w-5 text-center {{ request()->routeIs('admin.users.*') ? 'text-white' : 'text-purple-400' }}"></i>
          <span>Usuarios & Roles</span>
        </a>

        <div class="pt-4 px-3 pb-2 text-[11px] font-bold uppercase tracking-wider text-slate-400">
          Acceso Rápido
        </div>
      @endif

      @if(auth()->user()->hasRole([\App\Enums\UserRole::ADUANA, \App\Enums\UserRole::DIRECTOR]))
        <a href="{{ route('customs.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('customs.*') ? 'bg-amber-600 text-white shadow-md shadow-amber-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
          <i class="fa-solid fa-anchor w-5 text-center {{ request()->routeIs('customs.*') ? 'text-white' : 'text-orange-400' }}"></i>
          <span>Inspección Aduanal</span>
        </a>
      @endif

      @if(auth()->user()->hasRole([\App\Enums\UserRole::CAJERO_REGIONAL, \App\Enums\UserRole::DIRECTOR]))
        <a href="{{ route('cashier.index') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('cashier.*') ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
          <i class="fa-solid fa-cash-register w-5 text-center {{ request()->routeIs('cashier.*') ? 'text-white' : 'text-emerald-400' }}"></i>
          <span>Caja Regional (Cobros)</span>
        </a>
      @endif

      <a href="{{ route('dispatch.scanner') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('dispatch.scanner') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
        <i class="fa-solid fa-barcode w-5 text-center {{ request()->routeIs('dispatch.scanner') ? 'text-white' : 'text-amber-400' }}"></i>
        <span>Escáner Despacho Cuba</span>
      </a>

      <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
        <i class="fa-solid fa-id-card w-5 text-center {{ request()->routeIs('dashboard') ? 'text-white' : 'text-emerald-400' }}"></i>
        <span>Mi Perfil / Panel</span>
      </a>

      <a href="{{ route('tracking.index') }}" target="_blank" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-300 hover:bg-slate-800 hover:text-white transition-all">
        <i class="fa-solid fa-satellite-dish w-5 text-center text-cyan-400"></i>
        <span>Portal Tracking Público ↗</span>
      </a>
    </nav>

    <!-- Current User Box in Sidebar -->
    <div class="p-4 border-t border-slate-800 bg-slate-900/50">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-indigo-400 text-sm">
          {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-xs font-bold text-white truncate">{{ auth()->user()->name }}</p>
          <div class="flex items-center gap-1.5 mt-0.5">
            @if(auth()->user()->isAdmin())
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                Admin
              </span>
            @else
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                Usuario
              </span>
            @endif
          </div>
        </div>
      </div>
    </div>
  </aside>

  <!-- Main Content Area -->
  <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
    
    <!-- Top Header -->
    <header class="h-16 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-8 z-10">
      <div>
        <h2 class="text-lg font-bold text-white tracking-tight">
          @yield('page_title', 'Panel')
        </h2>
      </div>

      <div class="flex items-center gap-4">
        <div class="hidden sm:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800/80 border border-slate-700 text-xs text-slate-300">
          <i class="fa-solid fa-server text-emerald-400"></i>
          <span>XAMPP / MySQL: <strong class="text-white">mi_web</strong></span>
        </div>

        <!-- Logout Button -->
        <form method="POST" action="{{ route('logout') }}">
          @csrf
          <button type="submit" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-rose-500/20 text-slate-300 hover:text-rose-300 border border-slate-700 hover:border-rose-500/40 text-xs font-semibold transition-all">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Cerrar Sesión</span>
          </button>
        </form>
      </div>
    </header>

    <!-- Page Body -->
    <main class="flex-1 overflow-y-auto p-8">
      <div class="max-w-6xl mx-auto space-y-6">

        {{-- Flash Alerts --}}
        @if(session('success'))
          <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm font-medium flex items-center justify-between shadow-lg shadow-emerald-950/20 animate-fade-in">
            <div class="flex items-center gap-3">
              <i class="fa-solid fa-circle-check text-lg text-emerald-400"></i>
              <span>{{ session('success') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200">✕</button>
          </div>
        @endif

        @if(session('error'))
          <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm font-medium flex items-center justify-between shadow-lg shadow-rose-950/20 animate-fade-in">
            <div class="flex items-center gap-3">
              <i class="fa-solid fa-triangle-exclamation text-lg text-rose-400"></i>
              <span>{{ session('error') }}</span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-rose-400 hover:text-rose-200">✕</button>
          </div>
        @endif

        @yield('content')

      </div>
    </main>
  </div>

</body>
</html>
