<?php

namespace App\Services\Cultiva;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use RuntimeException;

/**
 * Sincroniza el padrón de integrantes de la tiendita contra Cultiva.
 *
 * Reglas:
 *
 * - Se casa por `code` = `numero` de Cultiva (el número de nómina). NO por la
 *   llave primaria de Cultiva: son numeraciones distintas y cruzarlas asigna
 *   el expediente de otra persona.
 * - La categoría (`category_id`) es de la tienda —define el límite de compra—
 *   y no se toca nunca. Cultiva manda sobre la identidad y el puesto.
 * - Nadie se borra. Quien deja de estar activo pasa al histórico con su
 *   usuario y su historial de pedidos intactos, pero pierde el acceso.
 * - Cada integrante nuevo estrena su usuario de la tienda (Employee::syncUser).
 */
class IntegranteSync
{
    /**
     * @param  array<int, array<string, mixed>>  $registros
     * @return array{recibidos:int, creados:int, actualizados:int, inactivados:int, reactivados:int, usuarios:int, conflictos:array<int, string>}
     */
    public function sincronizar(array $registros): array
    {
        // Una respuesta vacía no significa "se fueron todos": significa que
        // algo falló del otro lado. Inactivar el padrón completo por eso
        // dejaría a toda la empresa fuera de la tienda.
        if ($registros === []) {
            throw new RuntimeException(
                'Cultiva no devolvió ningún integrante activo; se aborta para no '
                .'inactivar el padrón completo.'
            );
        }

        $resumen = [
            'recibidos' => count($registros),
            'creados' => 0,
            'actualizados' => 0,
            'inactivados' => 0,
            'reactivados' => 0,
            'usuarios' => 0,
            'conflictos' => [],
        ];

        $codigosVigentes = [];
        $cedulasVistas = [];

        foreach ($registros as $registro) {
            $numero = $registro['numero'] ?? null;

            // Sin número de nómina no hay forma de casarlo con esta app.
            if ($numero === null) {
                continue;
            }

            $code = (string) $numero;
            $cedula = $this->soloDigitos($registro['cedula'] ?? null);

            if ($cedula === null) {
                $resumen['conflictos'][] = "{$code}: sin cédula en Cultiva";

                continue;
            }

            // `employees.cedula` y `users.cedula` son únicas. Cultiva tiene
            // cédulas de relleno repetidas (por ejemplo 00000000000), así que
            // la segunda se reporta en vez de reventar la corrida entera.
            if (isset($cedulasVistas[$cedula])) {
                $resumen['conflictos'][] = "{$code}: cédula {$cedula} repetida (ya usada por {$cedulasVistas[$cedula]})";

                continue;
            }

            $cedulasVistas[$cedula] = $code;

            $integrante = Employee::where('code', $code)->first();

            // La cédula puede estar tomada por otro registro: sin esto, el
            // guardado falla contra el índice único.
            $choque = Employee::where('cedula', $cedula)
                ->when($integrante, fn ($q) => $q->where('id', '!=', $integrante->id))
                ->first();

            if ($choque) {
                $resumen['conflictos'][] = "{$code}: la cédula {$cedula} ya pertenece al integrante {$choque->code}";

                continue;
            }

            $atributos = [
                'company_id' => $this->empresaId($registro['empresa']['nombre'] ?? null),
                'name' => $registro['nombre_completo'] ?? '',
                'cedula' => $cedula,
                'position' => $this->texto($registro['posicion']['nombre'] ?? null) ?? 'SIN POSICION',
                'department' => $this->texto($registro['departamento']['nombre'] ?? null) ?? 'SIN DEPARTAMENTO',
            ];

            $codigosVigentes[] = $code;

            if (! $integrante) {
                $integrante = Employee::create($atributos + [
                    'code' => $code,
                    'active' => true,
                ]);

                $resumen['creados']++;

                if ($this->crearUsuario($integrante)) {
                    $resumen['usuarios']++;
                }

                continue;
            }

            $estabaInactivo = ! $integrante->active;

            if ($estabaInactivo) {
                $atributos['active'] = true;
                $resumen['reactivados']++;
            }

            $integrante->fill($atributos);

            if ($integrante->isDirty()) {
                $integrante->save();

                if (! $estabaInactivo) {
                    $resumen['actualizados']++;
                }
            }

            // Un integrante que nunca llegó a tener usuario (alta a medias, o
            // un fallo en una corrida anterior) lo estrena aquí.
            if (! $integrante->user()->exists() && $this->crearUsuario($integrante)) {
                $resumen['usuarios']++;
            }
        }

        if ($codigosVigentes === []) {
            throw new RuntimeException(
                'Ningún integrante de Cultiva pudo procesarse; se aborta para no '
                .'inactivar el padrón completo.'
            );
        }

        // Quien dejó de venir en la respuesta ya no está activo en Cultiva.
        $ausentes = Employee::where('active', true)
            ->whereNotIn('code', $codigosVigentes)
            ->get();

        foreach ($ausentes as $integrante) {
            $integrante->forceFill(['active' => false])->save();
            $resumen['inactivados']++;
        }

        return $resumen;
    }

    /**
     * Deja al integrante con su usuario de la tienda.
     *
     * Employee::syncUser() es la misma vía que usan el alta manual y la
     * importación por Excel, para que todos los usuarios nazcan iguales.
     *
     * Antes de crear uno nuevo se adopta el usuario suelto que ya tenga esa
     * cédula: son cuentas creadas antes de que existiera el registro del
     * integrante, y `users.cedula` es única, así que crear otro fallaría y la
     * persona se quedaría sin poder entrar.
     */
    private function crearUsuario(Employee $integrante): bool
    {
        try {
            $huerfano = User::where('cedula', $integrante->cedula)
                ->whereNull('employee_id')
                ->first();

            if ($huerfano) {
                $huerfano->update([
                    'employee_id' => $integrante->id,
                    'name' => $integrante->name,
                ]);

                if (! $huerfano->hasRole('empleado')) {
                    $huerfano->assignRole('empleado');
                }

                return true;
            }

            $integrante->syncUser();

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * Empresa de la tiendita equivalente a la de Cultiva.
     *
     * Se casa por nombre, no por id: las dos aplicaciones numeran sus empresas
     * por su cuenta (el código 2 de Cultiva es MAVERICK, el id 2 de la tienda
     * es COSTIERA). Si la empresa no existe todavía, se crea.
     */
    private function empresaId(?string $nombre): int
    {
        $nombre = $this->texto($nombre) ?? 'SIN EMPRESA';

        $empresa = Company::whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper($nombre)])->first();

        if (! $empresa) {
            $empresa = Company::create([
                'name' => $nombre,
                'description' => 'Creada automáticamente por la sincronización con Cultiva.',
                'active' => true,
            ]);
        }

        return $empresa->id;
    }

    /** La cédula se guarda solo con dígitos, igual que en users. */
    private function soloDigitos(?string $valor): ?string
    {
        $valor = preg_replace('/\D/', '', (string) $valor);

        return $valor !== '' ? mb_substr($valor, 0, 255) : null;
    }

    private function texto(?string $valor): ?string
    {
        $valor = trim(preg_replace('/\s+/', ' ', (string) $valor));

        return $valor !== '' ? mb_substr($valor, 0, 255) : null;
    }
}
