<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use App\Models\Company;
use App\Models\Module;
use Illuminate\Database\Schema\Blueprint;

class ModuleInstall extends Command
{
    protected $signature = 'module:install {package : Package name to install} {--company= : Company DB name} {--force : Force installation even if already installed}';
    protected $description = 'Install a module package for a company';

    protected $packages = [
        'pos' => [
            'name' => 'POS (Point of Sale)',
            'modules' => [
                ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'is_special' => 1, 'order' => 0, 'package' => null],
                ['name' => 'Dashboard', 'route' => 'pos-dashboard', 'icon' => 'bi-speedometer2', 'is_special' => 1, 'order' => 0, 'package' => 'pos'],
                ['name' => 'Caja', 'route' => 'pos-caja', 'icon' => 'bi-cart3', 'is_special' => 0, 'order' => 1, 'package' => 'pos'],
                ['name' => 'Categorías', 'route' => 'pos-categories', 'icon' => 'bi-tags', 'is_special' => 0, 'order' => 2, 'package' => 'pos'],
                ['name' => 'Productos', 'route' => 'pos-products', 'icon' => 'bi-box-seam', 'is_special' => 0, 'order' => 3, 'package' => 'pos'],
                ['name' => 'Órdenes', 'route' => 'pos-orders', 'icon' => 'bi-receipt', 'is_special' => 0, 'order' => 4, 'package' => 'pos'],
                ['name' => 'Usuarios', 'route' => 'pos-users', 'icon' => 'bi-people', 'is_special' => 0, 'order' => 5, 'package' => 'pos'],
                ['name' => 'Configuración', 'route' => 'pos-config', 'icon' => 'bi-sliders', 'is_special' => 0, 'order' => 6, 'package' => 'pos'],
            ],
            'migrations_path' => 'app/Packages/Pos/Migrations',
            'seeder_class' => 'App\Packages\Pos\Seeders\PosSeeder',
        ],
        'quota_admin' => [
            'name' => 'Administración de Cuotas',
            'modules' => [
                ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'is_special' => 1, 'order' => 0, 'package' => null],
                ['name' => 'Dashboard', 'route' => 'quota-dashboard', 'icon' => 'bi-speedometer2', 'is_special' => 1, 'order' => 0, 'package' => 'quota_admin'],
                ['name' => 'Socios', 'route' => 'quota-partners', 'icon' => 'bi-people', 'is_special' => 0, 'order' => 1, 'package' => 'quota_admin'],
                ['name' => 'Planes', 'route' => 'quota-plans', 'icon' => 'bi-calendar3', 'is_special' => 0, 'order' => 2, 'package' => 'quota_admin'],
                ['name' => 'Cuotas', 'route' => 'quota-items', 'icon' => 'bi-credit-card', 'is_special' => 0, 'order' => 3, 'package' => 'quota_admin'],
                ['name' => 'Pagos', 'route' => 'quota-payments', 'icon' => 'bi-cash-coin', 'is_special' => 0, 'order' => 4, 'package' => 'quota_admin'],
                ['name' => 'Configuración', 'route' => 'quota-config', 'icon' => 'bi-sliders', 'is_special' => 0, 'order' => 5, 'package' => 'quota_admin'],
                ['name' => 'Estadísticas', 'route' => 'quota-statistics', 'icon' => 'bi-bar-chart', 'is_special' => 0, 'order' => 6, 'package' => 'quota_admin'],
            ],
            'migrations_path' => 'app/Packages/QuotaAdmin/Migrations',
            'seeder_class' => 'App\Packages\QuotaAdmin\Seeders\QuotaAdminSeeder',
        ],
        'hairsalon' => [
            'name' => 'Peluquería',
            'modules' => [
                ['name' => 'Menu', 'route' => 'menu', 'icon' => 'bi-list', 'is_special' => 1, 'order' => 0, 'package' => null],
                ['name' => 'Dashboard', 'route' => 'hairsalon-dashboard', 'icon' => 'bi-speedometer2', 'is_special' => 1, 'order' => 0, 'package' => 'hairsalon'],
                ['name' => 'Clientes', 'route' => 'hairsalon-clients', 'icon' => 'bi-people', 'is_special' => 0, 'order' => 1, 'package' => 'hairsalon'],
                ['name' => 'Servicios', 'route' => 'hairsalon-services', 'icon' => 'bi-scissors', 'is_special' => 0, 'order' => 2, 'package' => 'hairsalon'],
                ['name' => 'Caja', 'route' => 'hairsalon-cashier', 'icon' => 'bi-cart3', 'is_special' => 0, 'order' => 3, 'package' => 'hairsalon'],
                ['name' => 'Finanzas', 'route' => 'hairsalon-finances', 'icon' => 'bi-cash-stack', 'is_special' => 0, 'order' => 4, 'package' => 'hairsalon'],
                ['name' => 'Productos', 'route' => 'hairsalon-products', 'icon' => 'bi-box-seam', 'is_special' => 0, 'order' => 5, 'package' => 'hairsalon'],
                ['name' => 'Usuarios', 'route' => 'hairsalon-users', 'icon' => 'bi-person-badge', 'is_special' => 0, 'order' => 6, 'package' => 'hairsalon'],
                ['name' => 'Estadísticas', 'route' => 'hairsalon-statistics', 'icon' => 'bi-bar-chart', 'is_special' => 0, 'order' => 7, 'package' => 'hairsalon'],
                ['name' => 'Log', 'route' => 'hairsalon-log', 'icon' => 'bi-journal-text', 'is_special' => 0, 'order' => 8, 'package' => 'hairsalon'],
                ['name' => 'Configuración', 'route' => 'hairsalon-config', 'icon' => 'bi-sliders', 'is_special' => 0, 'order' => 9, 'package' => 'hairsalon'],
            ],
            'migrations_path' => 'app/Packages/HairSalon/Migrations',
            'seeder_class' => 'App\Packages\HairSalon\Seeders\HairSalonSeeder',
        ],
    ];

    public function handle(): int
    {
        $package = $this->argument('package');
        $companyDb = $this->option('company');

        if (!isset($this->packages[$package])) {
            $this->error("Package '{$package}' not found. Available packages: " . implode(', ', array_keys($this->packages)));
            return 1;
        }

        if (!$companyDb) {
            $this->error('Please specify a company with --company= option');
            return 1;
        }

        $company = Company::where('db', $companyDb)->first();
        if (!$company) {
            $this->error("Company with db '{$companyDb}' not found");
            return 1;
        }

        $this->info("Installing package '{$package}' for company '{$company->name}' (DB: {$companyDb})");

        $this->installPackage($package, $company);

        return 0;
    }

    protected function installPackage(string $package, Company $company): void
    {
        $packageConfig = $this->packages[$package];
        $companyDb = $company->db;

        $this->info("Creating database if not exists: {$companyDb}");
        $this->createDatabaseIfNotExists($companyDb);

        $this->info("Switching to company database: {$companyDb}");
        config(['database.connections.mysql.database' => $companyDb]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $this->info("Running required global migrations");
        \Artisan::call('migrate', ['--path' => 'database/migrations/2026_06_27_000001_create_user_module_orders_table.php']);

        $this->info("Running migrations for package: {$package}");
        $this->runMigrations($packageConfig['migrations_path']);

        $this->info("Running seeders for package: {$package}");
        $this->runSeeders($packageConfig['seeder_class']);

        $this->info("Adding modules to company: {$company->name}");
        $this->addModulesToCompany($company, $packageConfig['modules']);

        $this->info("Package '{$package}' installed successfully for company '{$company->name}'!");
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
        }
    }

    protected function runMigrations(string $migrationsPath): void
    {
        $fullPath = base_path($migrationsPath);
        
        if (!is_dir($fullPath)) {
            $this->warn("Migrations path not found: {$fullPath}");
            return;
        }

        $files = glob($fullPath . '/*.php');
        
        foreach ($files as $file) {
            $migrationName = basename($file, '.php');
            
            // Check if this migration's tables already exist
            if ($this->tablesAlreadyCreated($migrationName)) {
                $this->info("Migration {$migrationName} skipped - tables already exist");
                continue;
            }
            
            try {
                $this->call('migrate', ['--force' => true, '--path' => $migrationsPath . '/' . basename($file)]);
                $this->info("Migration {$migrationName} executed");
            } catch (\Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    $this->info("Migration {$migrationName} already applied");
                } else {
                    $this->warn("Migration {$migrationName} error: " . $e->getMessage());
                }
            }
        }
    }
    
    protected function tablesAlreadyCreated(string $migrationName): bool
    {
        // Map migration names to their tables
        $tablesMap = [
            '2026_04_07_100001_create_roles_table' => ['roles'],
            '2026_04_07_100002_create_users_table' => ['users'],
            '2026_04_07_100003_create_categories_table' => ['pos_categories'],
            '2026_04_07_100004_create_products_table' => ['pos_products'],
            '2026_04_07_100005_create_status_orders_table' => ['pos_status_orders'],
            '2026_04_07_100006_create_orders_table' => ['pos_orders'],
            '2026_04_07_100007_create_configs_table' => ['pos_configs'],
            '2026_04_07_100008_create_permissions_table' => ['pos_permissions'],
            '2026_04_07_100009_create_modules_table' => ['modules'],
            '2026_04_07_000001_create_roles_table' => ['roles'],
            '2026_04_07_000002_create_status_orders_table' => ['pos_status_orders'],
            '2026_04_07_000003_create_categories_table' => ['pos_categories'],
            '2026_04_07_000004_create_products_table' => ['pos_products'],
            '2026_04_07_000005_create_configs_table' => ['pos_configs'],
            '2026_04_07_000006_create_orders_table' => ['pos_orders'],
            '2026_04_07_000007_create_modules_table' => ['modules'],
            '2026_04_07_000008_create_permissions_table' => ['pos_permissions'],
            '2026_06_24_000001_create_hairsalon_configs_table' => ['hairsalon_configs'],
            '2026_06_24_000002_create_hairsalon_clients_table' => ['hairsalon_clients'],
            '2026_06_24_000003_create_hairsalon_service_categories_table' => ['hairsalon_service_categories'],
            '2026_06_24_000004_create_hairsalon_services_table' => ['hairsalon_services'],
            '2026_06_24_000005_create_hairsalon_jobs_table' => ['hairsalon_jobs'],
            '2026_06_24_000006_create_hairsalon_job_services_table' => ['hairsalon_job_services'],
            '2026_06_24_000007_create_hairsalon_cash_movements_table' => ['hairsalon_cash_movements'],
            '2026_06_24_000008_create_hairsalon_cash_registers_table' => ['hairsalon_cash_registers'],
            '2026_06_24_000009_create_hairsalon_products_table' => ['hairsalon_products'],
            '2026_06_24_000010_create_hairsalon_stock_movements_table' => ['hairsalon_stock_movements'],
            // 000011 is ALTER TABLE, not CREATE - excluded intentionally
            '2026_06_24_000014_create_hairsalon_appointments_table' => ['hairsalon_appointments'],
        ];
        
        if (!isset($tablesMap[$migrationName])) {
            return false;
        }
        
        foreach ($tablesMap[$migrationName] as $table) {
            if (!Schema::hasTable($table)) {
                return false;
            }
        }
        
        return true;
    }

    protected function runSeeders(string $seederClass): void
    {
        try {
            $this->call('db:seed', ['--class' => $seederClass, '--force' => true]);
        } catch (\Exception $e) {
            $this->warn("Seeder error: " . $e->getMessage());
        }
    }

    protected function addModulesToCompany(Company $company, array $modules): void
    {
        // Make sure package column exists in modules table
        try {
            if (!Schema::connection('mysql_parent')->hasColumn('modules', 'package')) {
                Schema::connection('mysql_parent')->table('modules', function (Blueprint $table) {
                    $table->string('package')->nullable()->after('description');
                });
                $this->info('Added package column to modules table');
            }
        } catch (\Exception $e) {
            $this->warn('Could not add package column: ' . $e->getMessage());
        }

        foreach ($modules as $moduleData) {
            $moduleId = null;
            $existingModule = Module::where('route', $moduleData['route'])->first();
            
            if (!$existingModule) {
                $module = Module::create([
                    'name' => $moduleData['name'],
                    'route' => $moduleData['route'],
                    'icon' => $moduleData['icon'],
                    'is_special' => $moduleData['is_special'],
                    'order' => $moduleData['order'],
                    'package' => $moduleData['package'] ?? 'pos',
                ]);
                $moduleId = $module->id;
            } else {
                $moduleId = $existingModule->id;
                // Update package if not set
                if (empty($existingModule->package)) {
                    $existingModule->update(['package' => $moduleData['package'] ?? 'pos']);
                }
            }

            $exists = DB::connection('mysql_parent')
                ->table('company_modules')
                ->where('company_id', $company->id)
                ->where('module_id', $moduleId)
                ->exists();

            if (!$exists) {
                $maxOrder = DB::connection('mysql_parent')
                    ->table('company_modules')
                    ->where('company_id', $company->id)
                    ->max('order') ?? 0;

                DB::connection('mysql_parent')
                    ->table('company_modules')
                    ->insert([
                        'company_id' => $company->id,
                        'module_id' => $moduleId,
                        'order' => $maxOrder + 1,
                    ]);
            }
        }
    }
}
