<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CompanyModuleController extends Controller
{
    public function index()
    {
        $companies = Company::where('status_id', 1)->get(['id', 'db', 'name']);
        return response()->json($companies);
    }

    public function show($companyId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $assignedModules = DB::connection('mysql_parent')
            ->table('company_modules')
            ->where('company_id', $companyId)
            ->pluck('module_id')
            ->toArray();

        $allModules = Module::orderBy('order')->get(['id', 'name', 'route', 'is_special']);

        return response()->json([
            'company' => $company,
            'assigned_modules' => $assignedModules,
            'all_modules' => $allModules
        ]);
    }

    public function update(Request $request, $companyId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $moduleIds = $request->input('module_ids', []);

        $mandatoryModules = Module::whereIn('route', ['dashboard', 'menu'])->pluck('id')->toArray();
        $moduleIds = array_unique(array_merge($mandatoryModules, $moduleIds));

        DB::connection('mysql_parent')
            ->table('company_modules')
            ->where('company_id', $companyId)
            ->delete();

        $order = 1;
        foreach ($moduleIds as $moduleId) {
            DB::connection('mysql_parent')
                ->table('company_modules')
                ->insert([
                    'company_id' => $companyId,
                    'module_id' => $moduleId,
                    'order' => $order++
                ]);
        }

        $posModuleIds = Module::where('package', 'pos')->pluck('id')->toArray();
        
        $currentPosAssigned = DB::connection('mysql_parent')
            ->table('company_modules as cm')
            ->join('modules as m', 'cm.module_id', '=', 'm.id')
            ->where('cm.company_id', $companyId)
            ->where('m.package', 'pos')
            ->pluck('cm.module_id')
            ->toArray();
        
        $newPosAssigned = array_intersect($moduleIds, $posModuleIds);
        
        $wasAssigned = !empty($currentPosAssigned);
        $isAssigned = !empty($newPosAssigned);
        
        Log::info('[CompanyModuleController] update: posModuleIds=' . json_encode($posModuleIds) . ', currentPosAssigned=' . json_encode($currentPosAssigned) . ', newPosAssigned=' . json_encode($newPosAssigned) . ', wasAssigned=' . ($wasAssigned ? 'yes' : 'no') . ', isAssigned=' . ($isAssigned ? 'yes' : 'no'));
        
        if ($isAssigned && !$wasAssigned) {
            Log::info('[CompanyModuleController] update: Installing POS package (first time)');
            $this->installPosPackage($company);
        } elseif ($isAssigned && $wasAssigned) {
            Log::info('[CompanyModuleController] update: Reinstalling POS package');
            $this->uninstallPosPackage($company);
            $this->installPosPackage($company);
        } elseif ($wasAssigned && !$isAssigned) {
            Log::info('[CompanyModuleController] update: Uninstalling POS package');
            $this->uninstallPosPackage($company);
        }

        // QuotaAdmin package detection
        $quotaModuleIds = Module::where('package', 'quota_admin')->pluck('id')->toArray();

        $currentQuotaAssigned = DB::connection('mysql_parent')
            ->table('company_modules as cm')
            ->join('modules as m', 'cm.module_id', '=', 'm.id')
            ->where('cm.company_id', $companyId)
            ->where('m.package', 'quota_admin')
            ->pluck('cm.module_id')
            ->toArray();

        $newQuotaAssigned = array_intersect($moduleIds, $quotaModuleIds);

        $quotaWasAssigned = !empty($currentQuotaAssigned);
        $quotaIsAssigned = !empty($newQuotaAssigned);

        Log::info('[CompanyModuleController] update: quotaModuleIds=' . json_encode($quotaModuleIds) . ', currentQuotaAssigned=' . json_encode($currentQuotaAssigned) . ', newQuotaAssigned=' . json_encode($newQuotaAssigned) . ', quotaWasAssigned=' . ($quotaWasAssigned ? 'yes' : 'no') . ', quotaIsAssigned=' . ($quotaIsAssigned ? 'yes' : 'no'));

        if ($quotaIsAssigned && !$quotaWasAssigned) {
            Log::info('[CompanyModuleController] update: Installing QuotaAdmin package (first time)');
            $this->installQuotaAdminPackage($company);
        } elseif ($quotaIsAssigned && $quotaWasAssigned) {
            Log::info('[CompanyModuleController] update: Reinstalling QuotaAdmin package');
            $this->uninstallQuotaAdminPackage($company);
            $this->installQuotaAdminPackage($company);
        } elseif ($quotaWasAssigned && !$quotaIsAssigned) {
            Log::info('[CompanyModuleController] update: Uninstalling QuotaAdmin package');
            $this->uninstallQuotaAdminPackage($company);
        }

        // HairSalon package detection
        $hairsalonModuleIds = Module::where('package', 'hairsalon')->pluck('id')->toArray();

        $currentHairSalonAssigned = DB::connection('mysql_parent')
            ->table('company_modules as cm')
            ->join('modules as m', 'cm.module_id', '=', 'm.id')
            ->where('cm.company_id', $companyId)
            ->where('m.package', 'hairsalon')
            ->pluck('cm.module_id')
            ->toArray();

        $newHairSalonAssigned = array_intersect($moduleIds, $hairsalonModuleIds);

        $hairsalonWasAssigned = !empty($currentHairSalonAssigned);
        $hairsalonIsAssigned = !empty($newHairSalonAssigned);

        Log::info('[CompanyModuleController] update: hairsalonModuleIds=' . json_encode($hairsalonModuleIds) . ', currentHairSalonAssigned=' . json_encode($currentHairSalonAssigned) . ', newHairSalonAssigned=' . json_encode($newHairSalonAssigned) . ', hairsalonWasAssigned=' . ($hairsalonWasAssigned ? 'yes' : 'no') . ', hairsalonIsAssigned=' . ($hairsalonIsAssigned ? 'yes' : 'no'));

        if ($hairsalonIsAssigned && !$hairsalonWasAssigned) {
            Log::info('[CompanyModuleController] update: Installing HairSalon package (first time)');
            $this->installHairSalonPackage($company);
        } elseif ($hairsalonIsAssigned && $hairsalonWasAssigned) {
            Log::info('[CompanyModuleController] update: Reinstalling HairSalon package');
            $this->uninstallHairSalonPackage($company);
            $this->installHairSalonPackage($company);
        } elseif ($hairsalonWasAssigned && !$hairsalonIsAssigned) {
            Log::info('[CompanyModuleController] update: Uninstalling HairSalon package');
            $this->uninstallHairSalonPackage($company);
        }

        return response()->json(['message' => 'Módulos actualizados correctamente']);
    }

    protected function installPosPackage(Company $company): void
    {
        \Log::info('[CompanyModuleController] installPosPackage: INICIO para empresa: ' . $company->name . ' (DB: ' . $company->db . ')');
        
        $companyDb = $company->db;

        $this->createDatabaseIfNotExists($companyDb);

        config(['database.connections.mysql.database' => $companyDb]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        \Log::info('[CompanyModuleController] installPosPackage: BD configurada a: ' . $companyDb);
        
        \Artisan::call('migrate', ['--path' => 'database/migrations/2026_06_27_000001_create_user_module_orders_table.php']);
        $this->runPosMigrations();
        $this->verifyPosTablesStructure($company);
        $this->runPosSeeders($company);
        $this->verifyPrintJobsTable($company);
        
        // Generate print_agent_key if not exists
        if (empty($company->print_agent_key)) {
            DB::connection('mysql_parent')
                ->table('companies')
                ->where('id', $company->id)
                ->update(['print_agent_key' => (string) Str::uuid()]);
            
            $company->refresh();
            \Log::info('[CompanyModuleController] installPosPackage: print_agent_key generada para: ' . $company->name);
        }
        
        \Log::info('[CompanyModuleController] installPosPackage: FIN para empresa: ' . $company->name);
    }

    protected function uninstallPosPackage(Company $company): void
    {
        Log::info('[CompanyModuleController] uninstallPosPackage: INICIO para empresa: ' . $company->name . ' (DB: ' . $company->db . ')');
        
        $companyDb = $company->db;

        $this->connectToChildDatabase($companyDb);
        
        $tables = [
            'orders', 'status_orders', 'configs', 'products', 'categories',
            'permissions', 'role_user', 'model_has_permissions', 'model_has_roles',
            'role_has_permissions', 'modules', 'users', 'roles',
            'pos_orders', 'pos_status_orders', 'pos_configs', 'pos_products', 'pos_categories',
            'pos_permissions', 'pos_modules', 'pos_users', 'pos_roles',
        ];
        
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            foreach ($tables as $table) {
                try {
                    Schema::dropIfExists($table);
                } catch (\Exception $e) {
                    // Ignore errors for individual tables
                }
            }
            
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->deleteMigrationRecords('app/Packages/Pos/Migrations');
            
            Log::info('[CompanyModuleController] uninstallPosPackage: Tablas POS eliminadas (incluyendo migrations)');
        } catch (\Exception $e) {
            try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Exception $e2) {}
            Log::error('[CompanyModuleController] uninstallPosPackage: Error al eliminar tablas: ' . $e->getMessage());
        }
        
        Log::info('[CompanyModuleController] uninstallPosPackage: FIN para empresa: ' . $company->name);
    }

    protected function connectToChildDatabase(string $dbName): void
    {
        config(['database.connections.mysql.database' => $dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    protected function createDatabaseIfNotExists(string $dbName): void
    {
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
        
        if (empty($exists)) {
            DB::statement("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
        }
    }

    protected function runPosMigrations(): void
    {
        \Log::info('[CompanyModuleController] runPosMigrations: INICIO');
        
        $migrationsPath = 'app/Packages/Pos/Migrations';
        $fullPath = base_path($migrationsPath);
        
        if (!is_dir($fullPath)) {
            \Log::warning('[CompanyModuleController] runPosMigrations: Directorio no encontrado: ' . $fullPath);
            return;
        }

        $files = glob($fullPath . '/*.php');
        sort($files);
        
        \Log::info('[CompanyModuleController] runPosMigrations: Archivos encontrados: ' . count($files));
        
        foreach ($files as $file) {
            $migrationName = basename($file);
            \Log::info('[CompanyModuleController] runPosMigrations: Ejecutando: ' . $migrationName);
            try {
                $exitCode = Artisan::call('migrate', [
                    '--force' => true, 
                    '--path' => $migrationsPath . '/' . basename($file), 
                    '--database' => 'mysql'
                ]);
                \Log::info('[CompanyModuleController] runPosMigrations: Migration ' . $migrationName . ' completada con código: ' . $exitCode);
            } catch (\Exception $e) {
                \Log::error('[CompanyModuleController] runPosMigrations: Migration ' . $migrationName . ' error: ' . $e->getMessage());
            }
        }
        
        \Log::info('[CompanyModuleController] runPosMigrations: FIN');
    }

    protected function runPosSeeders(Company $company): void
    {
        Log::info('[CompanyModuleController] runPosSeeders: INICIO');

        // Reconnect to child database after migrate
        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        Log::info('[CompanyModuleController] runPosSeeders: Reconnected to DB: ' . $company->db);
        
        try {
            // Use Artisan call to properly run seeders with context
            $exitCode = Artisan::call('db:seed', [
                '--class' => 'App\\Packages\\Pos\\Seeders\\PosSeeder',
                '--force' => true,
                '--database' => 'mysql'
            ]);
            Log::info('[CompanyModuleController] runPosSeeders: POS Seeder executed with code: ' . $exitCode);
        } catch (\Exception $e) {
            Log::error('[CompanyModuleController] runPosSeeders: POS Seeder error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
        }
        
        Log::info('[CompanyModuleController] runPosSeeders: FIN');
    }

    protected function verifyPosTablesStructure(Company $company): void
    {
        Log::info('[CompanyModuleController] verifyPosTablesStructure: INICIO for ' . $company->db);

        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        $posTables = [
            'orders' => ['sync_id', 'mp_payment_id', 'mp_transaction_amount', 'deleted_at'],
            'status_orders' => ['sync_id', 'deleted_at'],
            'categories' => ['sync_id', 'deleted_at', 'order'],
            'products' => ['sync_id', 'deleted_at'],
            'users' => ['sync_id', 'deleted_at', 'posnet_id'],
        ];

        foreach ($posTables as $table => $columns) {
            if (!Schema::hasTable($table)) continue;

            $existing = DB::getSchemaBuilder()->getColumnListing($table);
            foreach ($columns as $column) {
                if (!in_array($column, $existing)) {
                    try {
                        $stmt = match ($column) {
                            'sync_id' => "ALTER TABLE {$table} ADD COLUMN sync_id VARCHAR(36) NULL DEFAULT NULL UNIQUE AFTER id",
                            'deleted_at' => "ALTER TABLE {$table} ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at",
                            'mp_payment_id' => "ALTER TABLE {$table} ADD COLUMN mp_payment_id VARCHAR(50) NULL AFTER paid",
                            'mp_transaction_amount' => "ALTER TABLE {$table} ADD COLUMN mp_transaction_amount DECIMAL(10,2) NULL AFTER mp_payment_id",
                            'order' => "ALTER TABLE {$table} ADD COLUMN `order` INT DEFAULT 0 AFTER enable",
                            'posnet_id' => "ALTER TABLE {$table} ADD COLUMN posnet_id VARCHAR(255) NULL AFTER mercadopago_qr_enabled",
                            default => null,
                        };
                        if ($stmt) {
                            DB::statement($stmt);
                            Log::info("[CompanyModuleController] verifyPosTablesStructure: Added {$column} to {$table}");
                        }
                    } catch (\Exception $e) {
                        Log::warning("[CompanyModuleController] verifyPosTablesStructure: Error adding {$column} to {$table}: " . $e->getMessage());
                    }
                }
            }
        }

        Log::info('[CompanyModuleController] verifyPosTablesStructure: FIN');
    }

    protected function verifyPrintJobsTable(Company $company): void
    {
        Log::info('[CompanyModuleController] verifyPrintJobsTable: INICIO for ' . $company->db);

        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        if (!Schema::hasTable('print_jobs')) {
            Schema::create('print_jobs', function ($table) {
                $table->id();
                $table->unsignedBigInteger('order_id');
                $table->string('printer_ip', 45);
                $table->integer('printer_port')->default(9100);
                $table->string('printer_width', 10)->default('80mm');
                $table->longText('ticket_data');
                $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
                $table->text('error_message')->nullable();
                $table->tinyInteger('attempts')->default(0);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('processed_at')->nullable();
            });
            Log::info('[CompanyModuleController] verifyPrintJobsTable: print_jobs table created');
        } else {
            Log::info('[CompanyModuleController] verifyPrintJobsTable: print_jobs table already exists');
        }

        Log::info('[CompanyModuleController] verifyPrintJobsTable: FIN');
    }

    protected function installQuotaAdminPackage(Company $company): void
    {
        Log::info('[CompanyModuleController] installQuotaAdminPackage: INICIO para empresa: ' . $company->name . ' (DB: ' . $company->db . ')');

        $companyDb = $company->db;

        $this->createDatabaseIfNotExists($companyDb);

        config(['database.connections.mysql.database' => $companyDb]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Log::info('[CompanyModuleController] installQuotaAdminPackage: BD configurada a: ' . $companyDb);

        \Artisan::call('migrate', ['--path' => 'database/migrations/2026_06_27_000001_create_user_module_orders_table.php']);
        $this->runQuotaAdminMigrations();
        $this->runQuotaAdminSeeders($company);

        Log::info('[CompanyModuleController] installQuotaAdminPackage: FIN para empresa: ' . $company->name);
    }

    protected function uninstallQuotaAdminPackage(Company $company): void
    {
        Log::info('[CompanyModuleController] uninstallQuotaAdminPackage: INICIO para empresa: ' . $company->name . ' (DB: ' . $company->db . ')');

        $companyDb = $company->db;

        $this->connectToChildDatabase($companyDb);

        $quotaTables = [
            'quota_payment_items',
            'quota_payments',
            'quotas',
            'quota_partner_config',
            'quota_configs',
            'quota_plans',
        ];

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($quotaTables as $table) {
                try {
                    Schema::dropIfExists($table);
                } catch (\Exception $e) {
                    Log::warning('[CompanyModuleController] uninstallQuotaAdminPackage: Error dropping ' . $table . ': ' . $e->getMessage());
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->deleteMigrationRecords('app/Packages/QuotaAdmin/Migrations');

            Log::info('[CompanyModuleController] uninstallQuotaAdminPackage: Tablas quota_* eliminadas');
        } catch (\Exception $e) {
            try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Exception $e2) {}
            Log::error('[CompanyModuleController] uninstallQuotaAdminPackage: Error: ' . $e->getMessage());
        }

        Log::info('[CompanyModuleController] uninstallQuotaAdminPackage: FIN para empresa: ' . $company->name);
    }

    protected function runQuotaAdminMigrations(): void
    {
        Log::info('[CompanyModuleController] runQuotaAdminMigrations: INICIO');

        $migrationsPath = 'app/Packages/QuotaAdmin/Migrations';
        $fullPath = base_path($migrationsPath);

        if (!is_dir($fullPath)) {
            Log::warning('[CompanyModuleController] runQuotaAdminMigrations: Directorio no encontrado: ' . $fullPath);
            return;
        }

        $files = glob($fullPath . '/*.php');
        sort($files);

        Log::info('[CompanyModuleController] runQuotaAdminMigrations: Archivos encontrados: ' . count($files));

        foreach ($files as $file) {
            $migrationName = basename($file);
            Log::info('[CompanyModuleController] runQuotaAdminMigrations: Ejecutando: ' . $migrationName);
            try {
                $exitCode = Artisan::call('migrate', [
                    '--force' => true,
                    '--path' => $migrationsPath . '/' . basename($file),
                    '--database' => 'mysql'
                ]);
                Log::info('[CompanyModuleController] runQuotaAdminMigrations: Migration ' . $migrationName . ' completada con código: ' . $exitCode);
            } catch (\Exception $e) {
                Log::error('[CompanyModuleController] runQuotaAdminMigrations: Migration ' . $migrationName . ' error: ' . $e->getMessage());
            }
        }

        Log::info('[CompanyModuleController] runQuotaAdminMigrations: FIN');
    }

    protected function runQuotaAdminSeeders(Company $company): void
    {
        Log::info('[CompanyModuleController] runQuotaAdminSeeders: INICIO');

        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        Log::info('[CompanyModuleController] runQuotaAdminSeeders: Reconnected to DB: ' . $company->db);

        try {
            $exitCode = Artisan::call('db:seed', [
                '--class' => 'App\\Packages\\QuotaAdmin\\Seeders\\QuotaAdminSeeder',
                '--force' => true,
                '--database' => 'mysql'
            ]);
            Log::info('[CompanyModuleController] runQuotaAdminSeeders: QuotaAdmin Seeder executed with code: ' . $exitCode);
        } catch (\Exception $e) {
            Log::error('[CompanyModuleController] runQuotaAdminSeeders: QuotaAdmin Seeder error: ' . $e->getMessage());
        }

        Log::info('[CompanyModuleController] runQuotaAdminSeeders: FIN');
    }

    protected function installHairSalonPackage(Company $company): void
    {
        Log::info('[CompanyModuleController] installHairSalonPackage: INICIO para empresa: ' . $company->name . ' (DB: ' . $company->db . ')');

        $companyDb = $company->db;

        $this->createDatabaseIfNotExists($companyDb);

        config(['database.connections.mysql.database' => $companyDb]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Log::info('[CompanyModuleController] installHairSalonPackage: BD configurada a: ' . $companyDb);

        \Artisan::call('migrate', ['--path' => 'database/migrations/2026_06_27_000001_create_user_module_orders_table.php']);
        $this->runHairSalonMigrations();
        $this->runHairSalonSeeders($company);

        Log::info('[CompanyModuleController] installHairSalonPackage: FIN para empresa: ' . $company->name);
    }

    protected function uninstallHairSalonPackage(Company $company): void
    {
        Log::info('[CompanyModuleController] uninstallHairSalonPackage: INICIO para empresa: ' . $company->name . ' (DB: ' . $company->db . ')');

        $companyDb = $company->db;

        $this->connectToChildDatabase($companyDb);

        $hairsalonTables = [
            'hairsalon_stock_movements',
            'hairsalon_products',
            'hairsalon_cash_registers',
            'hairsalon_cash_movements',
            'hairsalon_job_services',
            'hairsalon_jobs',
            'hairsalon_services',
            'hairsalon_service_categories',
            'hairsalon_clients',
            'hairsalon_configs',
        ];

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($hairsalonTables as $table) {
                try {
                    Schema::dropIfExists($table);
                } catch (\Exception $e) {
                    Log::warning('[CompanyModuleController] uninstallHairSalonPackage: Error dropping ' . $table . ': ' . $e->getMessage());
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            $this->deleteMigrationRecords('app/Packages/HairSalon/Migrations');

            Log::info('[CompanyModuleController] uninstallHairSalonPackage: Tablas hairsalon_* eliminadas');
        } catch (\Exception $e) {
            try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Exception $e2) {}
            Log::error('[CompanyModuleController] uninstallHairSalonPackage: Error: ' . $e->getMessage());
        }

        Log::info('[CompanyModuleController] uninstallHairSalonPackage: FIN para empresa: ' . $company->name);
    }

    protected function runHairSalonMigrations(): void
    {
        Log::info('[CompanyModuleController] runHairSalonMigrations: INICIO');

        $migrationsPath = 'app/Packages/HairSalon/Migrations';
        $fullPath = base_path($migrationsPath);

        if (!is_dir($fullPath)) {
            Log::warning('[CompanyModuleController] runHairSalonMigrations: Directorio no encontrado: ' . $fullPath);
            return;
        }

        $files = glob($fullPath . '/*.php');
        sort($files);

        Log::info('[CompanyModuleController] runHairSalonMigrations: Archivos encontrados: ' . count($files));

        foreach ($files as $file) {
            $migrationName = basename($file);
            Log::info('[CompanyModuleController] runHairSalonMigrations: Ejecutando: ' . $migrationName);
            try {
                $exitCode = Artisan::call('migrate', [
                    '--force' => true,
                    '--path' => $migrationsPath . '/' . basename($file),
                    '--database' => 'mysql'
                ]);
                Log::info('[CompanyModuleController] runHairSalonMigrations: Migration ' . $migrationName . ' completada con código: ' . $exitCode);
            } catch (\Exception $e) {
                Log::error('[CompanyModuleController] runHairSalonMigrations: Migration ' . $migrationName . ' error: ' . $e->getMessage());
            }
        }

        Log::info('[CompanyModuleController] runHairSalonMigrations: FIN');
    }

    protected function deleteMigrationRecords(string $migrationsPath): void
    {
        $fullPath = base_path($migrationsPath);
        if (!is_dir($fullPath)) return;

        $files = glob($fullPath . '/*.php');
        foreach ($files as $file) {
            $migrationName = basename($file, '.php');
            try {
                DB::table('migrations')->where('migration', $migrationName)->delete();
            } catch (\Exception $e) {
                Log::warning('[CompanyModuleController] deleteMigrationRecords: Error deleting ' . $migrationName . ': ' . $e->getMessage());
            }
        }
    }

    protected function runHairSalonSeeders(Company $company): void
    {
        Log::info('[CompanyModuleController] runHairSalonSeeders: INICIO');

        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        Log::info('[CompanyModuleController] runHairSalonSeeders: Reconnected to DB: ' . $company->db);

        try {
            $exitCode = Artisan::call('db:seed', [
                '--class' => 'App\\Packages\\HairSalon\\Seeders\\HairSalonSeeder',
                '--force' => true,
                '--database' => 'mysql'
            ]);
            Log::info('[CompanyModuleController] runHairSalonSeeders: HairSalon Seeder executed with code: ' . $exitCode);
        } catch (\Exception $e) {
            Log::error('[CompanyModuleController] runHairSalonSeeders: HairSalon Seeder error: ' . $e->getMessage());
        }

        Log::info('[CompanyModuleController] runHairSalonSeeders: FIN');
    }
}