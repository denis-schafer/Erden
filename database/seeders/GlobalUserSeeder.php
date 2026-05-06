<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class GlobalUserSeeder extends Seeder
{
    protected $connection = 'mysql_parent';
    
    public function run(): void
    {
        echo "=== GlobalUserSeeder START ===\n";
        
        // First ensure roles exist in the parent database
        $this->seedGlobalRoles();
        
        // Then create global users
        $this->seedGlobalUsers();
        
        echo "=== GlobalUserSeeder END ===\n";
    }
    
    protected function seedGlobalRoles(): void
    {
        echo "Seeding global roles...\n";
        
        $roles = [
            ['name' => 'Admin Global', 'slug' => 'admin-global', 'is_global' => true],
            ['name' => 'Soporte', 'slug' => 'soporte', 'is_global' => true],
        ];

        foreach ($roles as $role) {
            try {
                $exists = DB::connection('mysql_parent')->table('roles')
                    ->where('slug', $role['slug'])
                    ->exists();
                
                if (!$exists) {
                    DB::connection('mysql_parent')->table('roles')->insert($role);
                    echo "Created role: {$role['name']}\n";
                } else {
                    echo "Role already exists: {$role['name']}\n";
                }
            } catch (\Exception $e) {
                echo "ERROR creating role {$role['name']}: " . $e->getMessage() . "\n";
            }
        }
    }
    
    protected function seedGlobalUsers(): void
    {
        echo "Seeding global users...\n";
        
        // Clear existing users for clean state
        DB::connection('mysql_parent')->table('global_users')->delete();
        
        try {
            // Check if global_users table exists
            $hasTable = Schema::connection('mysql_parent')->hasTable('global_users');
            echo "global_users table exists: " . ($hasTable ? 'YES' : 'NO') . "\n";
            
            if (!$hasTable) {
                echo "ERROR: global_users table does not exist in parent DB!\n";
                return;
            }
            
            $adminRole = DB::connection('mysql_parent')->table('roles')
                ->where('slug', 'admin-global')
                ->first();
            
            if (!$adminRole) {
                echo "ERROR: Admin Global role not found! Please run GlobalRoleSeeder first.\n";
                return;
            }
            
            $soporteRole = DB::connection('mysql_parent')->table('roles')
                ->where('slug', 'soporte')
                ->first();
            
            if (!$soporteRole) {
                echo "ERROR: Soporte role not found! Please run GlobalRoleSeeder first.\n";
                return;
            }
            
            echo "Found admin-global role with ID: {$adminRole->id}\n";
            echo "Found soporte role with ID: {$soporteRole->id}\n";
            
            // Create denischafer with admin-global role
            DB::connection('mysql_parent')->table('global_users')->insert([
                'username' => 'denischafer',
                'name' => 'Denis Chafer',
                'password' => Hash::make('$0deJulio'),
                'role_id' => $adminRole->id,
                'company_id' => null,
                'is_global' => true
            ]);
            echo "Created global user: denischafer (role: admin-global)\n";
            
            // Create soporte with soporte role
            DB::connection('mysql_parent')->table('global_users')->insert([
                'username' => 'soporte',
                'name' => 'Soporte',
                'password' => Hash::make('soporte'),
                'role_id' => $soporteRole->id,
                'company_id' => null,
                'is_global' => true
            ]);
            echo "Created global user: soporte (role: soporte)\n";
            
        } catch (\Exception $e) {
            echo "ERROR seeding global users: " . $e->getMessage() . "\n";
        }
    }
}