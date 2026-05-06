<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CompanyModuleSeeder extends Seeder
{
    public function run(): void
    {
        // Check if there are any companies in the database
        $companyCount = DB::connection('mysql_parent')->table('companies')->count();
        
        if ($companyCount === 0) {
            // No companies, skip company_modules seeding
            return;
        }
        
        // Check if company_modules already has data for any company
        $exists = DB::connection('mysql_parent')->table('company_modules')
            ->exists();
        
        if ($exists) {
            return;
        }
        
        $companies = DB::connection('mysql_parent')->table('companies')->get();
        $modules = DB::connection('mysql_parent')->table('modules')->get();
        
        foreach ($companies as $company) {
            $order = 1;
            foreach ($modules as $module) {
                DB::connection('mysql_parent')->table('company_modules')->insert([
                    'company_id' => $company->id,
                    'module_id' => $module->id,
                    'order' => $order++
                ]);
            }
        }
    }
}