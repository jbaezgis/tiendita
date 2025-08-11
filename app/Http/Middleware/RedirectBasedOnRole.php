<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectBasedOnRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplicar si el usuario está autenticado
        if (!Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $currentRoute = $request->route()->getName();

        // Verificar si el usuario tiene roles múltiples (empleado/supervisor Y admin)
        $hasEmployeeRole = $user->hasRole(['empleado', 'supervisor']);
        $hasAdminRole = $user->hasRole(['Super Admin', 'admin']);

        // Si el usuario tiene roles múltiples, permitir acceso a ambas áreas
        if ($hasEmployeeRole && $hasAdminRole) {
            return $next($request);
        }

        // Si el usuario es solo empleado o supervisor y está intentando acceder al dashboard
        if ($hasEmployeeRole && !$hasAdminRole && $currentRoute === 'dashboard') {
            // Solo redirigir si tiene empleado vinculado
            if ($user->employee) {
                return redirect()->route('public.orders');
            }
            // Si no tiene empleado vinculado, permitir acceso al dashboard
        }

        // Si el usuario es solo Super Admin o Admin y está intentando acceder a public/orders
        if ($hasAdminRole && !$hasEmployeeRole && $currentRoute === 'public.orders') {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
} 