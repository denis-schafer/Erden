<?php

namespace App\Packages\QuotaAdmin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['name' => 'business_name', 'value' => 'Natatorio', 'type' => 'string'],
    ['name' => 'redirect_uri',  'value' => 'https://www.erden.com.ar/mp/callback', 'type' => 'string'],
            ['name' => 'mp_access_token', 'value' => '', 'type' => 'string'],
            ['name' => 'default_cashier_id', 'value' => '', 'type' => 'string'],
            ['name' => 'whatsapp_message_template', 'value' => 'Hola %name%, recordá que podés gestionar tus cuotas en el portal de socios.', 'type' => 'text'],
            ['name' => 'portal_logo', 'value' => '', 'type' => 'string'],
            ['name' => 'portal_bg', 'value' => '', 'type' => 'string'],
            ['name' => 'portal_primary_color', 'value' => '#667eea', 'type' => 'string'],
            ['name' => 'portal_secondary_color', 'value' => '#764ba2', 'type' => 'string'],
        ];

        foreach ($configs as $config) {
            $existing = DB::table('quota_configs')->where('name', $config['name'])->first();
            if (!$existing) {
                DB::table('quota_configs')->insert(array_merge($config, ['created_at' => now(), 'updated_at' => now()]));
            }
        }

        if ($this->command) {
            $this->command->info('QuotaAdmin configs seeded');
        }
    }
}
