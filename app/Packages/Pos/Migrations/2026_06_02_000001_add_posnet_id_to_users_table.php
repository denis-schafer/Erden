<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'posnet_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('posnet_id', 255)->nullable()->after('mercadopago_qr_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'posnet_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('posnet_id');
            });
        }
    }
};
