<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quota_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('value', 500)->nullable();
            $table->string('type', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quota_configs');
    }
};
