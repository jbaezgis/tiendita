<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Employee;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Servicio de importación de integrantes desde Excel.
 *
 * - Usa la columna "CODIGO" del Excel como identificador contra la columna
 *   `code` de la tabla employees.
 * - Los integrantes existentes se mantienen intactos; solo se crean los nuevos.
 * - Al crear un integrante se sincroniza automáticamente su usuario (login por
 *   cédula) mediante Employee::syncUser().
 * - La categoría se busca por su código en el catálogo; si no existe, el
 *   integrante se crea sin categoría y se reporta para revisión manual (las
 *   categorías requieren rangos salariales y límite de compra).
 */
class EmployeeImportService
{
    /** Encabezados aceptados por campo (el primero es el que genera el export). */
    private const HEADERS = [
        'code' => ['CODIGO', 'CODIGO EMPLEADO', 'COD', 'NO. EMPLEADO'],
        'name' => ['NOMBRE', 'NOMBRE COMPLETO', 'EMPLEADO'],
        'cedula' => ['CEDULA', 'CEDULA EMPLEADO', 'DOCUMENTO'],
        'position' => ['CARGO', 'POSICION', 'PUESTO'],
        'department' => ['DEPARTAMENTO', 'DEPTO', 'AREA'],
        'category' => ['CATEGORIA', 'CATEGORIA EMPLEADO', 'CLASE'],
        'active' => ['ESTADO', 'ESTATUS', 'ACTIVO'],
    ];

    /**
     * Lee el archivo Excel y devuelve un arreglo de filas asociativas
     * (encabezado normalizado en MAYÚSCULAS => valor de la celda).
     *
     * @return array<int, array<string, mixed>>
     */
    public function parse(string $absolutePath): array
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($absolutePath)->getActiveSheet();

        $matrix = $sheet->toArray(null, true, false, false);

        if (empty($matrix)) {
            return [];
        }

        $headers = array_map(fn ($h) => $this->normalizeHeader($h), array_shift($matrix));

        $rows = [];
        foreach ($matrix as $cells) {
            $row = [];
            foreach ($headers as $i => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = $cells[$i] ?? null;
            }

            // Ignorar filas totalmente vacías.
            if (collect($row)->filter(fn ($v) => $v !== null && trim((string) $v) !== '')->isEmpty()) {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Importa una sola fila del Excel.
     *
     * @return array{status: 'created'|'skipped'|'invalid', code: ?string, name: ?string, user_created: bool, missing_categories: array<int, string>, message?: string}
     */
    public function importRow(array $row): array
    {
        $code = $this->cleanCode($this->get($row, 'code'));
        $name = $this->str($this->get($row, 'name'));
        $cedula = $this->cleanCedula($this->get($row, 'cedula'));
        $position = $this->str($this->get($row, 'position'));
        $department = $this->str($this->get($row, 'department'));

        $result = [
            'status' => 'invalid',
            'code' => $code,
            'name' => $name,
            'user_created' => false,
            'missing_categories' => [],
        ];

        // Campos obligatorios según el esquema de la tabla employees.
        $missing = [];
        if (! $code) {
            $missing[] = 'CODIGO';
        }
        if (! $name) {
            $missing[] = 'NOMBRE';
        }
        if (! $cedula) {
            $missing[] = 'CEDULA';
        }
        if (! $position) {
            $missing[] = 'CARGO';
        }
        if (! $department) {
            $missing[] = 'DEPARTAMENTO';
        }

        if ($missing) {
            return $result + ['message' => 'Faltan columnas: '.implode(', ', $missing)];
        }

        // Ya existe por código o por cédula (ambas son únicas): se respeta el
        // registro actual y no se modifica nada.
        $exists = Employee::where('code', $code)
            ->orWhere('cedula', $cedula)
            ->exists();

        if ($exists) {
            return ['status' => 'skipped'] + $result;
        }

        [$categoryId, $categoryMissing] = $this->resolveCategory($this->get($row, 'category'));

        $employee = Employee::create([
            'company_id' => 1,
            'code' => $code,
            'name' => $name,
            'cedula' => $cedula,
            'position' => $position,
            'department' => $department,
            'category_id' => $categoryId,
            'active' => $this->active($this->get($row, 'active')),
        ]);

        // El usuario NO se crea por evento del modelo (booted() solo escucha
        // updating/updated), así que se sincroniza explícitamente igual que en
        // el alta manual del formulario.
        $userCreated = false;
        try {
            $employee->syncUser();
            $userCreated = true;
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'status' => 'created',
            'code' => $code,
            'name' => $name,
            'user_created' => $userCreated,
            'missing_categories' => $categoryMissing ? [$categoryMissing] : [],
        ];
    }

    /* ----------------------- Resolución de catálogos ----------------------- */

    /**
     * Busca la categoría por código. No la crea: `categories` exige rangos
     * salariales y límite de compra que el Excel de integrantes no trae.
     *
     * @return array{0: ?int, 1: ?string} [category_id, codigoNoEncontrado|null]
     */
    private function resolveCategory($value): array
    {
        $code = trim((string) ($value ?? ''));

        if ($code === '' || mb_strtoupper($code) === 'SIN CATEGORIA') {
            return [null, null];
        }

        $category = Category::whereRaw('UPPER(TRIM(code)) = ?', [mb_strtoupper($code)])->first();

        return $category ? [$category->id, null] : [null, $code];
    }

    /* ----------------------------- Utilidades ----------------------------- */

    /** Normaliza el encabezado: mayúsculas, sin acentos y sin espacios dobles. */
    private function normalizeHeader($header): string
    {
        $header = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $header)));

        return strtr($header, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);
    }

    /** Devuelve el valor de la fila probando todos los alias del campo. */
    private function get(array $row, string $field): mixed
    {
        foreach (self::HEADERS[$field] as $header) {
            $value = $row[$this->normalizeHeader($header)] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function cleanCode($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        // Excel puede entregarlo como "102.0" si lo interpreta como número.
        if (is_numeric($value)) {
            $value = (string) (int) round((float) $value);
        }

        return mb_substr($value, 0, 255);
    }

    /** La cédula se guarda solo con dígitos, igual que en users. */
    private function cleanCedula($value): ?string
    {
        $value = preg_replace('/\D/', '', (string) ($value ?? ''));

        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    private function str($value): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) ($value ?? '')));

        return $value !== '' ? mb_substr($value, 0, 255) : null;
    }

    /** "Inactivo"/"0"/"No" => false; cualquier otra cosa (o vacío) => true. */
    private function active($value): bool
    {
        $value = mb_strtoupper(trim((string) ($value ?? '')));

        if ($value === '') {
            return true;
        }

        return ! (str_starts_with($value, 'INACTIV') || $value === '0' || $value === 'NO' || $value === 'FALSE');
    }
}
