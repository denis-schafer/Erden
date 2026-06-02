<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            if (Schema::hasColumn('users', 'mercadopago_enable_qr')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('mercadopago_enable_qr');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'mercadopago_enable_qr')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('mercadopago_enable_qr')->default(false);
            });
        }
    }
};
