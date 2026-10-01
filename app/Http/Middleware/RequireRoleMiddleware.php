<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles Lista de roles permitidos separados por coma
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acceso denegado: Usuario no autenticado o inactivo.',
                ], 401);
            }

            return redirect()->route('login')->with('error', 'Tu sesión no es válida o tu cuenta está desactivada.');
        }

        // Si el usuario tiene el rol 'director' o cumple con alguno de los roles solicitados
        if ($user->hasRole($roles)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes los permisos requeridos para acceder a este recurso.',
                'required_roles' => $roles,
            ], 403);
        }

        abort(403, 'Acceso Denegado: No posees el perfil necesario para acceder a este módulo.');
    }
}
