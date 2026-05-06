<?php

namespace App\Packages\Pos\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminExists = DB::table('users')->where('username', 'admin')->exists();
        
        if (!$adminExists) {
            DB::table('users')->insert([
                'name' => 'Administrador',
                'username' => 'admin',
                'email' => 'admin@pos.local',
                'password' => Hash::make('$0deJulio'),
                'role_id' => 1,
                'enable' => true,
                'printer_ip' => null,
                'printer_port' => 9100,
                'printer_type' => 'raw',
                'mercadopago_enable_qr' => false
            ]);
            
            $this->command->info('Admin user created: admin / $0deJulio');
        } else {
            $this->command->info('Admin user already exists');
        }
    }
}
