<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GlobalRoleSeeder extends Seeder
{
    protected $connection = 'mysql_parent';
    
    public function run(): void
    {
        echo "=== GlobalRoleSeeder START ===\n";
        
        // Skip module seeding - already handled by migration_all command
        // Just seed roles
        $this->seedGlobalRoles();
        
        echo "=== GlobalRoleSeeder END ===\n";
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
}