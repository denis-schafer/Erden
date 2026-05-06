<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'mercadopago_enable_qr')) {
                $table->dropColumn('mercadopago_enable_qr');
            }
            if (Schema::hasColumn('users', 'mercadopago_qr_enable')) {
                $table->dropColumn('mercadopago_qr_enable');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'mercadopago_qr_enabled')) {
                $table->boolean('mercadopago_qr_enabled')->default(false)->after('mercadopago_qr_enabled');
            }
        });
    }
};
