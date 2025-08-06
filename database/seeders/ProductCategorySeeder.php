<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ProductCategory;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Champú',
                'description' => 'Productos de champú para el cuidado del cabello',
                'is_active' => true,
            ],
            [
                'name' => 'Acondicionadores',
                'description' => 'Productos acondicionadores para el cabello',
                'is_active' => true,
            ],
            [
                'name' => 'Cuadernos',
                'description' => 'Cuadernos y libretas para uso escolar',
                'is_active' => true,
            ],
            [
                'name' => 'Lápices',
                'description' => 'Lápices de grafito y de colores',
                'is_active' => true,
            ],
            [
                'name' => 'Bolígrafos',
                'description' => 'Bolígrafos y plumas para escritura',
                'is_active' => true,
            ],
            [
                'name' => 'Mochilas',
                'description' => 'Mochilas y bolsos escolares',
                'is_active' => true,
            ],
            [
                'name' => 'Reglas',
                'description' => 'Reglas y escuadras para geometría',
                'is_active' => true,
            ],
            [
                'name' => 'Compases',
                'description' => 'Compases y herramientas de dibujo técnico',
                'is_active' => true,
            ],
            [
                'name' => 'Pegamento',
                'description' => 'Pegamentos y adhesivos escolares',
                'is_active' => true,
            ],
            [
                'name' => 'Tijeras',
                'description' => 'Tijeras y herramientas de corte',
                'is_active' => true,
            ],
            [
                'name' => 'Marcadores',
                'description' => 'Marcadores y resaltadores',
                'is_active' => true,
            ],
            [
                'name' => 'Papel',
                'description' => 'Papel y cartulinas para manualidades',
                'is_active' => true,
            ],
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

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}
