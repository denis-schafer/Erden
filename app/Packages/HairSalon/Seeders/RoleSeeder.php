<?php

namespace App\Packages\HairSalon\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'admin'],
            ['id' => 6, 'name' => 'operator'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['id' => $role['id']],
                ['name' => $role['name'], 'created_at' => now(), 'updated_at' => now()]
            );
        }

        if ($this->command) {
            $this->command->info('HairSalon roles seeded: admin, operator');
        }
    }
}
