<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\StoreConfig;

class EnsureStoreIsOpen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si la tienda está abierta
        if (!StoreConfig::isStoreOpen()) {
            // Si es una petición AJAX, devolver un error JSON
            if ($request->expectsJson() || $request->is('livewire/*')) {
                return response()->json([
                    'error' => 'Tienda cerrada',
                    'message' => 'La tienda está cerrada actualmente. No se pueden realizar pedidos.',
                ], 403);
            }

            // Para peticiones normales, mostrar una página de información
            // en lugar de redirigir para evitar bucles
            return response()->view('store-closed', [
                'message' => 'La tienda está cerrada actualmente. No se pueden realizar pedidos.'
            ], 200);
        }

        return $next($request);
    }
}
