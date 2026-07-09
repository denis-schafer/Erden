<?php

namespace App\Packages\Academy\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['name' => 'portal_title', 'value' => 'Academy - Aprendizaje Interactivo', 'type' => 'text'],
            ['name' => 'primary_color', 'value' => '#4F46E5', 'type' => 'color'],
            ['name' => 'secondary_color', 'value' => '#7C3AED', 'type' => 'color'],
            ['name' => 'logo', 'value' => '', 'type' => 'image'],
            ['name' => 'background_image', 'value' => '', 'type' => 'image'],
            ['name' => 'sidebar_drag_drop', 'value' => '0', 'type' => 'boolean'],
            ['name' => 'max_attempts_per_exam', 'value' => '3', 'type' => 'number'],
        ];

        foreach ($configs as $config) {
            DB::table('academy_configs')->updateOrInsert(
                ['name' => $config['name']],
                $config
            );
        }
    }
}
