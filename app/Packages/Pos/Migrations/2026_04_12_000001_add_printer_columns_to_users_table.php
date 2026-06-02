<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'printer_ip')) {
                    $table->string('printer_ip')->nullable()->after('role_id');
                }
                if (!Schema::hasColumn('users', 'printer_port')) {
                    $table->integer('printer_port')->nullable()->default(9100)->after('printer_ip');
                }
                if (!Schema::hasColumn('users', 'printer_type')) {
                    $table->string('printer_type')->nullable()->default('raw')->after('printer_port');
                }
                if (!Schema::hasColumn('users', 'printer_width')) {
                    $table->string('printer_width')->nullable()->default('80mm')->after('printer_type');
                }
                if (!Schema::hasColumn('users', 'enable_print')) {
                    $table->boolean('enable_print')->default(false)->after('printer_width');
                }
                if (!Schema::hasColumn('users', 'mercadopago_qr_enabled')) {
                    $table->boolean('mercadopago_qr_enabled')->default(false)->after('enable_print');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columnsToRemove = ['printer_ip', 'printer_port', 'printer_type', 'printer_width', 'enable_print', 'mercadopago_qr_enabled'];
            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};