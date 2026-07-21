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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Verificar que el usuario tenga rol de integrante o admin
        if (!$user->hasRole(['empleado', 'admin'])) {
            // Si no es integrante ni supervisor, redirigir al dashboard
            return redirect()->route('dashboard')->with('error', 'No tienes permisos para acceder a esta página. Solo integrantes y supervisores pueden acceder.');
        }

        return $next($request);
    }
} 