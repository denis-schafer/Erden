<?php

namespace App\Packages\QuotaAdmin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Ver Dashboard Cuotas', 'slug' => 'quota-dashboard_read', 'module' => 'quota-dashboard', 'action' => 'read'],
            ['name' => 'Ver Socios', 'slug' => 'quota-partners_read', 'module' => 'quota-partners', 'action' => 'read'],
            ['name' => 'Crear Socio', 'slug' => 'quota-partners_create', 'module' => 'quota-partners', 'action' => 'create'],
            ['name' => 'Editar Socio', 'slug' => 'quota-partners_update', 'module' => 'quota-partners', 'action' => 'update'],
            ['name' => 'Eliminar Socio', 'slug' => 'quota-partners_delete', 'module' => 'quota-partners', 'action' => 'delete'],
            ['name' => 'Resetear Password Socio', 'slug' => 'quota-partners_reset_password', 'module' => 'quota-partners', 'action' => 'reset_password'],
            ['name' => 'Ver Planes', 'slug' => 'quota-plans_read', 'module' => 'quota-plans', 'action' => 'read'],
            ['name' => 'Crear Plan', 'slug' => 'quota-plans_create', 'module' => 'quota-plans', 'action' => 'create'],
            ['name' => 'Editar Plan', 'slug' => 'quota-plans_update', 'module' => 'quota-plans', 'action' => 'update'],
            ['name' => 'Eliminar Plan', 'slug' => 'quota-plans_delete', 'module' => 'quota-plans', 'action' => 'delete'],
            ['name' => 'Generar Cuotas', 'slug' => 'quota-plans_generate', 'module' => 'quota-plans', 'action' => 'generate'],
            ['name' => 'Ver Cuotas', 'slug' => 'quota-items_read', 'module' => 'quota-items', 'action' => 'read'],
            ['name' => 'Cobrar Cuota', 'slug' => 'quota-items_pay', 'module' => 'quota-items', 'action' => 'pay'],
            ['name' => 'Eliminar Cuota', 'slug' => 'quota-items_delete', 'module' => 'quota-items', 'action' => 'delete'],
            ['name' => 'Rendir Cuota', 'slug' => 'quota-items_rendered', 'module' => 'quota-items', 'action' => 'rendered'],
            ['name' => 'Ver Pagos', 'slug' => 'quota-payments_read', 'module' => 'quota-payments', 'action' => 'read'],
            ['name' => 'Rendir Pago', 'slug' => 'quota-payments_rendered', 'module' => 'quota-payments', 'action' => 'rendered'],
            ['name' => 'Ver Configuración', 'slug' => 'quota-config_read', 'module' => 'quota-config', 'action' => 'read'],
            ['name' => 'Editar Configuración', 'slug' => 'quota-config_update', 'module' => 'quota-config', 'action' => 'update'],
            ['name' => 'Ver Estadísticas', 'slug' => 'quota-statistics_read', 'module' => 'quota-statistics', 'action' => 'read'],
            ['name' => 'Exportar Estadísticas', 'slug' => 'quota-statistics_export', 'module' => 'quota-statistics', 'action' => 'export'],
            ['name' => 'Ver Portal Propio', 'slug' => 'quota-portal_read', 'module' => 'quota-portal', 'action' => 'read'],
            ['name' => 'Ver Usuarios', 'slug' => 'quota-users_read', 'module' => 'quota-users', 'action' => 'read'],
            ['name' => 'Crear Usuario', 'slug' => 'quota-users_create', 'module' => 'quota-users', 'action' => 'create'],
            ['name' => 'Editar Usuario', 'slug' => 'quota-users_update', 'module' => 'quota-users', 'action' => 'update'],
            ['name' => 'Eliminar Usuario', 'slug' => 'quota-users_delete', 'module' => 'quota-users', 'action' => 'delete'],
        ];

        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                array_merge($perm, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        if ($this->command) {
            $this->command->info('QuotaAdmin permissions seeded: ' . count($permissions) . ' permissions');
        }
    }
}
