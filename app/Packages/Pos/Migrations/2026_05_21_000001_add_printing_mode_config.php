<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('configs')->updateOrInsert(
            ['name' => 'printing_mode'],
            [
                'value' => 'vps',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('configs')->where('name', 'printing_mode')->delete();
    }
};
