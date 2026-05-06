<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusOrderSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'pending'],
            ['name' => 'in_progress'],
            ['name' => 'completed'],
            ['name' => 'cancelled'],
        ];

        foreach ($statuses as $status) {
            DB::table('status_orders')->updateOrInsert(
                ['name' => $status['name']],
                []
            );
        }

        if ($this->command) {
            $this->command->info('Status orders seeded');
        }
    }
}
