<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('products')->delete();
        
        $comidaCategory = DB::table('categories')->where('name', 'Comida')->first();
        $bebidaCategory = DB::table('categories')->where('name', 'Bebida')->first();
        
        if (!$comidaCategory || !$bebidaCategory) {
            if ($this->command) {
                $this->command->warn('Categories not found. Run CategorySeeder first.');
            }
            return;
        }

        $products = [
            [
                'name' => 'Hamburguesa',
                'short_description' => 'Hamburguesa clásica',
                'amount' => 150.00,
                'category_id' => $comidaCategory->id,
                'enable' => true,
                'order' => 1
            ],
            [
                'name' => 'Papas Fritas',
                'short_description' => 'Papas crocantes',
                'amount' => 80.00,
                'category_id' => $comidaCategory->id,
                'enable' => true,
                'order' => 2
            ],
            [
                'name' => 'Gaseosa',
                'short_description' => 'Gaseosa 500ml',
                'amount' => 50.00,
                'category_id' => $bebidaCategory->id,
                'enable' => true,
                'order' => 1
            ],
            [
                'name' => 'Agua',
                'short_description' => 'Agua mineral 500ml',
                'amount' => 40.00,
                'category_id' => $bebidaCategory->id,
                'enable' => true,
                'order' => 2
            ],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert(array_merge($product, ['created_at' => now(), 'updated_at' => now()]));
        }

        if ($this->command) {
            $this->command->info('Products seeded: Hamburguesa, Papas Fritas, Gaseosa, Agua');
        }
    }
}
