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

        // Si el usuario es empleado o supervisor y está intentando acceder al dashboard
        if (($user->hasRole('empleado') || $user->hasRole('supervisor')) && $currentRoute === 'dashboard') {
            return redirect()->route('public.orders');
        }

        // Si el usuario es Super Admin o Admin y está intentando acceder a public/orders
        if (($user->hasRole('Super Admin') || $user->hasRole('admin')) && $currentRoute === 'public.orders') {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
} 