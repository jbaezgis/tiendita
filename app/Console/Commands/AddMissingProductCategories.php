<?php

namespace App\Console\Commands;

use App\Models\ProductCategory;
use Illuminate\Console\Command;

class AddMissingProductCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:add-missing-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add missing product categories';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Agregando categorías faltantes...');

        $newCategories = [
            [
                'name' => 'Rasuradoras',
                'description' => 'Rasuradoras y productos de afeitado',
                'is_active' => true,
            ],
            [
                'name' => 'Corrección',
                'description' => 'Productos de corrección y tipex',
                'is_active' => true,
            ],
        ];

        foreach ($newCategories as $category) {
            $existing = ProductCategory::where('name', $category['name'])->first();
            
            if (!$existing) {
                ProductCategory::create($category);
                $this->info("✓ Categoría '{$category['name']}' creada");
            } else {
                $this->line("- Categoría '{$category['name']}' ya existe");
            }
        }

        $this->info('Proceso completado');

        return Command::SUCCESS;
    }
}
