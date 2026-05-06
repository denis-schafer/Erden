<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Comida', 'default' => true, 'enable' => true],
            ['name' => 'Bebida', 'default' => false, 'enable' => true],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['name' => $category['name']],
                array_merge($category, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        if ($this->command) {
            $this->command->info('Categories seeded: Comida, Bebida');
        }
    }
}
