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
        ];

        foreach ($configs as $config) {
            DB::table('quota_configs')->updateOrInsert(
                ['name' => $config['name']],
                array_merge($config, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        if ($this->command) {
            $this->command->info('QuotaAdmin configs seeded');
        }
    }
}
