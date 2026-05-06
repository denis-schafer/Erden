<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionParentSeeder extends Seeder
{
    protected $connection = 'mysql_parent';
    
    public function run(): void
    {
        echo "=== RolePermissionParentSeeder START ===\n";
        
        $this->ensureRolesExist();
        $this->assignPermissionsToGlobalRoles();
        
        echo "=== RolePermissionParentSeeder END ===\n";
    }
    
    protected function ensureRolesExist(): void
    {
        echo "Ensuring roles exist...\n";
        
        $globalRoles = [
            ['name' => 'Admin Global', 'slug' => 'admin-global', 'is_global' => true],
            ['name' => 'Soporte', 'slug' => 'soporte', 'is_global' => true],
        ];
        
        foreach ($globalRoles as $role) {
            $exists = DB::connection('mysql_parent')->table('roles')
                ->where('slug', $role['slug'])
                ->exists();
            
            if (!$exists) {
                DB::connection('mysql_parent')->table('roles')->insert($role);
                echo "Created role: {$role['name']}\n";
            }
        }
        
        $localRoles = [
            ['name' => 'Admin', 'slug' => 'admin', 'is_global' => false],
            ['name' => 'Operator', 'slug' => 'operator', 'is_global' => false],
        ];
        
        foreach ($localRoles as $role) {
            $exists = DB::connection('mysql_parent')->table('roles')
                ->where('slug', $role['slug'])
                ->exists();
            
            if (!$exists) {
                DB::connection('mysql_parent')->table('roles')->insert($role);
                echo "Created role: {$role['name']}\n";
            }
        }
    }
    
    protected function assignPermissionsToGlobalRoles(): void
    {
        echo "Assigning permissions to global roles...\n";
        
        // Clear all existing role_permissions first to ensure clean state
        DB::connection('mysql_parent')->table('role_permission')->delete();
        
        // Admin Global gets all permissions
        $adminGlobalRole = DB::connection('mysql_parent')->table('roles')
            ->where('slug', 'admin-global')
            ->first();
            
        if ($adminGlobalRole) {
            $allPermissions = DB::connection('mysql_parent')->table('permissions')->get();
            
            foreach ($allPermissions as $permission) {
                DB::connection('mysql_parent')->table('role_permission')->insert([
                    'role_id' => $adminGlobalRole->id,
                    'permission_id' => $permission->id
                ]);
            }
            echo "Assigned all permissions to Admin Global role.\n";
        } else {
            echo "ERROR: Admin Global role not found!\n";
        }
        
        // Soporte gets limited permissions (only read and update for Users and Roles)
        $soporteRole = DB::connection('mysql_parent')->table('roles')
            ->where('slug', 'soporte')
            ->first();
            
        if ($soporteRole) {
            $limitedPermissions = ['users_read', 'users_update', 'roles_read', 'roles_update'];
            
            foreach ($limitedPermissions as $slug) {
                $permission = DB::connection('mysql_parent')->table('permissions')
                    ->where('slug', $slug)
                    ->first();
                
                if ($permission) {
                    DB::connection('mysql_parent')->table('role_permission')->insert([
                        'role_id' => $soporteRole->id,
                        'permission_id' => $permission->id
                    ]);
                }
            }
            echo "Assigned limited permissions to Soporte role.\n";
        }
        
        // Admin (local) gets all permissions
        $adminRole = DB::connection('mysql_parent')->table('roles')
            ->where('slug', 'admin')
            ->first();
        
        if ($adminRole) {
            $allPermissions = DB::connection('mysql_parent')->table('permissions')->get();
            
            foreach ($allPermissions as $permission) {
                DB::connection('mysql_parent')->table('role_permission')->insert([
                    'role_id' => $adminRole->id,
                    'permission_id' => $permission->id
                ]);
            }
            echo "Assigned all permissions to Admin role.\n";
        }
        
        // Operator gets only read permissions
        $operatorRole = DB::connection('mysql_parent')->table('roles')
            ->where('slug', 'operator')
            ->first();
        
        if ($operatorRole) {
            $readPermissions = DB::connection('mysql_parent')->table('permissions')
                ->where('action', 'read')
                ->get();
            
            foreach ($readPermissions as $permission) {
                DB::connection('mysql_parent')->table('role_permission')->insert([
                    'role_id' => $operatorRole->id,
                    'permission_id' => $permission->id
                ]);
            }
            echo "Assigned read permissions to Operator role.\n";
        }
    }
}
