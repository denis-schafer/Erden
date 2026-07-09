<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_course_student_lesson', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_student_id')->constrained('academy_course_student')->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained('academy_lessons')->cascadeOnDelete();
            $table->boolean('completed')->default(true);
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['course_student_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_course_student_lesson');
    }
};
