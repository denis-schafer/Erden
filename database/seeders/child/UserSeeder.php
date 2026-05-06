<?php

namespace Database\Seeders\Child;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = DB::table('roles')->where('slug', 'admin')->first();
        
        if (!$adminRole) {
            echo "Admin role not found. Please run RoleSeeder first.\n";
        } else {
            $exists = DB::table('users')->where('username', 'testuser')->exists();
            
            if (!$exists) {
                DB::table('users')->insert([
                    'name' => 'Local Test User',
                    'username' => 'testuser',
                    'password' => Hash::make('test123'),
                    'role_id' => $adminRole->id
                ]);
                echo "Created user: testuser\n";
            } else {
                echo "User testuser already exists.\n";
            }
        }
    }
}
