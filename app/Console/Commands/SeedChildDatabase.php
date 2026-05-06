<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedChildDatabase extends Command
{
    protected $signature = 'seed:child {db : The database name (e.g., 1)} {--class= : Specific seeder class}';
    protected $description = 'Seed a child database';

    public function handle()
    {
        $dbName = $this->argument('db');
        $class = $this->option('class');
        
        if (!$dbName) {
            $this->error('Database name is required');
            return 1;
        }

        $this->info("Switching to database: {$dbName}");
        
        config(['database.connections.mysql.database' => $dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        require_once base_path('database/seeders/child/RoleSeeder.php');
        require_once base_path('database/seeders/child/PermissionSeeder.php');
        require_once base_path('database/seeders/child/RolePermissionSeeder.php');
        require_once base_path('database/seeders/child/UserSeeder.php');

        if ($class) {
            $this->info("Running seeder: {$class}");
            $seeder = new $class();
            $seeder->run();
        } else {
            $this->info('Running all seeders...');
            (new \Database\Seeders\Child\RoleChildSeeder())->run();
            (new \Database\Seeders\Child\PermissionChildSeeder())->run();
            (new \Database\Seeders\Child\RolePermissionChildSeeder())->run();
            (new \Database\Seeders\Child\UserChildSeeder())->run();
        }
        
        $this->info('Seeding completed successfully');
        
        return 0;
    }
}