<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create users table if not exists
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('sync_id', 36)->nullable()->unique()->after('id');
                $table->string('name');
                $table->string('username')->unique();
                $table->string('email')->nullable()->unique();
                $table->string('password');
                $table->foreignId('role_id')->constrained()->onDelete('cascade');
                $table->string('printer_ip')->nullable();
                $table->integer('printer_port')->nullable()->default(9100);
                $table->string('printer_type')->nullable()->default('raw');
                $table->string('printer_width')->nullable()->default('80mm');
                $table->boolean('enable')->default(true);
                $table->boolean('enable_print')->default(false);
                $table->boolean('mercadopago_qr_enabled')->default(false);
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'sync_id')) {
                    $table->string('sync_id', 36)->nullable()->unique()->after('id');
                }
                if (!Schema::hasColumn('users', 'enable')) {
                    $table->boolean('enable')->default(true)->after('printer_type');
                }
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
                if (!Schema::hasColumn('users', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
