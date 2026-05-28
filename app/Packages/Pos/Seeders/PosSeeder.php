<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            StatusOrderSeeder::class,
            ConfigSeeder::class,
            OrderSeeder::class,
        ]);

        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->seedModules();
        $this->seedAdminUser();

        if ($this->command) {
            $this->command->info('POS package seeded successfully!');
        }
    }

    protected function seedPermissions(): void
    {
        $permissions = [
            ['name' => 'Ver Menu', 'slug' => 'menu_read', 'module' => 'menu', 'action' => 'read'],
            ['name' => 'Ver Caja', 'slug' => 'pos-caja_read', 'module' => 'pos-caja', 'action' => 'read'],
            ['name' => 'Ver Categorías', 'slug' => 'pos-categories_read', 'module' => 'pos-categories', 'action' => 'read'],
            ['name' => 'Crear Categoría', 'slug' => 'pos-categories_create', 'module' => 'pos-categories', 'action' => 'create'],
            ['name' => 'Editar Categoría', 'slug' => 'pos-categories_update', 'module' => 'pos-categories', 'action' => 'update'],
            ['name' => 'Eliminar Categoría', 'slug' => 'pos-categories_delete', 'module' => 'pos-categories', 'action' => 'delete'],
            ['name' => 'Ver Productos', 'slug' => 'pos-products_read', 'module' => 'pos-products', 'action' => 'read'],
            ['name' => 'Crear Producto', 'slug' => 'pos-products_create', 'module' => 'pos-products', 'action' => 'create'],
            ['name' => 'Editar Producto', 'slug' => 'pos-products_update', 'module' => 'pos-products', 'action' => 'update'],
            ['name' => 'Eliminar Producto', 'slug' => 'pos-products_delete', 'module' => 'pos-products', 'action' => 'delete'],
            ['name' => 'Ver Órdenes', 'slug' => 'pos-orders_read', 'module' => 'pos-orders', 'action' => 'read'],
            ['name' => 'Crear Orden', 'slug' => 'pos-orders_create', 'module' => 'pos-orders', 'action' => 'create'],
            ['name' => 'Ver Usuarios', 'slug' => 'pos-users_read', 'module' => 'pos-users', 'action' => 'read'],
            ['name' => 'Crear Usuario', 'slug' => 'pos-users_create', 'module' => 'pos-users', 'action' => 'create'],
            ['name' => 'Editar Usuario', 'slug' => 'pos-users_update', 'module' => 'pos-users', 'action' => 'update'],
            ['name' => 'Eliminar Usuario', 'slug' => 'pos-users_delete', 'module' => 'pos-users', 'action' => 'delete'],
            ['name' => 'Ver Configuración', 'slug' => 'pos-config_read', 'module' => 'pos-config', 'action' => 'read'],
            ['name' => 'Editar Configuración', 'slug' => 'pos-config_update', 'module' => 'pos-config', 'action' => 'update'],
            ['name' => 'Ver Estadísticas', 'slug' => 'pos-statistics_read', 'module' => 'pos-statistics', 'action' => 'read'],
            ['name' => 'Exportar Estadísticas', 'slug' => 'pos-statistics_export', 'module' => 'pos-statistics', 'action' => 'export'],
            ['name' => 'Ver Log', 'slug' => 'pos-log_read', 'module' => 'pos-log', 'action' => 'read'],
            ['name' => 'Ver QR', 'slug' => 'pos-qr_read', 'module' => 'pos-qr', 'action' => 'read'],
            ['name' => 'Ver Documentación', 'slug' => 'pos-documentation_read', 'module' => 'pos-documentation', 'action' => 'read'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $permission['slug']],
                array_merge($permission, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        if ($this->command) {
            $this->command->info('POS Permissions seeded');
        }
    }

    protected function seedRolePermissions(): void
    {
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        $cashierRole = DB::table('roles')->where('name', 'cashier')->first();
        
        if (!$adminRole) {
            if ($this->command) {
                $this->command->warn('Admin role not found. Cannot seed role permissions.');
            }
            return;
        }

        $allPermissions = DB::table('permissions')->get();
        
        // Give all permissions to admin
        foreach ($allPermissions as $permission) {
            DB::table('role_permission')->updateOrInsert(
                ['role_id' => $adminRole->id, 'permission_id' => $permission->id],
                ['role_id' => $adminRole->id, 'permission_id' => $permission->id]
            );
        }
        
        // Give basic permissions to cashier (cashier can access caja and create orders)
        if ($cashierRole) {
            // Clear existing cashier permissions
            DB::table('role_permission')->where('role_id', $cashierRole->id)->delete();
            
            $cashierPermissions = [
                'menu_read',
                'pos-caja_read',
                'pos-orders_read',
                'pos-orders_create',
                'pos-qr_read',
                'pos-dashboard_read'
            ];
            
            foreach ($cashierPermissions as $slug) {
                $permission = DB::table('permissions')->where('slug', $slug)->first();
                if ($permission) {
                    DB::table('role_permission')->insert([
                        'role_id' => $cashierRole->id, 
                        'permission_id' => $permission->id
                    ]);
                }
            }
        }
        
        // Stats role: only menu, dashboard, and statistics
        $statsRole = DB::table('roles')->where('name', 'stats')->first();
        if ($statsRole) {
            DB::table('role_permission')->where('role_id', $statsRole->id)->delete();

            $statsPermissions = [
                'menu_read',
                'pos-dashboard_read',
                'pos-statistics_read',
            ];

            foreach ($statsPermissions as $slug) {
                $permission = DB::table('permissions')->where('slug', $slug)->first();
                if ($permission) {
                    DB::table('role_permission')->insert([
                        'role_id' => $statsRole->id,
                        'permission_id' => $permission->id
                    ]);
                }
            }
        }

        if ($this->command) {
            $this->command->info('POS Role permissions seeded for admin, cashier, stats');
        }
    }

    protected function seedModules(): void
    {
        \Log::info('[PosSeeder] seedModules: INICIO');
        
        // Delete all POS modules first (only those with package='pos')
        DB::table('modules')->where('package', 'pos')->delete();
        // Don't delete menu - it's a core module
        
        $modules = [
            [
                'name' => 'Menu',
                'route' => 'menu',
                'icon' => 'bi-list',
                'description' => 'Menú principal',
                'is_special' => true,
                'order' => 0,
                'package' => null
            ],
            [
                'name' => 'Dashboard',
                'route' => 'pos-dashboard',
                'icon' => 'bi-speedometer2',
                'description' => 'Dashboard de ventas',
                'is_special' => true,
                'order' => 0,
                'package' => 'pos'
            ],
            [
                'name' => 'Caja',
                'route' => 'pos-caja',
                'icon' => 'bi-cart3',
                'description' => 'Punto de venta',
                'is_special' => false,
                'order' => 1,
                'package' => 'pos'
            ],
            [
                'name' => 'Categorías',
                'route' => 'pos-categories',
                'icon' => 'bi-tags',
                'description' => 'Gestión de categorías',
                'is_special' => false,
                'order' => 2,
                'package' => 'pos'
            ],
            [
                'name' => 'Productos',
                'route' => 'pos-products',
                'icon' => 'bi-box-seam',
                'description' => 'Gestión de productos',
                'is_special' => false,
                'order' => 3,
                'package' => 'pos'
            ],
            [
                'name' => 'Órdenes',
                'route' => 'pos-orders',
                'icon' => 'bi-receipt',
                'description' => 'Ver órdenes',
                'is_special' => false,
                'order' => 4,
                'package' => 'pos'
            ],
            [
                'name' => 'Usuarios',
                'route' => 'pos-users',
                'icon' => 'bi-people',
                'description' => 'Gestión de usuarios',
                'is_special' => false,
                'order' => 5,
                'package' => 'pos'
            ],
            [
                'name' => 'Configuración',
                'route' => 'pos-config',
                'icon' => 'bi-sliders',
                'description' => 'Configuración del sistema',
                'is_special' => false,
                'order' => 6,
                'package' => 'pos'
            ],
            [
                'name' => 'QR',
                'route' => 'pos-qr',
                'icon' => 'bi-qr-code',
                'description' => 'Ver pedido en dispositivo externo',
                'is_special' => false,
                'order' => 7,
                'package' => 'pos'
            ],
            [
                'name' => 'Estadísticas',
                'route' => 'pos-statistics',
                'icon' => 'bi-bar-chart',
                'description' => 'Estadísticas y reportes del POS',
                'is_special' => false,
                'order' => 8,
                'package' => 'pos'
            ],
            [
                'name' => 'Log',
                'route' => 'pos-log',
                'icon' => 'bi-journal-text',
                'description' => 'Registro de actividad del POS',
                'is_special' => false,
                'order' => 9,
                'package' => 'pos'
            ],
            [
                'name' => 'Documentación',
                'route' => 'pos-documentation',
                'icon' => 'bi-book',
                'description' => 'Documentación del sistema',
                'is_special' => false,
                'order' => 10,
                'package' => 'pos'
            ],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['route' => $module['route']],
                array_merge($module, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        
        $count = DB::table('modules')->count();
        \Log::info('[PosSeeder] seedModules:插入 ' . count($modules) . ' módulos. Total en tabla: ' . $count);
        
        if ($this->command) {
            $this->command->info('POS Modules seeded');
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
            'printer_ip' => null,
            'printer_port' => 9100,
            'printer_type' => 'raw',
            'printer_width' => 80,
            'enable_print' => false,
            'mercadopago_qr_enabled' => false,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        if ($this->command) {
            $this->command->info('Admin user created with ID: ' . $userId . ' (username: admin, password: $0deJulio)');
        }
    }
}
