<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrSupervisor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Verificar que el usuario tenga rol de Super Admin, Admin o Supervisor
        if (!$user->hasRole(['Super Admin', 'admin', 'supervisor'])) {
            // Si no tiene ninguno de estos roles, redirigir a public/orders
            return redirect()->route('public.orders')->with('error', 'No tienes permisos para acceder al dashboard. Solo Super Admin, Admin y Supervisores pueden acceder.');
        }

        return $next($request);
    }
} 