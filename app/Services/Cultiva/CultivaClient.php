<?php

namespace App\Services\Cultiva;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de la API de integrantes de Cultiva.
 *
 * Recorre la paginación completa y devuelve los registros crudos. No
 * interpreta nada: de eso se encarga IntegranteSync.
 */
class CultivaClient
{
    /**
     * Integrantes activos de todas las empresas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function integrantesActivos(): array
    {
        $url = rtrim((string) config('cultiva.url'), '/');
        $llave = (string) config('cultiva.api_key');

        if ($url === '' || $llave === '') {
            throw new RuntimeException('Falta configurar CULTIVA_URL o CULTIVA_API_KEY.');
        }

        $registros = [];
        $pagina = 1;

        do {
            $respuesta = Http::timeout((int) config('cultiva.timeout'))
                ->withHeaders(['X-Empleados-Api-Key' => $llave])
                ->acceptJson()
                ->get($url.'/api/empleados', array_filter([
                    'estatus' => config('cultiva.solo_activos') ? 'A' : null,
                    'por_pagina' => 500,
                    'page' => $pagina,
                ]));

            if ($respuesta->failed()) {
                throw new RuntimeException(
                    'Cultiva respondió '.$respuesta->status().' al pedir la página '.$pagina.'.'
                );
            }

            $cuerpo = $respuesta->json();

            foreach ($cuerpo['data'] ?? [] as $registro) {
                $registros[] = $registro;
            }

            $ultima = (int) ($cuerpo['meta']['ultima_pagina'] ?? 1);
            $pagina++;
        } while ($pagina <= $ultima);

        return $registros;
    }
}
