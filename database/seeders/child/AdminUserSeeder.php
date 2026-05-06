<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Check if users table exists
        if (!Schema::hasTable('users') || !Schema::hasTable('roles')) {
            $this->command->warn('Required tables do not exist. Skipping AdminUserSeeder.');
            return;
        }
        
        // Get roles columns to determine how to find admin role
        $roleColumns = Schema::getColumnListing('roles');
        $hasSlugColumn = in_array('slug', $roleColumns);
        
        if ($hasSlugColumn) {
            $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        } else {
            $adminRole = DB::table('roles')->where('name', 'Admin')->first();
        }
        
        if (!$adminRole) {
            $this->command->warn('Admin role not found. Please run RoleSeeder first.');
            return;
        }
        
        $exists = DB::table('users')->where('username', 'admin')->exists();
        
        if (!$exists) {
            // Get user columns to determine what to insert
            $userColumns = Schema::getColumnListing('users');
            
            $userData = [
                'name' => 'Administrador',
                'username' => 'admin',
                'password' => Hash::make('$0deJulio'),
                'role_id' => $adminRole->id
            ];
            
            // Remove role_id if not in columns
            if (!in_array('role_id', $userColumns)) {
                unset($userData['role_id']);
            }
            
            $userId = DB::table('users')->insertGetId($userData);
            $this->command->info('Admin user created with ID: ' . $userId);
        } else {
            $this->command->info('Admin user already exists.');
        }
    }
}