<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hairsalon_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('phone', 50)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('address', 300)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hairsalon_clients');
    }
};
