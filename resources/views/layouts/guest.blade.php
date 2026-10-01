<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? 'Acceso al Sistema' }} - {{ config('app.name') }}</title>
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-slate-100 antialiased selection:bg-indigo-500 selection:text-white">
  
  <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
    <a href="{{ route('home') }}" class="inline-flex items-center gap-3 text-2xl font-black text-white hover:opacity-90 transition-opacity">
      <span class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30">
        <i class="fa-solid fa-shield-halved"></i>
      </span>
      <span>Laravel Admin</span>
    </a>
    <h2 class="mt-4 text-xl font-bold tracking-tight text-slate-200">
      {{ $heading ?? 'Iniciar Sesión' }}
    </h2>
    <p class="mt-1 text-xs text-slate-400">
      Panel de autenticación y gestión de roles
    </p>
  </div>

  <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md px-4">
    @yield('content')
  </div>

  <footer class="mt-8 text-center text-xs text-slate-400">
    &copy; {{ date('Y') }} Laravel Fullstack Panel. Todos los derechos reservados.
  </footer>

</body>
</html>
