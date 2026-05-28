<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'Caja',
                'route' => 'pos-caja',
                'icon' => 'bi-cart3',
                'description' => 'Punto de venta',
                'is_special' => false,
                'order' => 1,
                'package' => 'pos'
            ],
            [
                'name' => 'Administración',
                'route' => 'pos-admin',
                'icon' => 'bi-gear',
                'description' => 'Administración del sistema',
                'is_special' => false,
                'order' => 2,
                'package' => 'pos'
            ],
            [
                'name' => 'Categorías',
                'route' => 'pos-categories',
                'icon' => 'bi-tags',
                'description' => 'Gestión de categorías',
                'is_special' => false,
                'order' => 3,
                'package' => 'pos'
            ],
            [
                'name' => 'Productos',
                'route' => 'pos-products',
                'icon' => 'bi-box-seam',
                'description' => 'Gestión de productos',
                'is_special' => false,
                'order' => 4,
                'package' => 'pos'
            ],
            [
                'name' => 'Órdenes',
                'route' => 'pos-orders',
                'icon' => 'bi-receipt',
                'description' => 'Ver órdenes',
                'is_special' => false,
                'order' => 5,
                'package' => 'pos'
            ],
            [
                'name' => 'Configuración',
                'route' => 'pos-config',
                'icon' => 'bi-sliders',
                'description' => 'Configuración del sistema',
                'is_special' => false,
                'order' => 6,
                'package' => 'pos'
            ],
            [
                'name' => 'QR',
                'route' => 'pos-qr',
                'icon' => 'bi-qr-code',
                'description' => 'Ver pedido en dispositivo externo',
                'is_special' => false,
                'order' => 7,
                'package' => 'pos'
            ],
            [
                'name' => 'Estadísticas',
                'route' => 'pos-statistics',
                'icon' => 'bi-bar-chart',
                'description' => 'Estadísticas y reportes del POS',
                'is_special' => false,
                'order' => 8,
                'package' => 'pos'
            ],
            [
                'name' => 'Log',
                'route' => 'pos-log',
                'icon' => 'bi-journal-text',
                'description' => 'Registro de actividad del POS',
                'is_special' => false,
                'order' => 9,
                'package' => 'pos'
            ],
            [
                'name' => 'Documentación',
                'route' => 'pos-documentation',
                'icon' => 'bi-book',
                'description' => 'Documentación del sistema',
                'is_special' => false,
                'order' => 10,
                'package' => 'pos'
            ],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['route' => $module['route']],
                array_merge($module, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        $this->command->info('POS Modules seeded');
    }
}
