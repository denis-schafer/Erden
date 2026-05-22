<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = DB::getTablePrefix();

        if (!in_array('test', array_column(DB::select("SHOW COLUMNS FROM `{$prefix}orders`"), 'Field'))) {
            DB::statement("ALTER TABLE `{$prefix}orders` ADD COLUMN test TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!in_array('test', array_column(DB::select("SHOW COLUMNS FROM `{$prefix}products`"), 'Field'))) {
            DB::statement("ALTER TABLE `{$prefix}products` ADD COLUMN test TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!in_array('test', array_column(DB::select("SHOW COLUMNS FROM `{$prefix}categories`"), 'Field'))) {
            DB::statement("ALTER TABLE `{$prefix}categories` ADD COLUMN test TINYINT(1) NOT NULL DEFAULT 0");
        }

        if (!in_array('test', array_column(DB::select("SHOW COLUMNS FROM `{$prefix}users`"), 'Field'))) {
            DB::statement("ALTER TABLE `{$prefix}users` ADD COLUMN test TINYINT(1) NOT NULL DEFAULT 0");
        }

        DB::table('configs')->updateOrInsert(
            ['name' => 'test_mode'],
            [
                'value' => '0',
                'type' => 'string',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $prefix = DB::getTablePrefix();
        DB::statement("ALTER TABLE `{$prefix}orders` DROP COLUMN IF EXISTS test");
        DB::statement("ALTER TABLE `{$prefix}products` DROP COLUMN IF EXISTS test");
        DB::statement("ALTER TABLE `{$prefix}categories` DROP COLUMN IF EXISTS test");
        DB::statement("ALTER TABLE `{$prefix}users` DROP COLUMN IF EXISTS test");
        DB::table('configs')->where('name', 'test_mode')->delete();
    }
};
