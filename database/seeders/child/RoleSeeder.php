<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Check if roles table exists
        if (!Schema::hasTable('roles')) {
            $this->command->warn('Table roles does not exist. Skipping RoleSeeder.');
            return;
        }
        
        // Get existing columns
        $columns = Schema::getColumnListing('roles');
        
        // Check what columns exist
        $hasSlugColumn = in_array('slug', $columns);
        $hasIsGlobalColumn = in_array('is_global', $columns);
        
        if ($hasSlugColumn && $hasIsGlobalColumn) {
            // New format with slug and is_global
            $roles = [
                ['name' => 'Admin', 'slug' => 'admin', 'is_global' => false],
                ['name' => 'Operator', 'slug' => 'operator', 'is_global' => false],
            ];

            foreach ($roles as $role) {
                $exists = DB::table('roles')->where('slug', $role['slug'])->exists();
                if (!$exists) {
                    DB::table('roles')->insert($role);
                }
            }
        } elseif ($hasSlugColumn) {
            // Has slug but not is_global
            $roles = [
                ['name' => 'Admin', 'slug' => 'admin'],
                ['name' => 'Operator', 'slug' => 'operator'],
            ];

            foreach ($roles as $role) {
                $exists = DB::table('roles')->where('slug', $role['slug'])->exists();
                if (!$exists) {
                    DB::table('roles')->insert($role);
                }
            }
        } else {
            // Legacy table - just name column
            $roles = [
                ['name' => 'Admin'],
                ['name' => 'Operator'],
            ];

            foreach ($roles as $role) {
                $exists = DB::table('roles')->where('name', $role['name'])->exists();
                if (!$exists) {
                    DB::table('roles')->insert($role);
                }
            }
        }
        
        $this->command->info('Roles seeded successfully.');
    }
}