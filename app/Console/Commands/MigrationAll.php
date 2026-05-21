<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MigrationAll extends Command
{
    protected $signature = 'migration_all {--migrate : Only run migrations, skip seeders} {--company= : Run only for specific company DB}';
    protected $description = 'Run migrations and seeders on parent database and all child databases';

    public function handle(): int
    {
        $this->info('=== Iniciando Migration All ===');
        $this->line('');

        $runMigrations = !$this->option('migrate');
        $specificCompany = $this->option('company');

        // Step 0: Verificar e instalar packages NPM y Composer
        $this->verifyAndInstallNpmPackages();
        $this->line('');

        // Step 0b: Iniciar servidor WebSocket
        $this->startWebSocketServer();
        $this->line('');

        // Step 1: Run migrations and seeders on parent database (erden)
        $this->info('--- Base de datos padre (erden) ---');
        $this->runParentDatabase($runMigrations);
        $this->line('');

        // If specific company is requested, only process that one
        if ($specificCompany) {
            $company = $this->getCompanyByDb($specificCompany);
            if ($company) {
                $this->processCompany($company, $runMigrations);
            } else {
                $this->error("Compañía con DB {$specificCompany} no encontrada.");
            }
        } else {
            // Check if companies exist in parent database
            $companies = $this->getCompanies();
            
            if (empty($companies)) {
                $this->warn('No se encontraron compañías en la base de datos.');
                $this->info('Las bases de datos hijos se crearán cuando se agreguen compañías via el módulo Admin Compañías.');
                $this->info('=== Migration All Completado ===');
                return 0;
            }

            $this->info('Se encontraron ' . count($companies) . ' compañía(s) en la base de datos.');
            $this->line('');
            
            // Process all companies
            foreach ($companies as $company) {
                $this->processCompany($company, $runMigrations);
            }
        }

        $this->info('=== Migration All Completado ===');
        return 0;
    }

    protected function getCompanyByDb(string $db): ?object
    {
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $company = DB::table('companies')
            ->where('db', $db)
            ->first();
            
        return $company;
    }

    protected function runParentDatabase(bool $runSeeders): void
    {
        $this->info('Running migrations on erden...');
        
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Only run parent-specific migrations
        $parentMigrations = [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/0001_01_01_000001_create_cache_table.php',
            'database/migrations/0001_01_01_000002_create_jobs_table.php',
            'database/migrations/2026_04_03_000001_create_statuses_table.php',
            'database/migrations/2026_04_03_000002_create_modules_table.php',
            'database/migrations/2026_04_03_000003_create_companies_table.php',
            'database/migrations/2026_04_03_000004_create_company_modules_table.php',
            'database/migrations/2026_04_03_000005_create_global_roles_table.php',
            'database/migrations/2026_04_03_000006_create_global_users_table.php',
            // New migrations for parent DB
            'database/migrations/2026_04_03_000007_create_roles_child_table.php',
            'database/migrations/2026_04_03_000008_create_permissions_table.php',
            'database/migrations/2026_04_03_000009_create_role_permission_table.php',
            'database/migrations/2026_04_03_000010_make_users_email_nullable.php',
            'database/migrations/2026_04_08_000001_create_pos_module.php',
            // Global Config module (2026-04-16)
            'database/migrations/2026_04_16_000001_create_config_module.php',
            'database/migrations/2026_04_16_000002_create_global_configs_table.php',
            'database/migrations/2026_04_16_000003_rename_mp_configs.php',
            'database/migrations/2026_05_17_000001_add_print_agent_key_to_companies_table.php',
            'database/migrations/2026_05_18_122141_create_print_jobs_table_on_parent.php',
        ];

        foreach ($parentMigrations as $path) {
            $migrationName = basename($path, '.php');
            try {
                $this->call('migrate', ['--force' => true, '--path' => $path, '--database' => 'mysql_parent']);
                $this->info("Migration {$migrationName} ejecutada.");
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    $this->info("Migration {$migrationName} ya aplicada.");
                } else {
                    $this->warn("Error en migration {$migrationName}: " . $e->getMessage());
                }
            }
        }

        // Verify and add username column to users table in parent DB
        $this->verifyParentUsersTable();

        if ($runSeeders) {
            $this->info('Ejecutando seeders en erden...');
            $this->call('db:seed', ['--class' => 'Database\Seeders\StatusSeeder', '--force' => true, '--database' => 'mysql_parent']);
            
            // Run ModuleSeeder directly - ensure it connects to erden
            $this->runModuleSeeder();
            
            $this->call('db:seed', ['--class' => 'Database\Seeders\CompanySeeder', '--force' => true, '--database' => 'mysql_parent']);
            $this->call('db:seed', ['--class' => 'Database\Seeders\CompanyModuleSeeder', '--force' => true, '--database' => 'mysql_parent']);
            $this->call('db:seed', ['--class' => 'Database\Seeders\GlobalRoleSeeder', '--force' => true, '--database' => 'mysql_parent']);
            $this->call('db:seed', ['--class' => 'Database\Seeders\GlobalUserSeeder', '--force' => true, '--database' => 'mysql_parent']);
            $this->call('db:seed', ['--class' => 'Database\Seeders\PermissionParentSeeder', '--force' => true, '--database' => 'mysql_parent']);
            $this->call('db:seed', ['--class' => 'Database\Seeders\RolePermissionParentSeeder', '--force' => true, '--database' => 'mysql_parent']);
            $this->call('db:seed', ['--class' => 'Database\Seeders\GlobalConfigSeeder', '--force' => true, '--database' => 'mysql_parent']);
        }

        $this->info('Base de datos padre completada.');
    }

    protected function runModuleSeeder(): void
    {
        $this->info('Ejecutando ModuleSeeder...');
        
        // Ensure we're connected to erden
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');
        
        $modules = [
            ['name' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'bi-speedometer2', 'is_special' => 1, 'parent_id' => null, 'order' => 1],
            ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'is_special' => 1, 'parent_id' => null, 'order' => 2],
            ['name' => 'Admin Módulos', 'route' => 'admin-modules', 'icon' => 'bi-gear', 'is_special' => 0, 'parent_id' => null, 'order' => 3],
            ['name' => 'Admin Compañías', 'route' => 'admin-companies', 'icon' => 'bi-building', 'is_special' => 0, 'parent_id' => null, 'order' => 4],
            ['name' => 'Usuarios', 'route' => 'users', 'icon' => 'bi-people', 'is_special' => 0, 'parent_id' => null, 'order' => 5],
            ['name' => 'Roles', 'route' => 'roles', 'icon' => 'bi-shield-lock', 'is_special' => 0, 'parent_id' => null, 'order' => 6],
            ['name' => 'POS', 'route' => 'pos', 'icon' => 'bi-cart3', 'is_special' => 1, 'parent_id' => null, 'order' => 50, 'package' => 'pos'],
        ];

        $this->info('Starting module insertion loop...');
        
        foreach ($modules as $module) {
            $this->info("Processing module: {$module['name']} ({$module['route']})");
            
            $exists = DB::connection('mysql')
                ->table('modules')
                ->where('route', $module['route'])
                ->exists();
            
            $this->info("  - Existe: " . ($exists ? 'SÍ' : 'NO'));
            
            if (!$exists) {
                try {
                    DB::connection('mysql')->table('modules')->insert($module);
                    $this->info("  - INSERTADO: {$module['name']}");
                } catch (\Exception $e) {
                    $this->error("  - ERROR al insertar: " . $e->getMessage());
                }
            } else {
                $this->info("  - Ya existe, omitiendo");
            }
        }
        
        // Show final count
        $count = DB::connection('mysql')->table('modules')->count();
        $this->info("Total de módulos en la base de datos: {$count}");
        
        $this->info('ModuleSeeder completado.');
    }

    protected function verifyParentUsersTable(): void
    {
        $this->info('Verificando tabla de usuarios en base de datos padre...');
        
        try {
            if (!Schema::connection('mysql_parent')->hasTable('users')) {
                $this->warn('La tabla users no existe en la base de datos padre!');
                return;
            }
            
            $columns = DB::connection('mysql_parent')->getSchemaBuilder()->getColumnListing('users');
            
            if (!in_array('username', $columns)) {
                $this->warn('users.username column NOT found in parent DB! Adding it manually...');
                DB::connection('mysql_parent')->statement("ALTER TABLE users ADD COLUMN username VARCHAR(255) NULL AFTER id");
                DB::connection('mysql_parent')->statement("ALTER TABLE users ADD UNIQUE INDEX users_username_unique (username)");
                $this->info('username column added successfully to parent DB.');
            } else {
                $this->info('users.username column exists in parent DB.');
            }
            
            if (!in_array('role_id', $columns)) {
                $this->warn('users.role_id column NOT found in parent DB! Adding it manually...');
                DB::connection('mysql_parent')->statement("ALTER TABLE users ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER id");
                DB::connection('mysql_parent')->statement("ALTER TABLE users ADD INDEX users_role_id_index (role_id)");
                $this->info('role_id column added successfully to parent DB.');
            } else {
                $this->info('users.role_id column exists in parent DB.');
            }
            
            try {
                DB::connection('mysql_parent')->statement("ALTER TABLE users MODIFY email VARCHAR(255) NULL");
                $this->info('users.email column set to nullable.');
            } catch (\Exception $e) {
                $this->info('users.email column already nullable or error: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->warn('Error verifying parent users table: ' . $e->getMessage());
        }
    }

    protected function getCompanies(): array
    {
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        return DB::table('companies')
            ->get(['id', 'db', 'name'])
            ->toArray();
    }

    protected function processCompany(object $company, bool $runSeeders): void
    {
        $dbName = $company->db; // Use exactly what's in company.db
        $this->info("--- Company: {$company->name} (Database: {$dbName}) ---");

        // Step 1: Create database if not exists
        $this->createDatabaseIfNotExists($dbName);

        // Step 2: Connect to company database
        $this->connectToDatabase($dbName);
        
        // Confirm connection
        $connectedDb = config('database.connections.mysql.database');
        $this->info("Confirmed connected to: {$connectedDb}");

        // Step 3: Run migrations (only child-specific migrations)
        $this->runChildMigrations();

        // Step 4: Run seeders
        if ($runSeeders) {
            $this->runSeeders();
        }

        // Step 5: Generate print_agent_key if missing
        $this->ensurePrintAgentKey($company);

        // Step 6: Reconnect to child database (ensurePrintAgentKey switches to parent)
        $this->connectToDatabase($dbName);

        // Step 7: Ensure printing_mode config exists on child database
        try {
            $exists = DB::table('configs')->where('name', 'printing_mode')->exists();
            if (!$exists) {
                DB::table('configs')->insert([
                    'name' => 'printing_mode',
                    'value' => 'vps',
                    'type' => 'string',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->info('printing_mode config created with default: vps');
            }
        } catch (\Exception $e) {
            $this->warn('Could not ensure printing_mode config: ' . $e->getMessage());
        }

        $this->info("Company {$company->name} completed.");
    }

    protected function connectToDatabase(string $dbName): void
    {
        config(['database.connections.mysql.database' => $dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        
        // Verify connection is working
        try {
            DB::connection()->getPdo();
            $this->info("Connected to database: {$dbName}");
        } catch (\Exception $e) {
            $this->error("Failed to connect to database {$dbName}: " . $e->getMessage());
            throw $e;
        }
        
        // NO longer dropping all tables - preserve existing data
        // $this->dropAllTables();
    }

    protected function dropAllTables(): void
    {
        try {
            $pdo = DB::connection()->getPDO();
            $tables = $pdo->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                $this->info('No tables to drop.');
                return;
            }
            
            $this->info('Dropping ' . count($tables) . ' existing tables...');
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $this->info('All tables dropped successfully.');
        } catch (\Exception $e) {
            $this->warn('Error dropping tables: ' . $e->getMessage());
        }
    }

    protected function runChildMigrations(): void
    {
        $this->info('Running migrations for child database...');

        // Only run child-specific migrations (that should exist in child DBs)
        $childMigrations = [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2026_04_03_100001_create_roles_table.php',
            'database/migrations/2026_04_03_100002_create_permissions_table.php',
            'database/migrations/2026_04_03_100005_add_role_id_to_users_table.php',
            'database/migrations/2026_04_03_100006_modify_users_table.php',
            'database/migrations/2026_04_03_100003_create_role_user_table.php',
            'database/migrations/2026_04_03_100004_create_role_permission_table.php',
            'database/migrations/2026_04_03_100007_create_modules_table.php',
            'database/migrations/2026_04_22_000001_drop_mercadopago_enable_qr_column.php',
            // Note: mp_payment_id and mp_transaction_amount columns are added in createPosTables()
        ];

        // First, check what tables already exist using Schema (more reliable)
        $tablesList = [];
        try {
            $tables = Schema::getTables();
            $tablesList = array_map(fn($t) => $t['name'], $tables);
            $this->info('Existing tables: ' . implode(', ', $tablesList));
        } catch (\Exception $e) {
            $this->warn('Could not get table list: ' . $e->getMessage());
        }

        // Run each migration
        foreach ($childMigrations as $path) {
            $migrationName = basename($path, '.php');
            try {
                $this->call('migrate', ['--force' => true, '--path' => $path]);
                $this->info("Migration {$migrationName} ejecutada.");
            } catch (\Exception $e) {
                // Check if it's "already exists" error - that's OK
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    $this->info("Migration {$migrationName} ya aplicada.");
                } else {
                    $this->warn("Error en migration {$migrationName}: " . $e->getMessage());
                }
            }
        }

        // Verify that username and role_id columns exist
        $this->verifyUsersTableStructure();
        
        // Verify and add MercadoPago columns to orders table
        $this->verifyOrdersTableStructure();
        
        $this->info('Migrations completed.');
    }
    
    protected function verifyOrdersTableStructure(): void
    {
        $this->info('Verifying orders table structure for MercadoPago columns...');
        
        try {
            if (!Schema::hasTable('orders')) {
                $this->warn('Orders table does not exist. Will be created by createPosTables().');
                return;
            }
            
            // Use direct query since orders table already exists
            $columnsQuery = DB::select('SHOW COLUMNS FROM orders');
            $columns = array_column($columnsQuery, 'Field');
            
            $this->info('Current orders columns: ' . implode(', ', $columns));
            
            if (!in_array('mp_payment_id', $columns)) {
                $this->info('Adding mp_payment_id column to orders...');
                DB::statement('ALTER TABLE orders ADD COLUMN mp_payment_id VARCHAR(50) NULL AFTER paid');
                $this->info('mp_payment_id column added successfully.');
            } else {
                $this->info('mp_payment_id column already exists.');
            }
            
            if (!in_array('mp_transaction_amount', $columns)) {
                $this->info('Adding mp_transaction_amount column to orders...');
                DB::statement('ALTER TABLE orders ADD COLUMN mp_transaction_amount DECIMAL(10,2) NULL AFTER mp_payment_id');
                $this->info('mp_transaction_amount column added successfully.');
            } else {
                $this->info('mp_transaction_amount column already exists.');
            }
        } catch (\Exception $e) {
            $this->warn('Error verifying orders table: ' . $e->getMessage());
        }
    }

    protected function verifyUsersTableStructure(): void
    {
        $this->info('Verifying users table structure...');
        
        try {
            if (!Schema::hasTable('users')) {
                $this->warn('Users table does not exist!');
                return;
            }
            
            $columns = DB::getSchemaBuilder()->getColumnListing('users');
            
            if (!in_array('username', $columns)) {
                $this->warn('users.username column NOT found! Adding it manually...');
                DB::statement("ALTER TABLE users ADD COLUMN username VARCHAR(255) NULL AFTER id");
                DB::statement("ALTER TABLE users ADD UNIQUE INDEX users_username_unique (username)");
                $this->info('username column added successfully.');
            } else {
                $this->info('users.username column exists.');
            }
            
            if (!in_array('role_id', $columns)) {
                $this->warn('users.role_id column NOT found! Adding it manually...');
                DB::statement("ALTER TABLE users ADD COLUMN role_id BIGINT UNSIGNED NULL AFTER id");
                DB::statement("ALTER TABLE users ADD INDEX users_role_id_index (role_id)");
                $this->info('role_id column added successfully.');
            } else {
                $this->info('users.role_id column exists.');
            }
        } catch (\Exception $e) {
            $this->warn('Error verifying table structure: ' . $e->getMessage());
        }
    }

    protected function createDatabaseIfNotExists(string $dbName): void
    {
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
        
        if (empty($exists)) {
            $this->info("Creating database: {$dbName}");
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
        } else {
            $this->info("Database {$dbName} already exists.");
        }
    }

    protected function runMigrations(): void
    {
        $this->info('Running migrations...');

        // Get list of tables that already exist
        $existingTables = DB::getSchemaBuilder()->getTables();
        $tables = array_keys($existingTables);

        // Run migrations with exception handling
        try {
            $this->call('migrate', ['--force' => true]);
        } catch (\Exception $e) {
            // If migration fails due to existing table, continue
            // Laravel will skip already-run migrations next time
            $this->warn('Some migrations may have failed: ' . $e->getMessage());
        }

        $this->info('Migrations completed.');
    }

    protected function runSeeders(): void
    {
        $this->info('Running seeders...');

        // Skip ModuleSeeder and RoleSeeder - already handled by POS module assignment
        // Only run permissions and admin user seeders
        
        $this->call('db:seed', ['--class' => 'Database\Seeders\Child\PermissionSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => 'Database\Seeders\Child\RolePermissionSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => 'Database\Seeders\Child\AdminUserSeeder', '--force' => true]);

        // Check if POS module is assigned and run POS seeders
        $this->runPosSeeders();

        $this->info('Seeders completed.');
    }

    protected function runPosSeeders(): void
    {
        // Verificar si el módulo POS está asignado a esta compañía
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Get current company DB name
        $companyDb = config('database.connections.mysql.database');
        
        $company = DB::table('companies')
            ->where('db', $companyDb)
            ->first();
            
        if (!$company) {
            return;
        }
        
        // Check if company has POS module
        $hasPos = DB::table('company_modules')
            ->where('company_id', $company->id)
            ->whereHas('module', function($query) {
                $query->where('route', 'pos');
            })
            ->exists();
            
        if (!$hasPos) {
            $this->info('POS module not assigned to this company. Skipping POS seeders.');
            return;
        }
        
        $this->info('POS module detected. Running POS seeders...');
        
        // Connect back to child database
        config(['database.connections.mysql.database' => $companyDb]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        
        // Create status_orders table if not exists
        $this->createPosTables();
        
        // Run POS seeders - include Package ModuleSeeder for child DB (includes all POS modules)
        // Solo ejecutar ModuleSeeder si no hay módulos POS aún
        $posModuleCount = DB::table('modules')->where('package', 'pos')->count();
        if ($posModuleCount === 0) {
            $this->call('db:seed', ['--class' => 'App\Packages\Pos\Seeders\ModuleSeeder', '--force' => true]);
        } else {
            $this->info('POS modules already exist. Skipping ModuleSeeder.');
        }
        
        $this->call('db:seed', ['--class' => 'Database\Seeders\Child\PosStatusOrderSeeder', '--force' => true]);
        $this->call('db:seed', ['--class' => 'Database\Seeders\Child\PosConfigSeeder', '--force' => true]);
        
        $this->info('POS seeders completed.');
    }

    protected function createPosTables(): void
    {
        $this->info('Creating POS tables...');
        
        $currentDb = config('database.connections.mysql.database');
        $this->info("Connected to database: {$currentDb}");
        
        // Create status_orders table
        if (!Schema::hasTable('status_orders')) {
            Schema::create('status_orders', function ($table) {
                $table->id();
                $table->string('name', 100);
                $table->timestamps();
            });
            $this->info('Table status_orders created.');
        }
        
        // Create configs table
        if (!Schema::hasTable('configs')) {
            Schema::create('configs', function ($table) {
                $table->id();
                $table->string('name', 200);
                $table->string('value', 500)->nullable();
                $table->string('target')->nullable();
                $table->string('type')->nullable();
                $table->timestamps();
            });
            $this->info('Table configs created.');
        }
        
        // Create orders table
        if (!Schema::hasTable('orders')) {
            Schema::create('orders', function ($table) {
                $table->id();
                $table->string('dni', 20)->nullable();
                $table->json('detail')->nullable();
                $table->decimal('total', 10, 2);
                $table->unsignedBigInteger('operator_id');
                $table->unsignedBigInteger('status_id')->default(1);
                $table->boolean('paid')->default(false);
                $table->string('mp_payment_id', 50)->nullable();
                $table->decimal('mp_transaction_amount', 10, 2)->nullable();
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
                
                $table->foreign('operator_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('status_id')->references('id')->on('status_orders')->onDelete('restrict');
            });
            $this->info('Table orders created.');
        } else {
            // Add MercadoPago columns if table already exists but missing columns
            $this->info('Orders table already exists. Checking for MercadoPago columns...');
            try {
                $columnsQuery = DB::select('SHOW COLUMNS FROM orders');
                $columns = array_column($columnsQuery, 'Field');
                
                $this->info('Current columns: ' . implode(', ', $columns));
                
                if (!in_array('mp_payment_id', $columns)) {
                    $this->info('Adding mp_payment_id column...');
                    DB::statement('ALTER TABLE orders ADD COLUMN mp_payment_id VARCHAR(50) NULL AFTER paid');
                    $this->info('Column mp_payment_id added!');
                } else {
                    $this->info('Column mp_payment_id already exists.');
                }
                
                if (!in_array('mp_transaction_amount', $columns)) {
                    $this->info('Adding mp_transaction_amount column...');
                    DB::statement('ALTER TABLE orders ADD COLUMN mp_transaction_amount DECIMAL(10,2) NULL AFTER mp_payment_id');
                    $this->info('Column mp_transaction_amount added!');
                } else {
                    $this->info('Column mp_transaction_amount already exists.');
                }
            } catch (\Exception $e) {
                $this->warn('Could not verify/add columns to orders: ' . $e->getMessage());
            }
        }
        
        // Create categories table
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function ($table) {
                $table->id();
                $table->string('name', 100);
                $table->boolean('default')->default(false);
                $table->integer('order')->default(0);
                $table->boolean('enable')->default(true);
                $table->timestamps();
            });
            $this->info('Table categories created.');
        }
        
        // Create products table
        if (!Schema::hasTable('products')) {
            Schema::create('products', function ($table) {
                $table->id();
                $table->string('name', 100);
                $table->string('short_description', 200)->nullable();
                $table->text('long_description')->nullable();
                $table->decimal('amount', 10, 2);
                $table->unsignedBigInteger('category_id');
                $table->boolean('enable')->default(true);
                $table->integer('order')->default(0);
                $table->timestamps();
                
                $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
});
            $this->info('Table orders created.');
        } else {
            // Orders table exists - add MercadoPago columns
            $this->info('Orders table already exists. Adding MercadoPago columns...');
            
            try {
                // Test connection with a simple query
                DB::select('SELECT 1 as test');
                $this->info('Database connection OK.');
                
                // Try to add columns directly
                DB::statement("ALTER TABLE orders ADD COLUMN mp_payment_id VARCHAR(50) NULL AFTER paid");
                $this->info('Column mp_payment_id added.');
            } catch (\Exception $e) {
                $this->warn('Error adding mp_payment_id: ' . $e->getMessage());
            }
            
            try {
                DB::statement("ALTER TABLE orders ADD COLUMN mp_transaction_amount DECIMAL(10,2) NULL AFTER mp_payment_id");
                $this->info('Column mp_transaction_amount added.');
            } catch (\Exception $e) {
                $this->warn('Error adding mp_transaction_amount: ' . $e->getMessage());
            }
            
            // Verify columns were added
            try {
                $verify = DB::select('SHOW COLUMNS FROM orders WHERE Field IN ("mp_payment_id", "mp_transaction_amount")');
                $this->info('Verification result: ' . count($verify) . ' columns found.');
            } catch (\Exception $e) {
                $this->warn('Verification error: ' . $e->getMessage());
            }
        }
        
        $this->info('POS tables creation completed.');
    }

    protected function verifyAndInstallNpmPackages(): void
    {
        $this->info('=== Verificando packages NPM y Composer ===');
        
        $packageJsonPath = base_path('package.json');
        
        if (!file_exists($packageJsonPath)) {
            $this->warn('package.json no encontrado. Omitiendo verificación de NPM.');
            return;
        }
        
        // Verificar Composer packages
        $composerJsonPath = base_path('composer.json');
        if (file_exists($composerJsonPath)) {
            $composerJson = json_decode(file_get_contents($composerJsonPath), true);
            $require = $composerJson['require'] ?? [];
            
            $requiredComposerPackages = ['laravel/reverb', 'pusher/pusher-php-server'];
            $missingComposerPackages = [];
            
            foreach ($requiredComposerPackages as $package) {
                if (!isset($require[$package])) {
                    $missingComposerPackages[] = $package;
                }
            }
            
            if (!empty($missingComposerPackages)) {
                $this->warn('Packages de Composer faltantes: ' . implode(', ', $missingComposerPackages));
                $this->info('Instalando packages de Composer con composer install...');
                
                exec('cd ' . base_path() . ' && composer install --no-interaction 2>&1', $composerOutput, $composerReturnCode);
                
                if ($composerReturnCode === 0) {
                    $this->info('Composer packages instalados correctamente.');
                } else {
                    $this->warn('Advertencia en composer install: ' . implode("\n", array_slice($composerOutput, 0, 5)));
                }
            } else {
                $this->info('Todos los packages de Composer requeridos ya están instalados.');
            }
        }
        
        // Verificar NPM packages
        $packageJson = json_decode(file_get_contents($packageJsonPath), true);
        $dependencies = $packageJson['dependencies'] ?? [];
        
        $requiredNpmPackages = ['laravel-echo', 'socket.io-client', 'pusher-js', 'chart.js', 'vue-chartjs'];
        $missingNpmPackages = [];
        
        foreach ($requiredNpmPackages as $package) {
            if (!isset($dependencies[$package])) {
                $missingNpmPackages[] = $package;
            }
        }
        
        if (!empty($missingNpmPackages)) {
            $this->warn('Packages NPM faltantes: ' . implode(', ', $missingNpmPackages));
        }
        
        $this->info('Ejecutando npm install para asegurar todos los packages...');
        exec('cd ' . base_path() . ' && npm install --legacy-peer-deps 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            $this->info('Packages NPM instalados correctamente.');
        } else {
            $this->warn('Advertencia en npm install: ' . implode("\n", array_slice($output, 0, 5)));
        }
        
        // Always run npm run build to ensure all assets are properly built
        $this->info('Ejecutando npm run build...');
        exec('cd ' . base_path() . ' && npm run build 2>&1', $buildOutput, $buildReturnCode);
        
        if ($buildReturnCode === 0) {
            $this->info('Build completado exitosamente.');
        } else {
            $this->warn('Advertencia en build: ' . implode("\n", array_slice($buildOutput, 0, 5)));
        }
        
        $this->info('Verificación de packages completada.');
    }

    protected function startWebSocketServer(): void
    {
        $this->info('=== Verificando servidor WebSocket (Laravel Reverb) ===');
        
        // Verificar si Laravel Reverb está instalado
        $reverbConfigPath = base_path('config/reverb.php');
        
        if (!file_exists($reverbConfigPath)) {
            $this->warn('Laravel Reverb no está instalado.');
            
            // Verificar si ya está en composer.lock (instalación automática)
            $composerLockPath = base_path('composer.lock');
            if (file_exists($composerLockPath)) {
                $lockContent = file_get_contents($composerLockPath);
                if (strpos($lockContent, 'laravel/reverb') !== false) {
                    $this->info('Reverb encontrado en composer.lock. Ejecutando composer install...');
                    exec('cd ' . base_path() . ' && composer install --no-interaction 2>&1', $composerOutput, $composerReturnCode);
                    
                    if ($composerReturnCode === 0) {
                        $this->info('Reverb instalado correctamente.');
                    }
                }
            }
            
            // Verificar si se instaló la config
            if (!file_exists($reverbConfigPath)) {
                $this->info('Publicando configuración de Reverb...');
                exec('cd ' . base_path() . ' && php artisan vendor:publish --provider="Laravel\Reverb\ReverbServiceProvider" --force 2>&1', $output2, $returnCode2);
            }
            
            if (!file_exists($reverbConfigPath)) {
                $this->warn('No se pudo configurar Reverb. Los eventos en tiempo real no funcionarán.');
            } else {
                $this->info('Laravel Reverb configurado correctamente.');
            }
        } else {
            $this->info('Laravel Reverb ya está instalado.');
        }
        
        // Iniciar servidor Reverb/Pusher
        $this->info('Iniciando servidor WebSocket...');
        
        // Verificar si el servidor ya está corriendo (puerto 6001 para Pusher)
        $port = env('PUSHER_APP_PORT', 6001);
        exec("netstat -an 2>nul | findstr {$port} 2>nul | findstr LISTENING", $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $this->info("El servidor WebSocket ya está corriendo en el puerto {$port}.");
        } else {
            $this->info("Servidor WebSocket configurado pero no iniciado.");
            $this->info("Para iniciar el servidor WebSocket, ejecuta en una terminal separada:");
            $this->line("  php artisan reverb:start");
            $this->line("  (El servidor escuchará en puerto 8080 para Reverb)");
        }
        
        $this->info('WebSockets configurado correctamente.');
    }

    protected function ensurePrintAgentKey(object $company): void
    {
        $this->info('Verificando print_agent_key...');

        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $companyRecord = DB::table('companies')->where('id', $company->id)->first();

        if (!$companyRecord) {
            $this->warn('Empresa no encontrada en base de datos padre.');
            return;
        }

        if (empty($companyRecord->print_agent_key)) {
            $key = (string) Str::uuid();
            DB::table('companies')->where('id', $company->id)->update(['print_agent_key' => $key]);
            $this->info("print_agent_key generada: {$key}");
        } else {
            $this->info('print_agent_key ya existe.');
        }
    }
}
