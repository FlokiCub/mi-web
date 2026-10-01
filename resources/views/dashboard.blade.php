@extends('layouts.app', ['title' => 'Mi Perfil & Panel'])

@section('page_title', 'Panel de Usuario')

@section('content')
<div class="space-y-6">

  <!-- Welcome banner -->
  <div class="p-6 rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950/70 to-slate-900 border border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-xl">
    <div class="flex items-center gap-4">
      <div class="w-14 h-14 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 flex items-center justify-center font-bold text-xl">
        {{ strtoupper(substr($user->name, 0, 2)) }}
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h3 class="text-xl font-black text-white">{{ $user->name }}</h3>
          @if($user->isAdmin())
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
              Admin
            </span>
          @else
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
              Usuario Estándar
            </span>
          @endif
        </div>
        <p class="text-xs text-slate-400 mt-0.5">{{ $user->email }}</p>
      </div>
    </div>

    @if($user->isAdmin())
      <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-md shadow-indigo-600/30 active:scale-95 transition-all">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Ir al Panel de Administración</span>
      </a>
    @endif
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <!-- Account Information Card -->
    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm space-y-4">
      <h4 class="text-sm font-bold text-white flex items-center gap-2 pb-3 border-b border-slate-800">
        <i class="fa-solid fa-id-badge text-indigo-400"></i>
        <span>Información de tu Cuenta</span>
      </h4>

      <div class="space-y-3 text-xs">
        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
          <span class="text-slate-400">Nombre de Usuario:</span>
          <span class="font-mono font-bold text-white">{{ $user->username ?? 'No especificado' }}</span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
          <span class="text-slate-400">Correo Electrónico:</span>
          <span class="font-mono font-bold text-indigo-400">{{ $user->email }}</span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
          <span class="text-slate-400">Rol Asignado:</span>
          <span class="font-bold text-white">{{ $user->isAdmin() ? 'Administrador del Sistema' : 'Usuario Estándar' }}</span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
          <span class="text-slate-400">Estado de Cuenta:</span>
          <span class="inline-flex items-center gap-1.5 font-bold text-emerald-400">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Activa
          </span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
          <span class="text-slate-400">Miembro desde:</span>
          <span class="text-slate-300">{{ $user->created_at->format('d/m/Y') }}</span>
        </div>
      </div>
    </div>

    <!-- Permissions and Info Card -->
    <div class="p-6 rounded-2xl bg-slate-900/80 border border-slate-800 shadow-sm space-y-4">
      <h4 class="text-sm font-bold text-white flex items-center gap-2 pb-3 border-b border-slate-800">
        <i class="fa-solid fa-lock-open text-emerald-400"></i>
        <span>Permisos y Capacidades</span>
      </h4>

      <ul class="space-y-2.5 text-xs text-slate-300">
        <li class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-950/40 border border-slate-800/60">
          <i class="fa-solid fa-circle-check text-emerald-400"></i>
          <span>Acceso seguro a través de sesiones autenticadas</span>
        </li>
        <li class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-950/40 border border-slate-800/60">
          <i class="fa-solid fa-circle-check text-emerald-400"></i>
          <span>Protección contra vulnerabilidades CSRF y XSS</span>
        </li>
        @if($user->isAdmin())
          <li class="flex items-center gap-3 p-2.5 rounded-xl bg-indigo-950/40 border border-indigo-800/60 text-indigo-200">
            <i class="fa-solid fa-crown text-indigo-400"></i>
            <span>Permiso especial: Crear, modificar y asignar roles a otros usuarios</span>
          </li>
        @else
          <li class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-950/40 border border-slate-800/60 text-slate-400">
            <i class="fa-solid fa-shield text-slate-500"></i>
            <span>Panel de administración reservado para el rol Administrador</span>
          </li>
        @endif
      </ul>
    </div>

  </div>

</div>
@endsection
