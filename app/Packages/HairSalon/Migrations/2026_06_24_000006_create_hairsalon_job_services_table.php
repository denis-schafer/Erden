<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hairsalon_job_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('service_id');
            $table->decimal('price', 10, 2);

            $table->foreign('job_id')->references('id')->on('hairsalon_jobs')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('hairsalon_services')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hairsalon_job_services');
    }
};
