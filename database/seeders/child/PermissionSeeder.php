<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Check if permissions table exists
        if (!Schema::hasTable('permissions')) {
            $this->command->warn('Table permissions does not exist. Skipping PermissionSeeder.');
            return;
        }
        
        // Get existing columns
        $columns = Schema::getColumnListing('permissions');
        
        // Check if slug column exists
        $hasSlugColumn = in_array('slug', $columns);
        
        if ($hasSlugColumn) {
            $permissions = [
                // Users
                ['name' => 'Ver Usuarios', 'slug' => 'users_read', 'module' => 'users', 'action' => 'read'],
                ['name' => 'Crear Usuarios', 'slug' => 'users_create', 'module' => 'users', 'action' => 'create'],
                ['name' => 'Editar Usuarios', 'slug' => 'users_update', 'module' => 'users', 'action' => 'update'],
                ['name' => 'Eliminar Usuarios', 'slug' => 'users_delete', 'module' => 'users', 'action' => 'delete'],
                
                // Roles
                ['name' => 'Ver Roles', 'slug' => 'roles_read', 'module' => 'roles', 'action' => 'read'],
                ['name' => 'Crear Roles', 'slug' => 'roles_create', 'module' => 'roles', 'action' => 'create'],
                ['name' => 'Editar Roles', 'slug' => 'roles_update', 'module' => 'roles', 'action' => 'update'],
                ['name' => 'Eliminar Roles', 'slug' => 'roles_delete', 'module' => 'roles', 'action' => 'delete'],
                
                // POS - All POS modules get read permission by default for POS users
                ['name' => 'Ver Caja', 'slug' => 'pos-caja_read', 'module' => 'pos-caja', 'action' => 'read'],
                ['name' => 'Ver Categorías', 'slug' => 'pos-categories_read', 'module' => 'pos-categories', 'action' => 'read'],
                ['name' => 'Ver Productos', 'slug' => 'pos-products_read', 'module' => 'pos-products', 'action' => 'read'],
                ['name' => 'Ver Órdenes', 'slug' => 'pos-orders_read', 'module' => 'pos-orders', 'action' => 'read'],
                ['name' => 'Ver Usuarios POS', 'slug' => 'pos-users_read', 'module' => 'pos-users', 'action' => 'read'],
                ['name' => 'Ver Configuración', 'slug' => 'pos-config_read', 'module' => 'pos-config', 'action' => 'read'],
                ['name' => 'Ver QR', 'slug' => 'pos-qr_read', 'module' => 'pos-qr', 'action' => 'read'],
            ];

            foreach ($permissions as $permission) {
                $exists = DB::table('permissions')->where('slug', $permission['slug'])->exists();
                
                if (!$exists) {
                    DB::table('permissions')->insert($permission);
                }
            }
        }
        
        $this->command->info('Permissions seeded successfully.');
    }
}