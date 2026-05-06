<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('username', 100)->unique();
            $table->string('password', 255);
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('enable')->default(true);
            $table->string('printer_ip', 50)->nullable();
            $table->integer('printer_port')->default(9100);
            $table->string('printer_type', 20)->default('raw');
            $table->integer('printer_width')->default(80);
            $table->boolean('enable_print')->default(false);
            $table->boolean('mercadopago_qr_enabled')->default(false);
            $table->rememberToken();
            $table->timestamps();
            
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
