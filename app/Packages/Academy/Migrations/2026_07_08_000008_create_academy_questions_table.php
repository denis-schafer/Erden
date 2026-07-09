<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academy_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('academy_exams')->cascadeOnDelete();
            $table->text('question_text');
            $table->enum('type', ['multiple_choice'])->default('multiple_choice');
            $table->decimal('points', 5, 2)->default(1.00);
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_questions');
    }
};
