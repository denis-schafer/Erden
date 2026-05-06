<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GlobalConfigSeeder extends Seeder
{
    public function run(): void
    {
        // ========================================
        // 1. General configs
        // ========================================
        $generalConfigs = [
            ['name' => 'business_name', 'value' => '', 'target' => 'general', 'type' => 'string', 'description' => 'Nombre del negocio/empresa'],
            ['name' => 'business_address', 'value' => '', 'target' => 'general', 'type' => 'string', 'description' => 'Dirección del negocio'],
            ['name' => 'business_phone', 'value' => '', 'target' => 'general', 'type' => 'string', 'description' => 'Teléfono de contacto'],
            ['name' => 'business_nit', 'value' => '', 'target' => 'general', 'type' => 'string', 'description' => 'NIT o identificador fiscal'],
        ];

        foreach ($generalConfigs as $config) {
            $this->insertIfNotExists($config);
        }

        // ========================================
        // 2. Print configs
        // ========================================
        $printConfigs = [
            ['name' => 'enable_print', 'value' => 'false', 'target' => 'print', 'type' => 'boolean', 'description' => 'Habilitar impresión automática de tickets'],
            ['name' => 'printer_ip', 'value' => '', 'target' => 'print', 'type' => 'string', 'description' => 'IP de la impresora'],
            ['name' => 'printer_port', 'value' => '9100', 'target' => 'print', 'type' => 'number', 'description' => 'Puerto de la impresora'],
            ['name' => 'printer_width', 'value' => '48', 'target' => 'print', 'type' => 'number', 'description' => 'Ancho del papel (32 o 48 caracteres)'],
        ];

        foreach ($printConfigs as $config) {
            $this->insertIfNotExists($config);
        }

        // ========================================
        // 3. MercadoPago configs
        // ========================================
        $mpConfigs = [
            ['name' => 'mp_client_id', 'value' => '', 'target' => 'payment', 'type' => 'string', 'description' => 'Client ID de MercadoPago'],
            ['name' => 'mp_client_secret', 'value' => '', 'target' => 'payment', 'type' => 'string', 'description' => 'Client Secret de MercadoPago'],
            ['name' => 'mp_public_key', 'value' => '', 'target' => 'payment', 'type' => 'string', 'description' => 'Public Key de MercadoPago'],
            ['name' => 'mp_mode', 'value' => 'sandbox', 'target' => 'payment', 'type' => 'string', 'description' => 'Modo de MercadoPago (sandbox/production)'],
            ['name' => 'mp_enable', 'value' => 'false', 'target' => 'payment', 'type' => 'boolean', 'description' => 'Habilitar pagos con MercadoPago'],
            ['name' => 'mp_access_token', 'value' => '', 'target' => 'oauth', 'type' => 'string', 'description' => 'Access Token para pagos (obtenido via OAuth)'],
            ['name' => 'mp_token_expires_at', 'value' => '', 'target' => 'oauth', 'type' => 'string', 'description' => 'Fecha de expiración del token'],
        ];

        foreach ($mpConfigs as $config) {
            $this->insertIfNotExists($config);
        }
    }

    private function insertIfNotExists(array $config): void
    {
        $exists = DB::connection('mysql_parent')->table('configs')
            ->where('name', $config['name'])
            ->exists();

        if (!$exists) {
            DB::connection('mysql_parent')->table('configs')->insert([
                'name' => $config['name'],
                'value' => $config['value'],
                'target' => $config['target'],
                'type' => $config['type'],
                'description' => $config['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
