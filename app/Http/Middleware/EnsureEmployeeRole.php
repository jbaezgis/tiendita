<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Un integrante inactivo conserva su cuenta y su historial, pero no
        // puede seguir comprando. Cubre las sesiones ya abiertas cuando la
        // sincronización con Cultiva lo dio de baja.
        if ($user->isBlockedByInactiveEmployee()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('error', 'Tu acceso a la tienda fue desactivado porque ya no figuras como integrante activo.');
        }

        // Verificar que el usuario tenga rol de integrante o admin
        if (! $user->hasRole(['empleado', 'admin'])) {
            // Si no es integrante ni supervisor, redirigir al dashboard
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta página. Solo integrantes y supervisores pueden acceder.');
        }

        return $next($request);
    }
}
