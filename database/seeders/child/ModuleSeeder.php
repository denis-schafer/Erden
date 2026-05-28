<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        // First ensure base modules exist
        $baseModules = [
            ['name' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-speedometer2', 'is_special' => true, 'order' => 1],
            ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'is_special' => true, 'order' => 2],
            ['name' => 'Usuarios', 'route' => 'users', 'icon' => 'bi-people', 'is_special' => false, 'order' => 3],
            ['name' => 'Roles', 'route' => 'roles', 'icon' => 'bi-shield-lock', 'is_special' => false, 'order' => 4],
        ];

        foreach ($baseModules as $module) {
            DB::table('modules')->updateOrInsert(
                ['route' => $module['route']],
                array_merge($module, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        // Check if POS module is enabled for this company
        $companyDb = config('database.connections.mysql.database');
        $this->command->info("Checking POS module for company: $companyDb");
        
        $hasPos = $this->companyHasPosModule($companyDb);
        $this->command->info("POS module detected: " . ($hasPos ? 'YES' : 'NO'));
        
        if ($hasPos) {
            $posModules = [
                ['name' => 'Caja', 'route' => 'pos-caja', 'icon' => 'bi-cart3', 'is_special' => false, 'order' => 10, 'package' => 'pos'],
                ['name' => 'Categorías', 'route' => 'pos-categories', 'icon' => 'bi-tags', 'is_special' => false, 'order' => 11, 'package' => 'pos'],
                ['name' => 'Productos', 'route' => 'pos-products', 'icon' => 'bi-box-seam', 'is_special' => false, 'order' => 12, 'package' => 'pos'],
                ['name' => 'Órdenes', 'route' => 'pos-orders', 'icon' => 'bi-receipt', 'is_special' => false, 'order' => 13, 'package' => 'pos'],
                ['name' => 'Usuarios POS', 'route' => 'pos-users', 'icon' => 'bi-people', 'is_special' => false, 'order' => 14, 'package' => 'pos'],
                ['name' => 'Configuración', 'route' => 'pos-config', 'icon' => 'bi-sliders', 'is_special' => false, 'order' => 15, 'package' => 'pos'],
                ['name' => 'QR', 'route' => 'pos-qr', 'icon' => 'bi-qr-code', 'is_special' => false, 'order' => 16, 'package' => 'pos'],
                ['name' => 'Documentación', 'route' => 'pos-documentation', 'icon' => 'bi-book', 'is_special' => false, 'order' => 17, 'package' => 'pos'],
            ];

            foreach ($posModules as $module) {
                DB::table('modules')->updateOrInsert(
                    ['route' => $module['route']],
                    array_merge($module, ['created_at' => now(), 'updated_at' => now()])
                );
                $this->command->info("Inserted/Updated POS module: " . $module['name']);
            }
        }

        // Show final modules
        $allModules = DB::table('modules')->orderBy('order')->get();
        $this->command->info("Total modules in DB: " . $allModules->count());
        foreach ($allModules as $mod) {
            $this->command->info("  - {$mod->name} ({$mod->route})");
        }
    }
    
    private function companyHasPosModule(string $companyDb): bool
    {
        if (!$companyDb || $companyDb === 'erden') {
            return false;
        }
        
        try {
            // Direct query to parent DB
            $pdo = DB::connection()->getPDO();
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as cnt
                FROM erden.company_modules cm
                JOIN erden.modules m ON cm.module_id = m.id
                WHERE cm.company_id = (SELECT id FROM erden.companies WHERE db = ? LIMIT 1)
                AND m.route = 'pos'
            ");
            $stmt->execute([$companyDb]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return ($result['cnt'] ?? 0) > 0;
        } catch (\Exception $e) {
            $this->command->warn('Error checking POS module: ' . $e->getMessage());
            
            // Fallback: try checking if company has POS in company_modules
            try {
                $pdo = DB::connection()->getPDO();
                $stmt = $pdo->prepare("
                    SELECT cm.company_id 
                    FROM erden.company_modules cm
                    WHERE cm.company_id = (SELECT id FROM erden.companies WHERE db = ? LIMIT 1)
                    LIMIT 1
                ");
                $stmt->execute([$companyDb]);
                return $stmt->rowCount() > 0;
            } catch (\Exception $e2) {
                // If we can't determine, assume POS is enabled
                return true;
            }
        }
    }
}