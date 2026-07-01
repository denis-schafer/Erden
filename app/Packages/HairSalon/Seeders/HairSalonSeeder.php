<?php

namespace App\Packages\HairSalon\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HairSalonSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            \App\Packages\HairSalon\Seeders\RoleSeeder::class,
            \App\Packages\HairSalon\Seeders\PermissionSeeder::class,
            \App\Packages\HairSalon\Seeders\ConfigSeeder::class,
        ]);

        $this->seedRolePermissions();
        $this->seedModules();
        $this->seedAdminUser();

        if ($this->command) {
            $this->command->info('HairSalon package seeded successfully!');
        }
    }

    protected function seedRolePermissions(): void
    {
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        $operatorRole = DB::table('roles')->where('name', 'operator')->first();

        if (!$adminRole) return;

        $allPermissions = DB::table('permissions')->get();

        foreach ($allPermissions as $perm) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $adminRole->id, 'permission_id' => $perm->id],
                ['role_id' => $adminRole->id, 'permission_id' => $perm->id]
            );
        }

        if ($operatorRole) {
            $operatorSlugs = [
                'menu_read',
                'hairsalon-dashboard_read',
                'hairsalon-clients_read',
                'hairsalon-clients_create',
                'hairsalon-services_read',
                'hairsalon-cashier_read',
                'hairsalon-cashier_create',
                'hairsalon-products_read',
                'hairsalon-appointments_read',
                'hairsalon-appointments_create',
                'hairsalon-appointments_update',
            ];

            foreach ($operatorSlugs as $slug) {
                $perm = DB::table('permissions')->where('slug', $slug)->first();
                if ($perm) {
                    DB::table('role_permission')->updateOrInsert(
                        ['role_id' => $operatorRole->id, 'permission_id' => $perm->id],
                        ['role_id' => $operatorRole->id, 'permission_id' => $perm->id]
                    );
                }
            }
        }

        if ($this->command) {
            $this->command->info('HairSalon role permissions seeded');
        }
    }

    protected function seedModules(): void
    {
        $modules = [
            ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'description' => 'Menú principal', 'is_special' => true, 'order' => 0, 'package' => null],
            ['name' => 'Dashboard', 'route' => 'hairsalon-dashboard', 'icon' => 'bi-speedometer2', 'description' => 'Dashboard de peluquería', 'is_special' => true, 'order' => 0, 'package' => 'hairsalon'],
            ['name' => 'Clientes', 'route' => 'hairsalon-clients', 'icon' => 'bi-people', 'description' => 'Gestión de clientes', 'is_special' => false, 'order' => 1, 'package' => 'hairsalon'],
            ['name' => 'Servicios', 'route' => 'hairsalon-services', 'icon' => 'bi-scissors', 'description' => 'Servicios ofrecidos', 'is_special' => false, 'order' => 2, 'package' => 'hairsalon'],
            ['name' => 'Caja', 'route' => 'hairsalon-cashier', 'icon' => 'bi-cart3', 'description' => 'Cobro de trabajos', 'is_special' => false, 'order' => 3, 'package' => 'hairsalon'],
            ['name' => 'Finanzas', 'route' => 'hairsalon-finances', 'icon' => 'bi-cash-stack', 'description' => 'Movimientos de caja', 'is_special' => false, 'order' => 4, 'package' => 'hairsalon'],
            ['name' => 'Productos', 'route' => 'hairsalon-products', 'icon' => 'bi-box-seam', 'description' => 'Stock de productos', 'is_special' => false, 'order' => 5, 'package' => 'hairsalon'],
            ['name' => 'Usuarios', 'route' => 'hairsalon-users', 'icon' => 'bi-person-badge', 'description' => 'Usuarios del sistema', 'is_special' => false, 'order' => 6, 'package' => 'hairsalon'],
            ['name' => 'Estadísticas', 'route' => 'hairsalon-statistics', 'icon' => 'bi-bar-chart', 'description' => 'Estadísticas y reportes', 'is_special' => false, 'order' => 7, 'package' => 'hairsalon'],
            ['name' => 'Log', 'route' => 'hairsalon-log', 'icon' => 'bi-journal-text', 'description' => 'Registro de actividad', 'is_special' => false, 'order' => 8, 'package' => 'hairsalon'],
            ['name' => 'Configuración', 'route' => 'hairsalon-config', 'icon' => 'bi-sliders', 'description' => 'Configuración del sistema', 'is_special' => false, 'order' => 9, 'package' => 'hairsalon'],
            ['name' => 'Turnos', 'route' => 'hairsalon-appointments', 'icon' => 'bi-calendar-event', 'description' => 'Sistema de turnos', 'is_special' => false, 'order' => 10, 'package' => 'hairsalon'],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['route' => $module['route']],
                array_merge($module, ['created_at' => now(), 'updated_at' => now()])
            );
        }

        if ($this->command) {
            $this->command->info('HairSalon modules seeded');
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
                $this->command->warn('Admin role not found.');
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
