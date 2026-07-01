<?php

namespace App\Packages\HairSalon\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Ver Dashboard', 'slug' => 'hairsalon-dashboard_read', 'module' => 'hairsalon-dashboard', 'action' => 'read'],
            ['name' => 'Ver Clientes', 'slug' => 'hairsalon-clients_read', 'module' => 'hairsalon-clients', 'action' => 'read'],
            ['name' => 'Crear Cliente', 'slug' => 'hairsalon-clients_create', 'module' => 'hairsalon-clients', 'action' => 'create'],
            ['name' => 'Editar Cliente', 'slug' => 'hairsalon-clients_update', 'module' => 'hairsalon-clients', 'action' => 'update'],
            ['name' => 'Eliminar Cliente', 'slug' => 'hairsalon-clients_delete', 'module' => 'hairsalon-clients', 'action' => 'delete'],
            ['name' => 'Ver Servicios', 'slug' => 'hairsalon-services_read', 'module' => 'hairsalon-services', 'action' => 'read'],
            ['name' => 'Crear Servicio', 'slug' => 'hairsalon-services_create', 'module' => 'hairsalon-services', 'action' => 'create'],
            ['name' => 'Editar Servicio', 'slug' => 'hairsalon-services_update', 'module' => 'hairsalon-services', 'action' => 'update'],
            ['name' => 'Eliminar Servicio', 'slug' => 'hairsalon-services_delete', 'module' => 'hairsalon-services', 'action' => 'delete'],
            ['name' => 'Ver Caja', 'slug' => 'hairsalon-cashier_read', 'module' => 'hairsalon-cashier', 'action' => 'read'],
            ['name' => 'Cobrar', 'slug' => 'hairsalon-cashier_create', 'module' => 'hairsalon-cashier', 'action' => 'create'],
            ['name' => 'Ver Finanzas', 'slug' => 'hairsalon-finances_read', 'module' => 'hairsalon-finances', 'action' => 'read'],
            ['name' => 'Ver Productos', 'slug' => 'hairsalon-products_read', 'module' => 'hairsalon-products', 'action' => 'read'],
            ['name' => 'Crear Producto', 'slug' => 'hairsalon-products_create', 'module' => 'hairsalon-products', 'action' => 'create'],
            ['name' => 'Editar Producto', 'slug' => 'hairsalon-products_update', 'module' => 'hairsalon-products', 'action' => 'update'],
            ['name' => 'Eliminar Producto', 'slug' => 'hairsalon-products_delete', 'module' => 'hairsalon-products', 'action' => 'delete'],
            ['name' => 'Ver Configuración', 'slug' => 'hairsalon-config_read', 'module' => 'hairsalon-config', 'action' => 'read'],
            ['name' => 'Editar Configuración', 'slug' => 'hairsalon-config_update', 'module' => 'hairsalon-config', 'action' => 'update'],
            ['name' => 'Ver Estadísticas', 'slug' => 'hairsalon-statistics_read', 'module' => 'hairsalon-statistics', 'action' => 'read'],
            ['name' => 'Exportar Estadísticas', 'slug' => 'hairsalon-statistics_export', 'module' => 'hairsalon-statistics', 'action' => 'export'],
            ['name' => 'Ver Log', 'slug' => 'hairsalon-log_read', 'module' => 'hairsalon-log', 'action' => 'read'],
            ['name' => 'Ver Usuarios', 'slug' => 'hairsalon-users_read', 'module' => 'hairsalon-users', 'action' => 'read'],
            ['name' => 'Crear Usuario', 'slug' => 'hairsalon-users_create', 'module' => 'hairsalon-users', 'action' => 'create'],
            ['name' => 'Editar Usuario', 'slug' => 'hairsalon-users_update', 'module' => 'hairsalon-users', 'action' => 'update'],
            ['name' => 'Eliminar Usuario', 'slug' => 'hairsalon-users_delete', 'module' => 'hairsalon-users', 'action' => 'delete'],
            ['name' => 'Ver Turnos', 'slug' => 'hairsalon-appointments_read', 'module' => 'hairsalon-appointments', 'action' => 'read'],
            ['name' => 'Crear Turno', 'slug' => 'hairsalon-appointments_create', 'module' => 'hairsalon-appointments', 'action' => 'create'],
            ['name' => 'Editar Turno', 'slug' => 'hairsalon-appointments_update', 'module' => 'hairsalon-appointments', 'action' => 'update'],
            ['name' => 'Eliminar Turno', 'slug' => 'hairsalon-appointments_delete', 'module' => 'hairsalon-appointments', 'action' => 'delete'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                array_merge($perm, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        if ($this->command) {
            $this->command->info('HairSalon permissions seeded: ' . count($permissions) . ' permissions');
        }
    }
}
