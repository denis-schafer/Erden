<?php

namespace App\Packages\QuotaAdmin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'admin'],
            ['id' => 2, 'name' => 'cashier'],
            ['id' => 3, 'name' => 'stats'],
            ['id' => 4, 'name' => 'partner'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                ['name' => $role['name'], 'updated_at' => now()]
            );
        }

        if ($this->command) {
            $this->command->info('Roles seeded: admin, cashier, stats, partner');
        }
    }
}
