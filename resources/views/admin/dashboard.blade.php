@extends('layouts.app', ['title' => 'Dashboard Administrativo'])

@section('page_title', 'Dashboard de Administración')

@section('content')
<div class="space-y-6">

  <!-- Welcome banner -->
  <div class="p-6 rounded-2xl bg-gradient-to-r from-indigo-900/60 via-purple-900/40 to-slate-900 border border-indigo-500/20 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-xl">
    <div>
      <span class="px-2.5 py-1 rounded-md bg-indigo-500/20 text-indigo-300 text-xs font-semibold uppercase tracking-wider border border-indigo-500/30">
        Panel de Control Fullstack
      </span>
      <h3 class="text-2xl font-black text-white mt-2">
        ¡Hola, {{ auth()->user()->name }}! 👋
      </h3>
      <p class="text-xs text-slate-300 mt-1 max-w-xl">
        Aquí tienes el resumen completo del sistema, métricas de usuarios registrados y control de roles y accesos.
      </p>
    </div>
    <div class="flex items-center gap-3">
      <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-all">
        <i class="fa-solid fa-user-plus"></i>
        <span>Crear Usuario</span>
      </a>
      <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition-all">
        <i class="fa-solid fa-users"></i>
        <span>Ver Usuarios</span>
      </a>
    </div>
  </div>

  <!-- Metric Cards Grid -->
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
    
    <!-- Total Users -->
    <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-sm hover:border-slate-700 transition-all">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Usuarios</p>
        <h4 class="text-3xl font-black text-white mt-1">{{ $totalUsers }}</h4>
        <span class="text-[11px] text-slate-500 mt-0.5 block">Registrados en MySQL</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl">
        <i class="fa-solid fa-users"></i>
      </div>
    </div>

    <!-- Admins -->
    <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-sm hover:border-slate-700 transition-all">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Administradores</p>
        <h4 class="text-3xl font-black text-indigo-400 mt-1">{{ $adminCount }}</h4>
        <span class="text-[11px] text-indigo-400/70 mt-0.5 block">Permisos Totales</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl">
        <i class="fa-solid fa-user-shield"></i>
      </div>
    </div>

    <!-- Standard Users -->
    <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-sm hover:border-slate-700 transition-all">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Usuarios Estándar</p>
        <h4 class="text-3xl font-black text-purple-400 mt-1">{{ $standardUserCount }}</h4>
        <span class="text-[11px] text-purple-400/70 mt-0.5 block">Rol de Usuario</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-xl">
        <i class="fa-solid fa-user"></i>
      </div>
    </div>

    <!-- Active Accounts -->
    <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 flex items-center justify-between shadow-sm hover:border-slate-700 transition-all">
      <div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Cuentas Activas</p>
        <h4 class="text-3xl font-black text-emerald-400 mt-1">{{ $activeUserCount }}</h4>
        <span class="text-[11px] text-emerald-400/70 mt-0.5 block">Acceso Habilitado</span>
      </div>
      <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl">
        <i class="fa-solid fa-circle-check"></i>
      </div>
    </div>

  </div>

  <!-- Recent Users Table & System Details -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Recent Users Table (2 cols) -->
    <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
      <div class="p-5 border-b border-slate-800 flex items-center justify-between">
        <div>
          <h4 class="text-base font-bold text-white">Usuarios Recientes</h4>
          <p class="text-xs text-slate-400">Últimos registros en la base de datos</p>
        </div>
        <a href="{{ route('admin.users.index') }}" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">
          Ver todos →
        </a>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
          <thead class="bg-slate-950/60 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
            <tr>
              <th class="px-5 py-3.5">Usuario</th>
              <th class="px-5 py-3.5">Rol</th>
              <th class="px-5 py-3.5">Estado</th>
              <th class="px-5 py-3.5 text-right">Acción</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            @forelse($recentUsers as $user)
              <tr class="hover:bg-slate-800/40 transition-colors">
                <td class="px-5 py-3.5">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-indigo-400">
                      {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <div>
                      <p class="font-bold text-white">{{ $user->name }}</p>
                      <p class="text-[11px] text-slate-400 font-mono">{{ $user->email }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3.5">
                  @if($user->isAdmin())
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-500/15 text-indigo-300 border border-indigo-500/30">
                      <i class="fa-solid fa-crown text-[9px]"></i> Administrador
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-purple-500/15 text-purple-300 border border-purple-500/30">
                      <i class="fa-solid fa-user text-[9px]"></i> Usuario
                    </span>
                  @endif
                </td>
                <td class="px-5 py-3.5">
                  @if($user->is_active)
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                      <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Activo
                    </span>
                  @else
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/15 text-rose-400 border border-rose-500/30">
                      <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Inactivo
                    </span>
                  @endif
                </td>
                <td class="px-5 py-3.5 text-right">
                  <a href="{{ route('admin.users.edit', $user) }}" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white font-semibold transition-all">
                    Editar
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="px-5 py-8 text-center text-slate-500">
                  No hay usuarios registrados.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    <!-- System info card (1 col) -->
    <div class="space-y-4">
      <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm space-y-4">
        <h4 class="text-sm font-bold text-white flex items-center gap-2">
          <i class="fa-solid fa-server text-indigo-400"></i>
          <span>Entorno de Ejecución</span>
        </h4>
        
        <div class="space-y-2.5 text-xs">
          <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
            <span class="text-slate-400">Framework:</span>
            <span class="font-bold text-white">Laravel {{ app()->version() }}</span>
          </div>
          <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
            <span class="text-slate-400">Versión PHP:</span>
            <span class="font-bold text-indigo-400">PHP {{ phpversion() }}</span>
          </div>
          <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
            <span class="text-slate-400">Base de Datos:</span>
            <span class="font-bold text-emerald-400">MySQL (mi_web)</span>
          </div>
          <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 border border-slate-800">
            <span class="text-slate-400">Servidor Web:</span>
            <span class="font-bold text-purple-400">Apache (XAMPP)</span>
          </div>
        </div>
      </div>

      <!-- Quick Role Info -->
      <div class="p-5 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm">
        <h4 class="text-sm font-bold text-white flex items-center gap-2 mb-3">
          <i class="fa-solid fa-key text-purple-400"></i>
          <span>Gestión de Roles</span>
        </h4>
        <ul class="text-xs space-y-2 text-slate-300">
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-check text-indigo-400 mt-0.5"></i>
            <span><strong>Admin:</strong> Control total de usuarios, roles, métricas y configuración.</span>
          </li>
          <li class="flex items-start gap-2">
            <i class="fa-solid fa-check text-purple-400 mt-0.5"></i>
            <span><strong>Usuario:</strong> Acceso a perfil y funciones estándar del sistema.</span>
          </li>
        </ul>
      </div>
    </div>

  </div>

</div>
@endsection
