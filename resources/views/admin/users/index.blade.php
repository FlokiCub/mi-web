@extends('layouts.app', ['title' => 'Gestión de Usuarios'])

@section('page_title', 'Gestión de Usuarios y Roles')

@section('content')
<div class="space-y-6">

  <!-- Header & Actions -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
    <div>
      <h3 class="text-xl font-bold text-white">Listado de Usuarios</h3>
      <p class="text-xs text-slate-400 mt-0.5">Administra los usuarios registrados y sus respectivos roles de acceso.</p>
    </div>
    
    <a href="{{ route('admin.users.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-all">
      <i class="fa-solid fa-user-plus"></i>
      <span>Nuevo Usuario</span>
    </a>
  </div>

  <!-- Filters & Search Bar -->
  <div class="p-4 bg-slate-900/80 border border-slate-800 rounded-2xl shadow-sm">
    <form method="GET" action="{{ route('admin.users.index') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3">
      
      <!-- Search Input -->
      <div class="sm:col-span-6 relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <i class="fa-solid fa-magnifying-glass"></i>
        </div>
        <input 
          type="text" 
          name="search" 
          value="{{ request('search') }}" 
          placeholder="Buscar por nombre, correo o usuario..." 
          class="w-full pl-10 pr-3 py-2 bg-slate-950/70 border border-slate-700 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />
      </div>

      <!-- Role Filter -->
      <div class="sm:col-span-3">
        <select name="role" class="w-full px-3 py-2 bg-slate-950/70 border border-slate-700 rounded-xl text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">Todos los Roles (RBAC)</option>
          @foreach($roles as $roleItem)
            <option value="{{ $roleItem->value }}" {{ request('role') === $roleItem->value ? 'selected' : '' }}>
              {{ $roleItem->label() }}
            </option>
          @endforeach
        </select>
      </div>

      <!-- Status Filter -->
      <div class="sm:col-span-2">
        <select name="status" class="w-full px-3 py-2 bg-slate-950/70 border border-slate-700 rounded-xl text-xs text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500">
          <option value="">Todos los Estados</option>
          <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Activo</option>
          <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactivo</option>
        </select>
      </div>

      <!-- Filter Buttons -->
      <div class="sm:col-span-1 flex items-center gap-1.5">
        <button type="submit" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold flex items-center justify-center transition-colors" title="Filtrar">
          <i class="fa-solid fa-filter"></i>
        </button>
        @if(request('search') || request('role') || request('status') !== null)
          <a href="{{ route('admin.users.index') }}" class="p-2 bg-slate-800 hover:bg-rose-500/20 text-slate-400 hover:text-rose-400 rounded-xl text-xs flex items-center justify-center transition-colors" title="Limpiar filtros">
            ✕
          </a>
        @endif
      </div>

    </form>
  </div>

  <!-- Users Table -->
  <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs text-slate-300">
        <thead class="bg-slate-950/60 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
          <tr>
            <th class="px-6 py-4">ID</th>
            <th class="px-6 py-4">Usuario</th>
            <th class="px-6 py-4">Rol Asignado</th>
            <th class="px-6 py-4">Estado</th>
            <th class="px-6 py-4">Fecha de Registro</th>
            <th class="px-6 py-4 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-800/60">
          @forelse($users as $user)
            <tr class="hover:bg-slate-800/30 transition-colors">
              <td class="px-6 py-4 font-mono text-slate-400">#{{ $user->id }}</td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-sm text-indigo-400">
                    {{ strtoupper(substr($user->name, 0, 2)) }}
                  </div>
                  <div>
                    <div class="flex items-center gap-2">
                      <p class="font-bold text-white text-sm">{{ $user->name }}</p>
                      @if(auth()->id() === $user->id)
                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">Tú</span>
                      @endif
                    </div>
                    <div class="flex items-center gap-2 text-[11px] text-slate-400">
                      <span class="font-mono text-slate-300">{{ $user->email }}</span>
                      @if($user->username)
                        <span>•</span>
                        <span class="font-mono text-slate-400">@<span>{{ $user->username }}</span></span>
                      @endif
                    </div>
                  </div>
                </div>
              </td>
              <td class="px-6 py-4">
                <div class="space-y-1">
                  @php
                    $roleEnum = $user->role instanceof \App\Enums\UserRole ? $user->role : \App\Enums\UserRole::tryFrom((string) $user->role);
                    $badgeColors = match($roleEnum) {
                        \App\Enums\UserRole::DIRECTOR => 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30',
                        \App\Enums\UserRole::ADUANA => 'bg-amber-500/15 text-amber-300 border-amber-500/30',
                        \App\Enums\UserRole::PVP_TECNICO => 'bg-cyan-500/15 text-cyan-300 border-cyan-500/30',
                        \App\Enums\UserRole::DESPACHO_PUERTO => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
                        \App\Enums\UserRole::LOGISTICA_CHOFER => 'bg-blue-500/15 text-blue-300 border-blue-500/30',
                        \App\Enums\UserRole::CAJERO_REGIONAL => 'bg-teal-500/15 text-teal-300 border-teal-500/30',
                        \App\Enums\UserRole::AUDITOR => 'bg-purple-500/15 text-purple-300 border-purple-500/30',
                        default => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
                    };
                    $badgeIcon = match($roleEnum) {
                        \App\Enums\UserRole::DIRECTOR => 'fa-crown',
                        \App\Enums\UserRole::ADUANA => 'fa-shield-halved',
                        \App\Enums\UserRole::PVP_TECNICO => 'fa-screwdriver-wrench',
                        \App\Enums\UserRole::DESPACHO_PUERTO => 'fa-barcode',
                        \App\Enums\UserRole::LOGISTICA_CHOFER => 'fa-truck-fast',
                        \App\Enums\UserRole::CAJERO_REGIONAL => 'fa-cash-register',
                        \App\Enums\UserRole::AUDITOR => 'fa-clipboard-check',
                        default => 'fa-user',
                    };
                  @endphp
                  <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border shadow-xs {{ $badgeColors }}">
                    <i class="fa-solid {{ $badgeIcon }} text-[10px]"></i>
                    {{ $roleEnum ? $roleEnum->label() : ucfirst((string) $user->role) }}
                  </span>
                  @if($user->regionalWarehouse)
                    <div class="text-[11px] text-slate-400 flex items-center gap-1">
                      <i class="fa-solid fa-warehouse text-[9px] text-slate-500"></i>
                      <span>{{ $user->regionalWarehouse->name }}</span>
                    </div>
                  @endif
                </div>
              </td>
              <td class="px-6 py-4">
                <form method="POST" action="{{ route('admin.users.toggle-status', $user) }}" class="inline">
                  @csrf
                  <button 
                    type="submit" 
                    {{ auth()->id() === $user->id ? 'disabled' : '' }}
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold transition-all {{ $user->is_active ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 hover:bg-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20 hover:bg-rose-500/20' }} {{ auth()->id() === $user->id ? 'cursor-not-allowed opacity-70' : '' }}"
                    title="{{ auth()->id() === $user->id ? 'No puedes desactivarte a ti mismo' : 'Clic para alternar estado' }}"
                  >
                    <span class="w-2 h-2 rounded-full {{ $user->is_active ? 'bg-emerald-400 animate-pulse' : 'bg-rose-400' }}"></span>
                    <span>{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span>
                  </button>
                </form>
              </td>
              <td class="px-6 py-4 text-slate-400">
                {{ $user->created_at->format('d/m/Y H:i') }}
              </td>
              <td class="px-6 py-4 text-right space-x-2">
                <!-- Edit -->
                <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-indigo-600 text-slate-300 hover:text-white font-semibold transition-all">
                  <i class="fa-solid fa-pen-to-square"></i>
                  <span>Editar</span>
                </a>

                <!-- Delete -->
                @if(auth()->id() !== $user->id)
                  <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar al usuario {{ $user->name }}? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white font-semibold transition-all">
                      <i class="fa-solid fa-trash-can"></i>
                      <span>Eliminar</span>
                    </button>
                  </form>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                <div class="flex flex-col items-center justify-center">
                  <i class="fa-solid fa-users-slash text-4xl text-slate-600 mb-3"></i>
                  <p class="font-bold text-slate-300 text-sm">No se encontraron usuarios</p>
                  <p class="text-xs text-slate-500 mt-1">Prueba ajustando los filtros de búsqueda.</p>
                </div>
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    @if($users->hasPages())
      <div class="p-4 border-t border-slate-800 bg-slate-950/40">
        {{ $users->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
