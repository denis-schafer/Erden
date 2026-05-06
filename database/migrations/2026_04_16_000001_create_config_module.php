<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('mysql_parent')->table('modules')->updateOrInsert(
            ['route' => 'config'],
            [
                'name' => 'Configuración',
                'icon' => 'bi-gear',
                'description' => 'Configuración global del sistema',
                'is_special' => true,
                'order' => 5,
                'package' => 'core',
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    public function down(): void
    {
        DB::connection('mysql_parent')->table('modules')->where('route', 'config')->delete();
    }
};