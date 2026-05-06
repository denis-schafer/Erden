<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with('status')->orderBy('id', 'desc')->get();
        
        return response()->json($companies);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $lastCompany = Company::orderBy('id', 'desc')->first();
        $nextDb = $lastCompany ? ($lastCompany->db + 1) : 1;

        $company = Company::create([
            'db' => (string) $nextDb,
            'name' => $request->name,
            'status_id' => 1
        ]);

        // Create database and basic tables
        try {
            $pdo = DB::connection('mysql_parent')->getPDO();
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$nextDb}`");
            
            // Switch to child database and create basic tables
            config(['database.connections.mysql.database' => $nextDb]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            // Create basic tables for login and core modules
            $this->createBasicTables();
            
            // Reconnect to parent
            config(['database.connections.mysql.database' => 'erden']);
            DB::purge('mysql');
            DB::reconnect('mysql');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Company created but database setup failed: ' . $e->getMessage()
            ], 500);
        }

        return response()->json($company, 201);
    }
    
    protected function createBasicTables(): void
    {
        // Jobs table
        DB::statement("CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue VARCHAR(255) NOT NULL,
            payload LONGTEXT NOT NULL,
            attempts TINYINT UNSIGNED NOT NULL,
            reserved_at INT UNSIGNED NULL,
            available_at INT UNSIGNED NOT NULL,
            created_at INT UNSIGNED NOT NULL
        )");
        
        // Failed jobs table
        DB::statement("CREATE TABLE IF NOT EXISTS failed_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            uuid VARCHAR(255) NULL,
            connection TEXT NOT NULL,
            queue TEXT NOT NULL,
            payload LONGTEXT NOT NULL,
            exception LONGTEXT NOT NULL,
            failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Status table
        DB::statement("CREATE TABLE IF NOT EXISTS statuses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )");
        
        // Roles table
        DB::statement("CREATE TABLE IF NOT EXISTS roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )");
        
        // Permissions table
        DB::statement("CREATE TABLE IF NOT EXISTS permissions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            module VARCHAR(255) DEFAULT NULL,
            action VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )");
        
        // Users table
        DB::statement("CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            username VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            password VARCHAR(255) NOT NULL,
            role_id BIGINT UNSIGNED NULL,
            status_id BIGINT UNSIGNED DEFAULT 1,
            enable TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL,
            FOREIGN KEY (status_id) REFERENCES statuses(id) ON DELETE SET NULL
        )");
        
        // Modules table
        DB::statement("CREATE TABLE IF NOT EXISTS modules (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            route VARCHAR(255) NOT NULL,
            icon VARCHAR(255) DEFAULT NULL,
            description TEXT NULL,
            is_special TINYINT(1) DEFAULT 0,
            parent_id BIGINT UNSIGNED NULL,
            `order` INT DEFAULT 0,
            package VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (parent_id) REFERENCES modules(id) ON DELETE SET NULL
        )");
        
        // Role permission table
        DB::statement("CREATE TABLE IF NOT EXISTS role_permission (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_id BIGINT UNSIGNED NOT NULL,
            permission_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
            UNIQUE KEY role_permission_unique (role_id, permission_id)
        )");
        
        // Seed basic data
        $this->seedBasicData();
    }
    
    protected function seedBasicData(): void
    {
        // Insert status
        DB::table('statuses')->updateOrInsert(['id' => 1], ['name' => 'Activo', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('statuses')->updateOrInsert(['id' => 2], ['name' => 'Inactivo', 'created_at' => now(), 'updated_at' => now()]);
        
        // Insert roles
        DB::table('roles')->updateOrInsert(['id' => 1], ['name' => 'admin', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('roles')->updateOrInsert(['id' => 2], ['name' => 'operator', 'created_at' => now(), 'updated_at' => now()]);
        
        // Insert basic modules (Dashboard and Menu)
        DB::table('modules')->updateOrInsert(
            ['route' => 'dashboard'],
            ['name' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-speedometer2', 'is_special' => 1, 'order' => 1, 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('modules')->updateOrInsert(
            ['route' => 'menu'],
            ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'is_special' => 1, 'order' => 2, 'created_at' => now(), 'updated_at' => now()]
        );
        
        // Insert basic permissions
        $permissions = [
            ['name' => 'Ver Dashboard', 'slug' => 'dashboard_read', 'module' => 'dashboard', 'action' => 'read'],
            ['name' => 'Ver Menu', 'slug' => 'menu_read', 'module' => 'menu', 'action' => 'read'],
            ['name' => 'Ver Módulos', 'slug' => 'admin-modules_read', 'module' => 'admin-modules', 'action' => 'read'],
            ['name' => 'Ver Compañías', 'slug' => 'admin-companies_read', 'module' => 'admin-companies', 'action' => 'read'],
            ['name' => 'Ver Usuarios', 'slug' => 'users_read', 'module' => 'users', 'action' => 'read'],
            ['name' => 'Ver Roles', 'slug' => 'roles_read', 'module' => 'roles', 'action' => 'read'],
        ];
        
        foreach ($permissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => $perm['slug']],
                array_merge($perm, ['created_at' => now(), 'updated_at' => now()])
            );
        }
        
        // Assign all permissions to admin role
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            $perms = DB::table('permissions')->get();
            foreach ($perms as $p) {
                DB::table('role_permission')->updateOrInsert(
                    ['role_id' => $adminRole->id, 'permission_id' => $p->id],
                    ['role_id' => $adminRole->id, 'permission_id' => $p->id, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
        
        // Assign basic permissions to operator role
        $operatorRole = DB::table('roles')->where('name', 'operator')->first();
        if ($operatorRole) {
            $basicPerms = ['dashboard_read', 'menu_read'];
            foreach ($basicPerms as $slug) {
                $p = DB::table('permissions')->where('slug', $slug)->first();
                if ($p) {
                    DB::table('role_permission')->updateOrInsert(
                        ['role_id' => $operatorRole->id, 'permission_id' => $p->id],
                        ['role_id' => $operatorRole->id, 'permission_id' => $p->id, 'created_at' => now(), 'updated_at' => now()]
                    );
                }
            }
        }
        
        // Create admin user
        $adminRole = DB::table('roles')->where('name', 'admin')->first();
        if ($adminRole) {
            DB::table('users')->updateOrInsert(
                ['username' => 'admin'],
                [
                    'name' => 'Administrador',
                    'username' => 'admin',
                    'password' => Hash::make('$0deJulio'),
                    'role_id' => $adminRole->id,
                    'status_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }
        
        // Create configs table
        $this->createConfigsTable();
    }
    
    protected function createConfigsTable(): void
    {
        // Configs table
        DB::statement("CREATE TABLE IF NOT EXISTS configs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            value TEXT NULL,
            type VARCHAR(50) DEFAULT 'text',
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )");
        
        // Seed default configs
        $configs = [
            ['name' => 'business_name', 'value' => '', 'type' => 'text'],
            ['name' => 'business_address', 'value' => '', 'type' => 'text'],
            ['name' => 'business_phone', 'value' => '', 'type' => 'text'],
            ['name' => 'business_nit', 'value' => '', 'type' => 'text'],
            ['name' => 'printer_name', 'value' => '', 'type' => 'text'],
            ['name' => 'printer_ip', 'value' => '', 'type' => 'text'],
            ['name' => 'printer_port', 'value' => '9100', 'type' => 'text'],
            ['name' => 'printer_width', 'value' => '80mm', 'type' => 'selector'],
            ['name' => 'ticket_title', 'value' => 'MI NEGOCIO', 'type' => 'text'],
            ['name' => 'enable_print', 'value' => 'false', 'type' => 'boolean'],
            ['name' => 'mercadopago_enabled', 'value' => 'false', 'type' => 'boolean'],
            ['name' => 'mercadopago_qr_enabled', 'value' => 'false', 'type' => 'boolean'],
        ];
        
        foreach ($configs as $config) {
            DB::table('configs')->updateOrInsert(
                ['name' => $config['name']],
                array_merge($config, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }

    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        
        $request->validate([
            'status_id' => 'required|exists:statuses,id'
        ]);

        $company->update([
            'status_id' => $request->status_id
        ]);

        return response()->json($company);
    }

    public function destroy($id)
    {
        $company = Company::findOrFail($id);

        try {
            $pdo = DB::connection('mysql_parent')->getPDO();
            $pdo->exec("DROP DATABASE IF EXISTS `{$company->db}`");
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to drop database: ' . $e->getMessage()
            ], 500);
        }

        $company->delete();

        return response()->json(['message' => 'Company deleted successfully']);
    }

    public function getStatuses()
    {
        $statuses = DB::connection('mysql_parent')->table('statuses')->get();
        return response()->json($statuses);
    }
}