<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Module;

class PosModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Caja',
                'route' => 'pos-caja',
                'icon' => 'bi-cart3',
                'is_special' => false,
                'order' => 100,
                'package' => 'pos'
            ],
            [
                'name' => 'Administración',
                'route' => 'pos-admin',
                'icon' => 'bi-gear',
                'is_special' => false,
                'order' => 101,
                'package' => 'pos'
            ]
        ];

        foreach ($modules as $moduleData) {
            $existing = Module::where('route', $moduleData['route'])->first();
            
            if (!$existing) {
                Module::create($moduleData);
                $this->command->info("Created module: {$moduleData['name']}");
            } else {
                $this->command->info("Module already exists: {$moduleData['name']}");
            }
        }
    }
}
