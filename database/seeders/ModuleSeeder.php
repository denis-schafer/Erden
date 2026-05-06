<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        echo "=== ModuleSeeder START ===\n";
        
        // Use the mysql_parent connection that points to erden
        $connection = 'mysql_parent';
        
        // Verify modules table exists
        try {
            $hasTable = DB::connection($connection)->hasTable('modules');
            echo "modules table exists: " . ($hasTable ? 'YES' : 'NO') . "\n";
            if (!$hasTable) {
                echo "ERROR: modules table does not exist!\n";
                return;
            }
        } catch (\Exception $e) {
            echo "ERROR checking modules table: " . $e->getMessage() . "\n";
            // Try alternative connection
            try {
                config(['database.connections.mysql.database' => 'erden']);
                DB::purge('mysql');
                DB::reconnect('mysql');
                $hasTable = DB::connection('mysql')->hasTable('modules');
                echo "modules table exists (alt): " . ($hasTable ? 'YES' : 'NO') . "\n";
                if ($hasTable) {
                    $connection = 'mysql';
                } else {
                    return;
                }
            } catch (\Exception $e2) {
                echo "ERROR: " . $e2->getMessage() . "\n";
                return;
            }
        }
        
        $modules = [
            ['name' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-speedometer2', 'is_special' => 1, 'parent_id' => null, 'order' => 1],
            ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'is_special' => 1, 'parent_id' => null, 'order' => 2],
            ['name' => 'Admin Módulos', 'route' => 'admin-modules', 'icon' => 'bi-gear', 'is_special' => 0, 'parent_id' => null, 'order' => 3],
            ['name' => 'Admin Compañías', 'route' => 'admin-companies', 'icon' => 'bi-building', 'is_special' => 0, 'parent_id' => null, 'order' => 4],
            ['name' => 'Usuarios', 'route' => 'users', 'icon' => 'bi-people', 'is_special' => 0, 'parent_id' => null, 'order' => 5],
            ['name' => 'Roles', 'route' => 'roles', 'icon' => 'bi-shield-lock', 'is_special' => 0, 'parent_id' => null, 'order' => 6],
        ];

        foreach ($modules as $module) {
            try {
                $exists = DB::connection($connection)
                    ->table('modules')
                    ->where('route', $module['route'])
                    ->exists();
                
                if (!$exists) {
                    DB::connection($connection)->table('modules')->insert($module);
                    echo "Created module: {$module['name']} ({$module['route']})\n";
                } else {
                    echo "Module already exists: {$module['name']}\n";
                }
            } catch (\Exception $e) {
                echo "ERROR with module {$module['name']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "=== ModuleSeeder END ===\n";
    }
}