<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateChildDatabase extends Command
{
    protected $signature = 'migrate:child {db : The database name (e.g., 1)}';
    protected $description = 'Run migrations on a child database';

    public function handle()
    {
        $dbName = $this->argument('db');
        
        if (!$dbName) {
            $this->error('Database name is required');
            return 1;
        }

        $this->info("Switching to database: {$dbName}");
        
        config(['database.connections.mysql.database' => $dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Schema::dropAllTables();

        $this->info('Running migrations...');
        
        $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php']);
        $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_04_03_100001_create_roles_table.php']);
        $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_04_03_100002_create_permissions_table.php']);
        $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_04_03_100005_add_role_id_to_users_table.php']);
        $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_04_03_100006_modify_users_table.php']);
        $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_04_03_100003_create_role_user_table.php']);
        $this->call('migrate', ['--force' => true, '--path' => 'database/migrations/2026_04_03_100004_create_role_permission_table.php']);
        
        $this->info('Migrations completed successfully');
        
        return 0;
    }
}