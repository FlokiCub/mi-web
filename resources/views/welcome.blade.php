<!DOCTYPE html>
<html lang="es" class="scroll-smooth">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laravel Fullstack App - Sistema de Usuarios & Roles</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased min-h-screen flex flex-col selection:bg-indigo-500 selection:text-white">

  <!-- Navbar -->
  <header class="sticky top-0 z-50 bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-6 py-4">
    <div class="max-w-6xl mx-auto flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md shadow-indigo-500/30 font-bold">
          <i class="fa-solid fa-shield-halved"></i>
        </div>
        <span class="font-black text-xl tracking-tight text-white">Laravel Panel</span>
      </div>

      <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-300">
        <a href="#inicio" class="hover:text-indigo-400 transition-colors">Inicio</a>
        <a href="#arquitectura" class="hover:text-indigo-400 transition-colors">Arquitectura</a>
        <a href="#roles" class="hover:text-indigo-400 transition-colors">Roles & Seguridad</a>
      </nav>

      <div class="flex items-center gap-3">
        @auth
          @if(auth()->user()->isAdmin())
            <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 text-xs sm:text-sm font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition-all">
              <i class="fa-solid fa-crown mr-1"></i> Panel Admin
            </a>
          @else
            <a href="{{ route('dashboard') }}" class="px-4 py-2 text-xs sm:text-sm font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition-all">
              <i class="fa-solid fa-user mr-1"></i> Mi Panel
            </a>
          @endif
        @else
          <a href="{{ route('login') }}" class="px-5 py-2 text-xs sm:text-sm font-bold rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 active:scale-95 transition-all">
            <i class="fa-solid fa-right-to-bracket mr-1.5"></i> Iniciar Sesión
          </a>
        @endauth
      </div>
    </div>
  </header>

  <!-- Hero Section -->
  <section id="inicio" class="py-20 px-6 max-w-6xl mx-auto text-center flex flex-col items-center flex-1 justify-center">
    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-indigo-950/70 border border-indigo-800/80 text-indigo-300 text-xs font-semibold mb-6">
      <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
      Laravel {{ app()->version() }} + MySQL + XAMPP
    </div>

    <h1 class="text-4xl sm:text-6xl font-black tracking-tight text-white max-w-4xl leading-tight">
      Sistema Fullstack con <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400">Autenticación & Roles</span>
    </h1>

    <p class="mt-6 text-base sm:text-lg text-slate-400 max-w-2xl leading-relaxed">
      Aplicación completa en Laravel con inicio de sesión seguro, panel de administración para gestión y asignación de roles (Administrador y Usuario), y base de datos MySQL en XAMPP.
    </p>

    <!-- Call to action buttons -->
    <div class="mt-8 flex flex-wrap items-center justify-center gap-4">
      <a href="{{ route('login') }}" class="px-7 py-3.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-bold text-sm shadow-xl shadow-indigo-600/30 active:scale-95 transition-all">
        <i class="fa-solid fa-lock mr-2"></i> Ingresar al Panel de Control
      </a>
    </div>

    <!-- Credentials Quick Card -->
    <div class="mt-12 w-full max-w-xl p-5 rounded-2xl bg-slate-900/90 border border-slate-800 shadow-2xl text-left">
      <div class="flex items-center justify-between pb-3 border-b border-slate-800">
        <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">
          <i class="fa-solid fa-key text-indigo-400 mr-1.5"></i> Credenciales de Acceso Inicial
        </span>
        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
          Listo para usar
        </span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4 text-xs">
        <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800/90">
          <div class="flex items-center justify-between mb-1">
            <span class="font-bold text-indigo-400">Administrador</span>
            <span class="px-1.5 py-0.5 rounded text-[9px] bg-indigo-500/20 text-indigo-300">Rol Admin</span>
          </div>
          <p class="text-slate-300 font-mono">Usuario: <strong class="text-white">admin</strong></p>
          <p class="text-slate-300 font-mono">Contraseña: <strong class="text-white">1234</strong></p>
        </div>

        <div class="p-3 rounded-xl bg-slate-950/80 border border-slate-800/90">
          <div class="flex items-center justify-between mb-1">
            <span class="font-bold text-purple-400">Usuario de Prueba</span>
            <span class="px-1.5 py-0.5 rounded text-[9px] bg-purple-500/20 text-purple-300">Rol User</span>
          </div>
          <p class="text-slate-300 font-mono">Usuario: <strong class="text-white">juan@example.com</strong></p>
          <p class="text-slate-300 font-mono">Contraseña: <strong class="text-white">1234</strong></p>
        </div>
      </div>
    </div>
  </section>

  <!-- Features / Roles Section -->
  <section id="roles" class="py-16 px-6 bg-slate-900/50 border-t border-slate-800">
    <div class="max-w-6xl mx-auto">
      <div class="text-center max-w-xl mx-auto mb-12">
        <h2 class="text-2xl sm:text-3xl font-bold text-white">Estructura & Control de Roles</h2>
        <p class="mt-2 text-xs text-slate-400">Middleware y políticas de autorización integradas en Laravel.</p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
          <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl mb-4">
            <i class="fa-solid fa-crown"></i>
          </div>
          <h3 class="font-bold text-base text-white mb-2">Panel Administrador</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Permite crear nuevos usuarios, editar sus perfiles, cambiar o resetear contraseñas, activar/desactivar accesos y asignar roles (Admin o Usuario).
          </p>
        </div>

        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
          <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 text-purple-400 flex items-center justify-center text-xl mb-4">
            <i class="fa-solid fa-user-lock"></i>
          </div>
          <h3 class="font-bold text-base text-white mb-2">Middleware de Protección</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Las rutas de administración están resguardadas por el middleware `admin`, impidiendo accesos no autorizados de usuarios estándar o invitados.
          </p>
        </div>

        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 shadow-sm">
          <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl mb-4">
            <i class="fa-solid fa-database"></i>
          </div>
          <h3 class="font-bold text-base text-white mb-2">MySQL & Eloquent ORM</h3>
          <p class="text-xs text-slate-400 leading-relaxed">
            Integrado con la base de datos `mi_web` en XAMPP, con migraciones, seeders y modelos optimizados.
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="border-t border-slate-800 py-6 px-6 text-center text-xs text-slate-500">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
      <span>Desarrollado en <strong>Laravel Fullstack</strong></span>
      <span>Base de datos: <code class="text-indigo-400 font-mono">MySQL (mi_web)</code></span>
    </div>
  </footer>

</body>
</html>
