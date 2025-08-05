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

        // Verificar que el usuario tenga rol de Super Admin o Admin
        if (!$user->hasRole(['Super Admin', 'admin'])) {
            // Si no es Super Admin ni Admin, redirigir a public/orders
            return redirect()->route('public.orders')->with('error', 'No tienes permisos para acceder al dashboard. Solo Super Admin y Admin pueden acceder.');
        }

        return $next($request);
    }
} 