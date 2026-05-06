<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Eliminar roles que no sean admin o cashier
        DB::table('roles')->whereNotIn('name', ['admin', 'cashier'])->delete();
        
        $roles = [
            ['id' => 1, 'name' => 'admin'],
            ['id' => 2, 'name' => 'cashier'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                ['name' => $role['name']]
            );
        }

        if ($this->command) {
            $this->command->info('Roles seeded: admin, cashier');
        }
    }
}
