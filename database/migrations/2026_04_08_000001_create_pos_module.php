<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('mysql_parent')->table('modules')->updateOrInsert(
            ['route' => 'pos'],
            [
                'name' => 'POS',
                'icon' => 'bi-cart3',
                'description' => 'Sistema de Punto de Venta',
                'is_special' => true,
                'order' => 50,
                'package' => 'pos',
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    public function down(): void
    {
        DB::connection('mysql_parent')->table('modules')->where('route', 'pos')->delete();
    }
};
