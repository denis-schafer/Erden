<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'Activo'],
            ['id' => 2, 'name' => 'Inactivo'],
            ['id' => 3, 'name' => 'Deuda'],
            ['id' => 4, 'name' => 'Suspendido'],
        ];
        
        foreach ($statuses as $status) {
            $exists = DB::connection('mysql_parent')->table('statuses')
                ->where('id', $status['id'])
                ->exists();
            
            if (!$exists) {
                DB::connection('mysql_parent')->table('statuses')->insert($status);
                echo "Created status: {$status['name']}\n";
            } else {
                DB::connection('mysql_parent')->table('statuses')
                    ->where('id', $status['id'])
                    ->update(['name' => $status['name']]);
                echo "Updated status: {$status['name']}\n";
            }
        }
    }
}