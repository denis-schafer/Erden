<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hairsalon_appointments', function (Blueprint $table) {
            $table->id();
            $table->string('client_name', 200);
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('operator_id');
            $table->json('service_ids')->nullable();
            $table->json('custom_services')->nullable();
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->integer('duration_min')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->string('color', 7)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('client_id')->references('id')->on('hairsalon_clients')->onDelete('set null');
            $table->foreign('operator_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hairsalon_appointments');
    }
};
