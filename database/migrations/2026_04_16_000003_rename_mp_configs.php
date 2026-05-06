<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check FIRST if mp_client_id already exists
        $clientIdExists = DB::connection('mysql_parent')->table('configs')
            ->where('name', 'mp_client_id')
            ->exists();
        
        if ($clientIdExists) {
            // Already has mp_client_id - delete old duplicates (only those with target=payment, not oauth)
            DB::connection('mysql_parent')->table('configs')
                ->where('name', 'mp_access_token')
                ->where('target', 'payment')
                ->delete();
        } else {
            // No mp_client_id yet - check for old token
            $hasOldToken = DB::connection('mysql_parent')->table('configs')
                ->where('name', 'mp_access_token')
                ->exists();
            
            if ($hasOldToken) {
                // Rename old token to client_id
                DB::connection('mysql_parent')->table('configs')
                    ->where('name', 'mp_access_token')
                    ->update([
                        'name' => 'mp_client_id',
                        'description' => 'Client ID de MercadoPago',
                        'target' => 'payment',
                        'updated_at' => now(),
                    ]);
            }
        }
        
        // STEP 4: Add mp_client_secret if not exists
        if (!DB::connection('mysql_parent')->table('configs')->where('name', 'mp_client_secret')->exists()) {
            DB::connection('mysql_parent')->table('configs')->insert([
                'name' => 'mp_client_secret',
                'value' => '',
                'target' => 'payment',
                'type' => 'string',
                'description' => 'Client Secret de MercadoPago',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // STEP 5: Add mp_access_token (OAuth token) with target oauth if not exists
        if (!DB::connection('mysql_parent')->table('configs')
            ->where('name', 'mp_access_token')
            ->where('target', 'oauth')
            ->exists()) {
            DB::connection('mysql_parent')->table('configs')->insert([
                'name' => 'mp_access_token',
                'value' => '',
                'target' => 'oauth',
                'type' => 'string',
                'description' => 'Access Token para pagos (obtenido via OAuth)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // STEP 6: Add mp_token_expires_at if not exists
        if (!DB::connection('mysql_parent')->table('configs')->where('name', 'mp_token_expires_at')->exists()) {
            DB::connection('mysql_parent')->table('configs')->insert([
                'name' => 'mp_token_expires_at',
                'value' => '',
                'target' => 'oauth',
                'type' => 'string',
                'description' => 'Fecha de expiración del token',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // STEP 7: Add mp_mode if not exists
        if (!DB::connection('mysql_parent')->table('configs')->where('name', 'mp_mode')->exists()) {
            DB::connection('mysql_parent')->table('configs')->insert([
                'name' => 'mp_mode',
                'value' => 'sandbox',
                'target' => 'payment',
                'type' => 'string',
                'description' => 'Modo de MercadoPago (sandbox/production)',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // STEP 8: Add mp_enable if not exists
        if (!DB::connection('mysql_parent')->table('configs')->where('name', 'mp_enable')->exists()) {
            DB::connection('mysql_parent')->table('configs')->insert([
                'name' => 'mp_enable',
                'value' => 'false',
                'target' => 'payment',
                'type' => 'boolean',
                'description' => 'Habilitar pagos con MercadoPago',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        
        // STEP 9: Add mp_public_key if not exists
        if (!DB::connection('mysql_parent')->table('configs')->where('name', 'mp_public_key')->exists()) {
            DB::connection('mysql_parent')->table('configs')->insert([
                'name' => 'mp_public_key',
                'value' => '',
                'target' => 'payment',
                'type' => 'string',
                'description' => 'Public Key de MercadoPago',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('mysql_parent')->table('configs')->where('name', 'mp_client_secret')->delete();
        DB::connection('mysql_parent')->table('configs')->where('name', 'mp_public_key')->delete();
        DB::connection('mysql_parent')->table('configs')->where('name', 'mp_mode')->delete();
        DB::connection('mysql_parent')->table('configs')->where('name', 'mp_enable')->delete();
        DB::connection('mysql_parent')->table('configs')->where('name', 'mp_token_expires_at')->delete();
        DB::connection('mysql_parent')->table('configs')
            ->where('name', 'mp_access_token')
            ->where('target', 'oauth')
            ->delete();
    }
};