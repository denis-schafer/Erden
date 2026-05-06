<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('route', 100)->unique();
            $table->string('icon', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_special')->default(false);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('order')->default(0);
            $table->string('package', 50)->nullable();
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('modules')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
