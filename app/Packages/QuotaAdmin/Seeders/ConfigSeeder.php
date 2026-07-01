<?php

namespace App\Packages\QuotaAdmin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['name' => 'business_name', 'value' => 'Natatorio', 'type' => 'text'],
            ['name' => 'redirect_uri', 'value' => 'https://www.erden.com.ar/mp/callback', 'type' => 'text'],
            ['name' => 'mp_access_token', 'value' => '', 'type' => 'text'],
            ['name' => 'default_cashier_id', 'value' => '', 'type' => 'text'],
            ['name' => 'whatsapp_message_template', 'value' => 'Hola %name%, recordá que podés gestionar tus cuotas en el portal de socios.', 'type' => 'textarea'],
            ['name' => 'portal_logo', 'value' => '', 'type' => 'image'],
            ['name' => 'portal_bg', 'value' => '', 'type' => 'image'],
            ['name' => 'portal_primary_color', 'value' => '#667eea', 'type' => 'color'],
            ['name' => 'portal_secondary_color', 'value' => '#764ba2', 'type' => 'color'],
            ['name' => 'primary_color', 'value' => '#212529', 'type' => 'color'],
            ['name' => 'secondary_color', 'value' => '#6c757d', 'type' => 'color'],
            ['name' => 'logo', 'value' => '', 'type' => 'image'],
            ['name' => 'background_image', 'value' => '', 'type' => 'image'],
            ['name' => 'sidebar_drag_drop', 'value' => '0', 'type' => 'boolean'],
        ];

        foreach ($configs as $config) {
            $existing = DB::table('quota_configs')->where('name', $config['name'])->first();
            if ($existing) {
                DB::table('quota_configs')->where('id', $existing->id)->update([
                    'type' => $config['type'],
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('quota_configs')->insert([
                    'name' => $config['name'],
                    'value' => $config['value'],
                    'type' => $config['type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($this->command) {
            $this->command->info('QuotaAdmin configs seeded: ' . count($configs) . ' configs');
        }
    }
}
