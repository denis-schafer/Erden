<?php

namespace App\Packages\HairSalon\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['name' => 'business_name', 'value' => 'Mi Peluquería', 'type' => 'text'],
            ['name' => 'business_address', 'value' => '', 'type' => 'text'],
            ['name' => 'business_phone', 'value' => '', 'type' => 'text'],
            ['name' => 'logo', 'value' => '', 'type' => 'image'],
            ['name' => 'primary_color', 'value' => '#212529', 'type' => 'color'],
            ['name' => 'secondary_color', 'value' => '#6c757d', 'type' => 'color'],
            ['name' => 'background_image', 'value' => '', 'type' => 'image'],
            ['name' => 'cash_register_mode', 'value' => 'simple', 'type' => 'select'],
            ['name' => 'calendar_start_time', 'value' => '08:00', 'type' => 'time'],
            ['name' => 'calendar_end_time', 'value' => '20:00', 'type' => 'time'],
            ['name' => 'calendar_view_mode', 'value' => 'weekly', 'type' => 'select'],
            ['name' => 'default_operator_id', 'value' => '', 'type' => 'select'],
            ['name' => 'sidebar_drag_drop', 'value' => '0', 'type' => 'boolean'],
        ];

        foreach ($configs as $config) {
            $existing = DB::table('hairsalon_configs')->where('name', $config['name'])->first();

            if ($existing) {
                // Only update type (in case it changed between versions), never overwrite value
                DB::table('hairsalon_configs')->where('id', $existing->id)->update([
                    'type' => $config['type'],
                    'updated_at' => now(),
                ]);
            } else {
                // Insert with default value only for new configs
                DB::table('hairsalon_configs')->insert([
                    'name' => $config['name'],
                    'value' => $config['value'],
                    'type' => $config['type'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($this->command) {
            $this->command->info('HairSalon configs seeded: ' . count($configs) . ' configs');
        }
    }
}
