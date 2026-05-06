<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosStatusOrderSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'pending', 'default' => true],
            ['name' => 'preparing'],
            ['name' => 'ready'],
            ['name' => 'delivered'],
            ['name' => 'cancelled'],
        ];

        foreach ($statuses as $status) {
            DB::table('status_orders')->updateOrInsert(
                ['name' => $status['name']],
                [
                    'name' => $status['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}