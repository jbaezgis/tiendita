<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Console\Command;

class ExtractProductCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:extract-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extract product categories from product descriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Extrayendo categorías de productos...');

        // Mapeo de palabras clave a categorías
        $categoryMapping = [
            'champú' => 'Champú',
            'shampoo' => 'Champú',
            'acondicionador' => 'Acondicionadores',
            'conditioner' => 'Acondicionadores',
            'cuaderno' => 'Cuadernos',
            'notebook' => 'Cuadernos',
            'libreta' => 'Cuadernos',
            'lápiz' => 'Lápices',
            'lapiz' => 'Lápices',
            'pencil' => 'Lápices',
            'bolígrafo' => 'Bolígrafos',
            'boligrafo' => 'Bolígrafos',
            'pen' => 'Bolígrafos',
            'mochila' => 'Mochilas',
            'backpack' => 'Mochilas',
            'regla' => 'Reglas',
            'ruler' => 'Reglas',
            'compás' => 'Compases',
            'compas' => 'Compases',
            'compass' => 'Compases',
            'pegamento' => 'Pegamento',
            'glue' => 'Pegamento',
            'tijera' => 'Tijeras',
            'scissors' => 'Tijeras',
            'marcador' => 'Marcadores',
            'marker' => 'Marcadores',
            'resaltador' => 'Marcadores',
            'highlighter' => 'Marcadores',
            'papel' => 'Papel',
            'paper' => 'Papel',
            'cartulina' => 'Papel',
            'rasurador' => 'Rasuradoras',
            'razor' => 'Rasuradoras',
            'shaver' => 'Rasuradoras',
            'plumon' => 'Marcadores',
            'plumones' => 'Marcadores',
            'correctora' => 'Corrección',
            'corrector' => 'Corrección',
            'evolution' => 'Lápices',
        ];

        $products = Product::whereNull('product_category_id')->get();
        $processed = 0;
        $categorized = 0;

        foreach ($products as $product) {
            $processed++;
            $description = strtolower($product->description);
            $categoryFound = false;

            foreach ($categoryMapping as $keyword => $categoryName) {
                if (str_contains($description, $keyword)) {
                    $category = ProductCategory::where('name', $categoryName)->first();
                    
                    if ($category) {
                        $product->update(['product_category_id' => $category->id]);
                        $this->line("✓ Producto '{$product->description}' asignado a categoría '{$categoryName}'");
                        $categorized++;
                        $categoryFound = true;
                        break;
                    }
                }
            }

            if (!$categoryFound) {
                $this->line("- Producto '{$product->description}' sin categoría asignada");
            }
        }

        $this->info("Procesados: {$processed} productos");
        $this->info("Categorizados: {$categorized} productos");
        $this->info("Sin categoría: " . ($processed - $categorized) . " productos");

        return Command::SUCCESS;
    }
}
