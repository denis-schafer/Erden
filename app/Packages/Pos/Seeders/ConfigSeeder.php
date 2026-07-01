<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            // General
            ['name' => 'business_name', 'value' => '', 'type' => 'text'],
            ['name' => 'business_address', 'value' => '', 'type' => 'text'],
            ['name' => 'business_phone', 'value' => '', 'type' => 'text'],
            ['name' => 'business_nit', 'value' => '', 'type' => 'text'],
            ['name' => 'ticket_title', 'value' => 'MI NEGOCIO', 'type' => 'text'],
            
            // OAuth - MercadoPago
            ['name' => 'redirect_uri', 'value' => '', 'type' => 'text'],
            ['name' => 'mp_access_token', 'value' => '', 'type' => 'text'],
            ['name' => 'mp_token_expires_at', 'value' => '', 'type' => 'text'],
            
            // Test mode
            ['name' => 'test_mode', 'value' => '0', 'type' => 'string'],
            // Theming
            ['name' => 'primary_color', 'value' => '#212529', 'type' => 'color'],
            ['name' => 'secondary_color', 'value' => '#6c757d', 'type' => 'color'],
            ['name' => 'logo', 'value' => '', 'type' => 'image'],
            ['name' => 'background_image', 'value' => '', 'type' => 'image'],
            ['name' => 'sidebar_drag_drop', 'value' => '0', 'type' => 'boolean'],
        ];

        foreach ($configs as $config) {
            $insertData = [
                'name' => $config['name'],
                'value' => $config['value'],
                'type' => $config['type'],
                'created_at' => now(),
                'updated_at' => now()
            ];
            
            DB::table('configs')->updateOrInsert(
                ['name' => $config['name']],
                $insertData
            );
        }

        if ($this->command) {
            $this->command->info('POS Configs seeded');
        }
    }
}