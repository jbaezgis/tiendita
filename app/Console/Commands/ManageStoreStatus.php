<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\StoreConfig;

class ManageStoreStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'store:status {action : open|close|info} {--season= : Set current season} {--opening-date= : Set store opening date (YYYY-MM-DD HH:MM)} {--closing-date= : Set store closing date (YYYY-MM-DD HH:MM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage store status (open/close) and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $config = StoreConfig::getCurrentConfig();

        switch ($action) {
            case 'open':
                $this->openStore($config);
                break;
            case 'close':
                $this->closeStore($config);
                break;
            case 'info':
                $this->showInfo($config);
                break;
            default:
                $this->error('Acción no válida. Use: open, close, o info');
                return 1;
        }

        return 0;
    }

    private function openStore(StoreConfig $config)
    {
        $config->update(['is_open' => true]);
        
        $openingDate = $this->option('opening-date');
        $closingDate = $this->option('closing-date');
        
        if ($openingDate) {
            $config->update(['store_opening_date' => $openingDate]);
        }
        
        if ($closingDate) {
            $config->update(['store_closing_date' => $closingDate]);
        }

        $this->info('✅ Tienda abierta exitosamente');
        $this->showInfo($config);
    }

    private function closeStore(StoreConfig $config)
    {
        $config->update(['is_open' => false]);
        $this->info('🔒 Tienda cerrada exitosamente');
        $this->showInfo($config);
    }

    private function showInfo(StoreConfig $config)
    {
        $this->info('📊 Estado de la Tienda');
        $this->info('==================');
        $this->info('Estado: ' . ($config->is_open ? '🟢 Abierta' : '🔴 Cerrada'));
        $this->info('Estado real: ' . $config->getStoreStatus());
        $this->info('Temporada: ' . $config->current_season);
        $this->info('Estado temporada: ' . $config->getSeasonStatus());
        
        if ($config->store_opening_date) {
            $this->info('Fecha apertura: ' . $config->store_opening_date->format('Y-m-d H:i'));
        }
        
        if ($config->store_closing_date) {
            $this->info('Fecha cierre: ' . $config->store_closing_date->format('Y-m-d H:i'));
        }
        
        $this->info('Monto máximo por pedido: RD$ ' . number_format($config->max_order_amount, 2));
        
        if ($config->notes) {
            $this->info('Notas: ' . $config->notes);
        }
    }
}
