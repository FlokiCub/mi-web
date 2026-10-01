@extends('layouts.guest', ['title' => 'Iniciar Sesión', 'heading' => 'Ingreso al Sistema'])

@section('content')
<div class="bg-slate-800/80 backdrop-blur-xl border border-slate-700/80 py-8 px-6 shadow-2xl rounded-2xl sm:px-10">

  {{-- Flash Messages --}}
  @if(session('error'))
    <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-medium flex items-center gap-2.5">
      <i class="fa-solid fa-triangle-exclamation text-sm"></i>
      <span>{{ session('error') }}</span>
    </div>
  @endif

  @if(session('info'))
    <div class="mb-5 p-3.5 rounded-xl bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-medium flex items-center gap-2.5">
      <i class="fa-solid fa-circle-info text-sm"></i>
      <span>{{ session('info') }}</span>
    </div>
  @endif

  @if(session('success'))
    <div class="mb-5 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium flex items-center gap-2.5">
      <i class="fa-solid fa-circle-check text-sm"></i>
      <span>{{ session('success') }}</span>
    </div>
  @endif

  <form method="POST" action="{{ route('login.submit') }}" class="space-y-5">
    @csrf

    {{-- User / Email --}}
    <div>
      <label for="login" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
        Usuario o Correo
      </label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <i class="fa-solid fa-user"></i>
        </div>
        <input 
          id="login" 
          name="login" 
          type="text" 
          autocomplete="username" 
          required 
          value="{{ old('login', 'admin') }}" 
          placeholder="admin o usuario@ejemplo.com"
          class="block w-full pl-10 pr-3 py-2.5 bg-slate-900/90 border @error('login') border-rose-500 @else border-slate-700 @enderror rounded-xl text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
        />
      </div>
      @error('login')
        <p class="mt-1.5 text-xs text-rose-400 font-medium">{{ $message }}</p>
      @enderror
    </div>

    {{-- Password --}}
    <div>
      <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
        Contraseña
      </label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
          <i class="fa-solid fa-lock"></i>
        </div>
        <input 
          id="password" 
          name="password" 
          type="password" 
          autocomplete="current-password" 
          required 
          value="1234"
          placeholder="••••••••"
          class="block w-full pl-10 pr-10 py-2.5 bg-slate-900/90 border @error('password') border-rose-500 @else border-slate-700 @enderror rounded-xl text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all"
        />
        <button type="button" onclick="togglePasswordVisibility()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200">
          <i id="eye-icon" class="fa-solid fa-eye"></i>
        </button>
      </div>
      @error('password')
        <p class="mt-1.5 text-xs text-rose-400 font-medium">{{ $message }}</p>
      @enderror
    </div>

    {{-- Remember me --}}
    <div class="flex items-center justify-between">
      <div class="flex items-center">
        <input 
          id="remember" 
          name="remember" 
          type="checkbox" 
          class="h-4 w-4 rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-800"
        />
        <label for="remember" class="ml-2 block text-xs text-slate-300 select-none cursor-pointer">
          Recordar sesión
        </label>
      </div>
      <span class="text-xs text-indigo-400">XAMPP / MySQL</span>
    </div>

    {{-- Submit button --}}
    <div>
      <button 
        type="submit" 
        class="w-full flex justify-center items-center gap-2 py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-sm font-semibold shadow-lg shadow-indigo-500/25 active:scale-98 transition-all"
      >
        <i class="fa-solid fa-right-to-bracket"></i>
        <span>Ingresar al Sistema</span>
      </button>
    </div>
  </form>

  {{-- Quick Access Credentials Box --}}
  <div class="mt-6 pt-5 border-t border-slate-700/60">
    <p class="text-xs font-semibold text-slate-400 text-center uppercase tracking-wider mb-3">
      ⚡ Cuentas de Acceso Rápido
    </p>
    <div class="grid grid-cols-2 gap-2">
      <button 
        type="button" 
        onclick="fillCredentials('admin', '1234')"
        class="p-2.5 rounded-xl bg-slate-900/60 hover:bg-slate-900 border border-slate-700/70 hover:border-indigo-500 text-left transition-all group"
      >
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs font-bold text-indigo-400 group-hover:text-indigo-300">Admin</span>
          <span class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-500/20 text-indigo-300 font-semibold">Rol Admin</span>
        </div>
        <p class="text-[11px] text-slate-400 font-mono">admin / 1234</p>
      </button>

      <button 
        type="button" 
        onclick="fillCredentials('juan@example.com', '1234')"
        class="p-2.5 rounded-xl bg-slate-900/60 hover:bg-slate-900 border border-slate-700/70 hover:border-emerald-500 text-left transition-all group"
      >
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs font-bold text-emerald-400 group-hover:text-emerald-300">Usuario</span>
          <span class="px-1.5 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 font-semibold">Rol User</span>
        </div>
        <p class="text-[11px] text-slate-400 font-mono">juan / 1234</p>
      </button>
    </div>
  </div>

</div>

<script>
  function fillCredentials(user, pass) {
    document.getElementById('login').value = user;
    document.getElementById('password').value = pass;
  }

  function togglePasswordVisibility() {
    const input = document.getElementById('password');
    const icon = document.getElementById('eye-icon');
    if (input.type === 'password') {
      input.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  }
</script>
@endsection
