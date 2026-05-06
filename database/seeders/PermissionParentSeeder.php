<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionParentSeeder extends Seeder
{
    protected $connection = 'mysql_parent';
    
    public function run(): void
    {
        echo "=== PermissionParentSeeder START ===\n";
        
        $permissions = [
            ['name' => 'Ver Usuarios', 'slug' => 'users_read', 'module' => 'users', 'action' => 'read'],
            ['name' => 'Crear Usuarios', 'slug' => 'users_create', 'module' => 'users', 'action' => 'create'],
            ['name' => 'Editar Usuarios', 'slug' => 'users_update', 'module' => 'users', 'action' => 'update'],
            ['name' => 'Eliminar Usuarios', 'slug' => 'users_delete', 'module' => 'users', 'action' => 'delete'],
            ['name' => 'Ver Roles', 'slug' => 'roles_read', 'module' => 'roles', 'action' => 'read'],
            ['name' => 'Crear Roles', 'slug' => 'roles_create', 'module' => 'roles', 'action' => 'create'],
            ['name' => 'Editar Roles', 'slug' => 'roles_update', 'module' => 'roles', 'action' => 'update'],
            ['name' => 'Eliminar Roles', 'slug' => 'roles_delete', 'module' => 'roles', 'action' => 'delete'],
            ['name' => 'Ver Módulos', 'slug' => 'admin-modules_read', 'module' => 'admin-modules', 'action' => 'read'],
            ['name' => 'Crear Módulos', 'slug' => 'admin-modules_create', 'module' => 'admin-modules', 'action' => 'create'],
            ['name' => 'Editar Módulos', 'slug' => 'admin-modules_update', 'module' => 'admin-modules', 'action' => 'update'],
            ['name' => 'Eliminar Módulos', 'slug' => 'admin-modules_delete', 'module' => 'admin-modules', 'action' => 'delete'],
            ['name' => 'Ver Empresas', 'slug' => 'companies_read', 'module' => 'companies', 'action' => 'read'],
            ['name' => 'Crear Empresas', 'slug' => 'companies_create', 'module' => 'companies', 'action' => 'create'],
            ['name' => 'Editar Empresas', 'slug' => 'companies_update', 'module' => 'companies', 'action' => 'update'],
            ['name' => 'Eliminar Empresas', 'slug' => 'companies_delete', 'module' => 'companies', 'action' => 'delete'],
        ];

        foreach ($permissions as $permission) {
            try {
                $exists = DB::connection('mysql_parent')->table('permissions')
                    ->where('slug', $permission['slug'])
                    ->exists();
                
                if (!$exists) {
                    DB::connection('mysql_parent')->table('permissions')->insert($permission);
                    echo "Created permission: {$permission['name']}\n";
                } else {
                    echo "Permission already exists: {$permission['name']}\n";
                }
            } catch (\Exception $e) {
                echo "ERROR creating permission {$permission['name']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "=== PermissionParentSeeder END ===\n";
    }
}
