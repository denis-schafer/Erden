<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('academy_exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('academy_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('academy_question_options')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->decimal('points_earned', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_exam_answers');
    }
};
