<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained('academy_modules')->nullOnDelete();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->decimal('passing_score', 5, 2)->default(6.00);
            $table->integer('max_attempts')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_exams');
    }
};
