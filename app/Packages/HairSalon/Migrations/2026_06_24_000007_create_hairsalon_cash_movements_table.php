<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hairsalon_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['income', 'expense']);
            $table->string('concept', 200);
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 50)->default('cash');
            $table->unsignedBigInteger('job_id')->nullable();
            $table->unsignedBigInteger('cash_register_id')->nullable();
            $table->unsignedBigInteger('operator_id');
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('hairsalon_jobs')->onDelete('set null');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hairsalon_cash_movements');
    }
};
