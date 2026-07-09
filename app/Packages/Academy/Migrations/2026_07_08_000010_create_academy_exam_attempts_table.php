<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('academy_exams')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('academy_students')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0);
            $table->decimal('max_score', 5, 2)->default(0);
            $table->boolean('passed')->default(false);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_exam_attempts');
    }
};
