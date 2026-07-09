<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('academy_modules')->cascadeOnDelete();
            $table->string('name', 200);
            $table->longText('content')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_lessons');
    }
};
