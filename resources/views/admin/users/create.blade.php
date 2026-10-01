@extends('layouts.app', ['title' => 'Crear Usuario'])

@section('page_title', 'Crear Nuevo Usuario')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

  <div class="flex items-center justify-between">
    <div>
      <h3 class="text-xl font-bold text-white">Registrar Nuevo Usuario</h3>
      <p class="text-xs text-slate-400 mt-0.5">Completa los campos para crear una nueva cuenta y asignar su rol.</p>
    </div>
    <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-all">
      <i class="fa-solid fa-arrow-left"></i>
      <span>Volver a la lista</span>
    </a>
  </div>

  <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl">
    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
      @csrf

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        
        <!-- Name -->
        <div class="sm:col-span-2">
          <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
            Nombre Completo <span class="text-rose-400">*</span>
          </label>
          <input 
            type="text" 
            id="name" 
            name="name" 
            value="{{ old('name') }}" 
            required 
            placeholder="Ej: Laura Martínez"
            class="w-full px-4 py-2.5 bg-slate-950/80 border @error('name') border-rose-500 @else border-slate-700 @enderror rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          @error('name')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
          @enderror
        </div>

        <!-- Username -->
        <div>
          <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
            Nombre de Usuario (opcional)
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 text-xs">@</span>
            <input 
              type="text" 
              id="username" 
              name="username" 
              value="{{ old('username') }}" 
              placeholder="lauram"
              class="w-full pl-8 pr-4 py-2.5 bg-slate-950/80 border @error('username') border-rose-500 @else border-slate-700 @enderror rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          @error('username')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
          @enderror
        </div>

        <!-- Email -->
        <div>
          <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
            Correo Electrónico <span class="text-rose-400">*</span>
          </label>
          <input 
            type="email" 
            id="email" 
            name="email" 
            value="{{ old('email') }}" 
            required 
            placeholder="laura@ejemplo.com"
            class="w-full px-4 py-2.5 bg-slate-950/80 border @error('email') border-rose-500 @else border-slate-700 @enderror rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          @error('email')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
          @enderror
        </div>

        <!-- Password -->
        <div>
          <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
            Contraseña <span class="text-rose-400">*</span>
          </label>
          <input 
            type="password" 
            id="password" 
            name="password" 
            required 
            placeholder="Mínimo 4 caracteres"
            class="w-full px-4 py-2.5 bg-slate-950/80 border @error('password') border-rose-500 @else border-slate-700 @enderror rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          @error('password')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
          @enderror
        </div>

        <!-- Phone -->
        <div>
          <label for="phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
            Teléfono Móvil
          </label>
          <input 
            type="text" 
            id="phone" 
            name="phone" 
            value="{{ old('phone') }}" 
            placeholder="+53 5 123 4567"
            class="w-full px-4 py-2.5 bg-slate-950/80 border @error('phone') border-rose-500 @else border-slate-700 @enderror rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          />
          @error('phone')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
          @enderror
        </div>

        <!-- Role Selector -->
        <div>
          <label for="role" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
            Rol Corporativo (RBAC) <span class="text-rose-400">*</span>
          </label>
          <select 
            id="role" 
            name="role" 
            required
            class="w-full px-4 py-2.5 bg-slate-950/80 border @error('role') border-rose-500 @else border-slate-700 @enderror rounded-xl text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            @foreach($roles as $roleItem)
              <option value="{{ $roleItem->value }}" {{ old('role') === $roleItem->value ? 'selected' : '' }}>
                {{ $roleItem->label() }}
              </option>
            @endforeach
          </select>
          @error('role')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
          @enderror
        </div>

        <!-- Regional Warehouse Selector -->
        <div>
          <label for="regional_warehouse_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
            Almacén Regional Asignado (Obligatorio para Cajeros)
          </label>
          <select 
            id="regional_warehouse_id" 
            name="regional_warehouse_id" 
            class="w-full px-4 py-2.5 bg-slate-950/80 border @error('regional_warehouse_id') border-rose-500 @else border-slate-700 @enderror rounded-xl text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option value="">-- Sin Almacén Fijo (Nivel Central) --</option>
            @foreach($warehouses as $wh)
              <option value="{{ $wh->id }}" {{ old('regional_warehouse_id') === $wh->id ? 'selected' : '' }}>
                🏢 {{ $wh->name }} ({{ $wh->province }})
              </option>
            @endforeach
          </select>
          @error('regional_warehouse_id')
            <p class="mt-1 text-xs text-rose-400 font-medium">{{ $message }}</p>
          @enderror
        </div>

      </div>

      <!-- Active state toggle -->
      <div class="pt-4 border-t border-slate-800 flex items-center gap-3">
        <input 
          type="checkbox" 
          id="is_active" 
          name="is_active" 
          value="1" 
          {{ old('is_active', '1') == '1' ? 'checked' : '' }}
          class="w-4 h-4 rounded bg-slate-950 border-slate-700 text-indigo-600 focus:ring-indigo-500"
        />
        <label for="is_active" class="text-xs text-slate-200 select-none cursor-pointer">
          <span class="font-bold">Cuenta Activa:</span> El usuario podrá iniciar sesión inmediatamente.
        </label>
      </div>

      <!-- Form Actions -->
      <div class="pt-4 flex items-center justify-end gap-3">
        <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition-all">
          Cancelar
        </a>
        <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold shadow-lg shadow-indigo-600/30 active:scale-95 transition-all">
          Guardar Usuario
        </button>
      </div>

    </form>
  </div>

</div>
@endsection
