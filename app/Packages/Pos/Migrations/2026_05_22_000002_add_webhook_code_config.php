<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        try {
            $exists = DB::table('configs')->where('name', 'webhook_code')->exists();
            if (!$exists) {
                DB::table('configs')->insert([
                    'name' => 'webhook_code',
                    'value' => '',
                    'type' => 'string',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }
    }

    public function down(): void
    {
        try {
            DB::table('configs')->where('name', 'webhook_code')->delete();
        } catch (\Exception $e) {
            // Table may not exist
        }
    }
};
