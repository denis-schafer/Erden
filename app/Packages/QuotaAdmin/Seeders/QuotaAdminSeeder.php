<?php

namespace App\Packages\QuotaAdmin\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class QuotaAdminSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            \App\Packages\QuotaAdmin\Seeders\RoleSeeder::class,
            \App\Packages\QuotaAdmin\Seeders\PermissionSeeder::class,
            \App\Packages\QuotaAdmin\Seeders\ConfigSeeder::class,
        ]);

        $this->seedRolePermissions();
        $this->seedModules();
        $this->seedAdminUser();

        if ($this->command) {
            $this->command->info('QuotaAdmin package seeded successfully!');
        }
    }

    protected function seedRolePermissions(): void
    {
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        $cashierRole = DB::table('roles')->where('name', 'cashier')->first();
        $statsRole = DB::table('roles')->where('name', 'stats')->first();
        $partnerRole = DB::table('roles')->where('name', 'partner')->first();
        $limitedCollectorRole = DB::table('roles')->where('name', 'limited_collector')->first();

        if (!$adminRole) return;

        $allPermissions = DB::table('permissions')->get();

        // Admin gets all permissions
        foreach ($allPermissions as $perm) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $adminRole->id, 'permission_id' => $perm->id],
                ['role_id' => $adminRole->id, 'permission_id' => $perm->id]
            );
        }

        // Cashier permissions
        if ($cashierRole) {
            $cashierSlugs = [
                'menu_read',
                'quota-dashboard_read',
                'quota-partners_read',
                'quota-partners_create',
                'quota-partners_update',
                'quota-items_read',
                'quota-items_pay',
                'quota-items_rendered',
                'quota-payments_read',
                'quota-payments_rendered',
                'quota-statistics_read',
            ];

            DB::table('role_permission')->where('role_id', $cashierRole->id)->delete();
            foreach ($cashierSlugs as $slug) {
                $perm = DB::table('permissions')->where('slug', $slug)->first();
                if ($perm) {
                    DB::table('role_permission')->insert([
                        'role_id' => $cashierRole->id,
                        'permission_id' => $perm->id,
                    ]);
                }
            }
        }

        // Stats permissions
        if ($statsRole) {
            $statsSlugs = [
                'menu_read',
                'quota-dashboard_read',
                'quota-partners_read',
                'quota-items_read',
                'quota-payments_read',
                'quota-statistics_read',
                'quota-config_read',
            ];

            DB::table('role_permission')->where('role_id', $statsRole->id)->delete();
            foreach ($statsSlugs as $slug) {
                $perm = DB::table('permissions')->where('slug', $slug)->first();
                if ($perm) {
                    DB::table('role_permission')->insert([
                        'role_id' => $statsRole->id,
                        'permission_id' => $perm->id,
                    ]);
                }
            }
        }

        // Partner permissions (only portal)
        if ($partnerRole) {
            $partnerSlugs = [
                'quota-portal_read',
                'quota-items_read',
            ];

            DB::table('role_permission')->where('role_id', $partnerRole->id)->delete();
            foreach ($partnerSlugs as $slug) {
                $perm = DB::table('permissions')->where('slug', $slug)->first();
                if ($perm) {
                    DB::table('role_permission')->insert([
                        'role_id' => $partnerRole->id,
                        'permission_id' => $perm->id,
                    ]);
                }
            }
        }

        // Limited collector permissions (bañero)
        if ($limitedCollectorRole) {
            $limitedCollectorSlugs = [
                'menu_read',
                'quota-dashboard_read',
                'quota-items_read',
                'quota-items_pay',
                'quota-daily_read',
                'quota-daily_create',
            ];

            DB::table('role_permission')->where('role_id', $limitedCollectorRole->id)->delete();
            foreach ($limitedCollectorSlugs as $slug) {
                $perm = DB::table('permissions')->where('slug', $slug)->first();
                if ($perm) {
                    DB::table('role_permission')->insert([
                        'role_id' => $limitedCollectorRole->id,
                        'permission_id' => $perm->id,
                    ]);
                }
            }
        }

        if ($this->command) {
            $this->command->info('QuotaAdmin role permissions seeded');
        }
    }

    protected function seedModules(): void
    {
        DB::table('modules')->where('package', 'quota_admin')->delete();

        $modules = [
            ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'description' => 'Menú principal', 'is_special' => true, 'order' => 0, 'package' => null],
            ['name' => 'Dashboard', 'route' => 'quota-dashboard', 'icon' => 'bi-speedometer2', 'description' => 'Dashboard de cuotas', 'is_special' => true, 'order' => 0, 'package' => 'quota_admin'],
            ['name' => 'Socios', 'route' => 'quota-partners', 'icon' => 'bi-people', 'description' => 'Gestión de socios', 'is_special' => false, 'order' => 1, 'package' => 'quota_admin'],
            ['name' => 'Planes', 'route' => 'quota-plans', 'icon' => 'bi-calendar3', 'description' => 'Planes de cuotas', 'is_special' => false, 'order' => 2, 'package' => 'quota_admin'],
            ['name' => 'Cuotas', 'route' => 'quota-items', 'icon' => 'bi-credit-card', 'description' => 'Gestión de cuotas', 'is_special' => false, 'order' => 3, 'package' => 'quota_admin'],
            ['name' => 'Cobro Diario', 'route' => 'quota-daily', 'icon' => 'bi-calendar-day', 'description' => 'Cobro diario a no socios', 'is_special' => false, 'order' => 4, 'package' => 'quota_admin'],
            ['name' => 'Pagos', 'route' => 'quota-payments', 'icon' => 'bi-cash-coin', 'description' => 'Historial de pagos', 'is_special' => false, 'order' => 5, 'package' => 'quota_admin'],
            ['name' => 'Configuración', 'route' => 'quota-config', 'icon' => 'bi-sliders', 'description' => 'Configuración del módulo', 'is_special' => false, 'order' => 6, 'package' => 'quota_admin'],
            ['name' => 'Estadísticas', 'route' => 'quota-statistics', 'icon' => 'bi-bar-chart', 'description' => 'Estadísticas y reportes', 'is_special' => false, 'order' => 7, 'package' => 'quota_admin'],
            ['name' => 'Usuarios', 'route' => 'quota-users', 'icon' => 'bi-person-badge', 'description' => 'Gestión de usuarios del sistema', 'is_special' => false, 'order' => 8, 'package' => 'quota_admin'],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['route' => $module['route']],
                array_merge($module, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        if ($this->command) {
            $this->command->info('QuotaAdmin modules seeded');
        }
    }

    protected function seedAdminUser(): void
    {
        $existingAdmin = DB::table('users')->where('username', 'admin')->first();

        if ($existingAdmin) {
            if ($this->command) {
                $this->command->info('Admin user already exists.');
            }
            return;
        }

        $adminRole = DB::table('roles')->where('name', 'admin')->first();

        if (!$adminRole) {
            if ($this->command) {
                $this->command->warn('Admin role not found. Please run RoleSeeder first.');
            }
            return;
        }

        $userId = DB::table('users')->insertGetId([
            'name' => 'Administrador',
            'username' => 'admin',
            'password' => Hash::make('$0deJulio'),
            'role_id' => $adminRole->id,
            'enable' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($this->command) {
            $this->command->info('Admin user created (username: admin, password: $0deJulio)');
        }
    }
}
