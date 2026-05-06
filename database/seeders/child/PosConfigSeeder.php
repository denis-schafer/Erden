<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            [
                'name' => 'enable_print',
                'value' => 'false',
                'target' => 'print',
                'type' => 'boolean',
            ],
            [
                'name' => 'printer_ip',
                'value' => '',
                'target' => 'print',
                'type' => 'string',
            ],
            [
                'name' => 'printer_port',
                'value' => '9100',
                'target' => 'print',
                'type' => 'string',
            ],
            [
                'name' => 'restaurant_name',
                'value' => 'Mi Restaurante',
                'target' => 'general',
                'type' => 'string',
            ],
        ];

        foreach ($configs as $config) {
            DB::table('configs')->updateOrInsert(
                ['name' => $config['name']],
                array_merge($config, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}