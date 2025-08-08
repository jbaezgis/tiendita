<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\StoreConfig;

class StoreConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        StoreConfig::create([
            'is_open' => false,
            'current_season' => 'Útiles Escolares',
            'season_start_date' => now()->startOfMonth(),
            'season_end_date' => now()->endOfMonth()->addMonths(2),
            'store_opening_date' => null,
            'store_closing_date' => null,
            'max_order_amount' => 10000.00,
            'notes' => 'Configuración inicial de la tienda. Temporada de útiles escolares.',
        ]);

        $this->command->info('Configuración de la tienda creada exitosamente.');
    }
}
