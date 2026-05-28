<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Check if tables exist
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('role_permission')) {
            $this->command->warn('Required tables do not exist. Skipping RolePermissionSeeder.');
            return;
        }
        
        // Get roles columns to determine how to find roles
        $roleColumns = Schema::getColumnListing('roles');
        $hasSlugColumn = in_array('slug', $roleColumns);
        
        if ($hasSlugColumn) {
            $adminRole = DB::table('roles')->where('slug', 'admin')->first();
            $operatorRole = DB::table('roles')->where('slug', 'operator')->first();
        } else {
            $adminRole = DB::table('roles')->where('name', 'Admin')->first();
            $operatorRole = DB::table('roles')->where('name', 'Operator')->first();
        }
        
        if (!$adminRole || !$operatorRole) {
            $this->command->warn('Roles not found. Please run RoleSeeder first.');
            return;
        }
        
        $allPermissions = DB::table('permissions')->get();
        
        foreach ($allPermissions as $permission) {
            $exists = DB::table('role_permission')
                ->where('role_id', $adminRole->id)
                ->where('permission_id', $permission->id)
                ->exists();
            
            if (!$exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permission->id
                ]);
            }
        }
        
        $readPermissions = DB::table('permissions')->where('action', 'read')->get();

        foreach ($readPermissions as $permission) {
            if ($permission->slug === 'pos-documentation_read') continue;
            $exists = DB::table('role_permission')
                ->where('role_id', $operatorRole->id)
                ->where('permission_id', $permission->id)
                ->exists();
            
            if (!$exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $operatorRole->id,
                    'permission_id' => $permission->id
                ]);
            }
        }
        
        $this->command->info('Role permissions seeded successfully.');
    }
}