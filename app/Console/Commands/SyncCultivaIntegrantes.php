<?php

namespace App\Console\Commands;

use App\Services\Cultiva\CultivaClient;
use App\Services\Cultiva\IntegranteSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCultivaIntegrantes extends Command
{
    protected $signature = 'cultiva:sync-integrantes {--dry-run : Muestra lo que haría sin escribir en la base}';

    protected $description = 'Sincroniza los integrantes activos desde Cultiva y les crea su usuario de la tienda';

    public function handle(CultivaClient $cliente, IntegranteSync $sync): int
    {
        $this->info('Consultando integrantes activos en Cultiva...');

        try {
            $registros = $cliente->integrantesActivos();
        } catch (\Throwable $e) {
            return $this->fallar('[consulta] '.$e->getMessage());
        }

        $this->line('Recibidos: '.count($registros));

        if ($this->option('dry-run')) {
            $empresas = collect($registros)
                ->groupBy(fn (array $r) => $r['empresa']['nombre'] ?? 'SIN EMPRESA')
                ->map->count();

            $this->table(
                ['Empresa', 'Integrantes'],
                $empresas->map(fn ($total, $empresa) => [$empresa, $total])->values()->all(),
            );
            $this->comment('Simulación: no se escribió nada.');

            return Command::SUCCESS;
        }

        try {
            $resumen = $sync->sincronizar($registros);
        } catch (\Throwable $e) {
            return $this->fallar('[sincronización] '.$e->getMessage());
        }

        $this->table(
            ['Recibidos', 'Creados', 'Actualizados', 'Inactivados', 'Reactivados', 'Usuarios nuevos'],
            [[
                $resumen['recibidos'],
                $resumen['creados'],
                $resumen['actualizados'],
                $resumen['inactivados'],
                $resumen['reactivados'],
                $resumen['usuarios'],
            ]],
        );

        if ($resumen['conflictos'] !== []) {
            $this->newLine();
            $this->warn('Integrantes omitidos por datos en conflicto ('.count($resumen['conflictos']).'):');

            foreach ($resumen['conflictos'] as $conflicto) {
                $this->line('  - '.$conflicto);
            }

            Log::warning('Cultiva integrantes: omitidos por conflicto', $resumen['conflictos']);
        }

        return Command::SUCCESS;
    }

    private function fallar(string $mensaje): int
    {
        Log::error('Cultiva integrantes: '.$mensaje);
        $this->error($mensaje);

        return Command::FAILURE;
    }
}
