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
            'pos_permissions', 'pos_modules', 'pos_users', 'pos_roles', 'migrations'
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

        // Connect to child database
        config(['database.connections.mysql.database' => $company->db]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Verify orders table
        if (!Schema::hasTable('orders')) {
            Log::info('[CompanyModuleController] verifyPosTablesStructure: orders table does not exist yet');
            return;
        }

        $columns = DB::getSchemaBuilder()->getColumnListing('orders');
        Log::info('[CompanyModuleController] verifyPosTablesStructure: Current columns: ' . implode(', ', $columns));

        // Add mp_payment_id if missing
        if (!in_array('mp_payment_id', $columns)) {
            try {
                DB::statement('ALTER TABLE orders ADD COLUMN mp_payment_id VARCHAR(50) NULL AFTER paid');
                Log::info('[CompanyModuleController] verifyPosTablesStructure: Added mp_payment_id column');
            } catch (\Exception $e) {
                Log::error('[CompanyModuleController] verifyPosTablesStructure: Error adding mp_payment_id: ' . $e->getMessage());
            }
        } else {
            Log::info('[CompanyModuleController] verifyPosTablesStructure: mp_payment_id already exists');
        }

        // Add mp_transaction_amount if missing
        if (!in_array('mp_transaction_amount', $columns)) {
            try {
                DB::statement('ALTER TABLE orders ADD COLUMN mp_transaction_amount DECIMAL(10,2) NULL AFTER mp_payment_id');
                Log::info('[CompanyModuleController] verifyPosTablesStructure: Added mp_transaction_amount column');
            } catch (\Exception $e) {
                Log::error('[CompanyModuleController] verifyPosTablesStructure: Error adding mp_transaction_amount: ' . $e->getMessage());
            }
        } else {
            Log::info('[CompanyModuleController] verifyPosTablesStructure: mp_transaction_amount already exists');
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
}