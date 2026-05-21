<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('roles')->whereNotIn('name', ['admin', 'cashier', 'stats'])->delete();
        
        $roles = [
            ['id' => 1, 'name' => 'admin'],
            ['id' => 2, 'name' => 'cashier'],
            ['id' => 3, 'name' => 'stats'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                ['name' => $role['name']]
            );
        }

        if ($this->command) {
            $this->command->info('Roles seeded: admin, cashier, stats');
        }
    }
}
