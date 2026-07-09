<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyQuestionController extends Controller
{
    public function index($examId)
    {
        $exam = DB::table('academy_exams')->find($examId);
        if (!$exam) {
            return response()->json(['message' => 'Examen no encontrado'], 404);
        }

        $questions = DB::table('academy_questions')
            ->where('exam_id', $examId)
            ->orderBy('order')
            ->get()
            ->map(function ($question) {
                $question->options = DB::table('academy_question_options')
                    ->where('question_id', $question->id)
                    ->orderBy('order')
                    ->get();
                return $question;
            });

        return response()->json($questions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'exam_id' => 'required|exists:academy_exams,id',
            'question_text' => 'required|string',
            'type' => 'nullable|string|in:multiple_choice',
            'points' => 'nullable|numeric|min:0',
            'order' => 'nullable|integer|min:0',
            'options' => 'required|array|min:2',
            'options.*.option_text' => 'required|string',
            'options.*.is_correct' => 'required|boolean',
        ]);

        $validated['type'] = $validated['type'] ?? 'multiple_choice';
        $validated['points'] = $validated['points'] ?? 1;
        $validated['order'] = $validated['order'] ?? 0;

        $questionId = DB::table('academy_questions')->insertGetId([
            'exam_id' => $validated['exam_id'],
            'question_text' => $validated['question_text'],
            'type' => $validated['type'],
            'points' => $validated['points'],
            'order' => $validated['order'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($validated['options'] as $index => $option) {
            DB::table('academy_question_options')->insert([
                'question_id' => $questionId,
                'option_text' => $option['option_text'],
                'is_correct' => $option['is_correct'],
                'order' => $index,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $question = DB::table('academy_questions')->find($questionId);
        $question->options = DB::table('academy_question_options')
            ->where('question_id', $questionId)
            ->orderBy('order')
            ->get();

        return response()->json($question, 201);
    }

    public function show($id)
    {
        $question = DB::table('academy_questions')->find($id);
        if (!$question) {
            return response()->json(['message' => 'Pregunta no encontrada'], 404);
        }

        $question->options = DB::table('academy_question_options')
            ->where('question_id', $id)
            ->orderBy('order')
            ->get();

        return response()->json($question);
    }

    public function update(Request $request, $id)
    {
        $question = DB::table('academy_questions')->find($id);
        if (!$question) {
            return response()->json(['message' => 'Pregunta no encontrada'], 404);
        }

        $validated = $request->validate([
            'question_text' => 'sometimes|string',
            'type' => 'nullable|string|in:multiple_choice',
            'points' => 'nullable|numeric|min:0',
            'order' => 'nullable|integer|min:0',
            'options' => 'nullable|array|min:2',
            'options.*.id' => 'nullable|exists:academy_question_options,id',
            'options.*.option_text' => 'required_with:options|string',
            'options.*.is_correct' => 'required_with:options|boolean',
        ]);

        DB::table('academy_questions')->where('id', $id)->update([
            'question_text' => $validated['question_text'] ?? $question->question_text,
            'type' => $validated['type'] ?? $question->type,
            'points' => $validated['points'] ?? $question->points,
            'order' => $validated['order'] ?? $question->order,
            'updated_at' => now(),
        ]);

        if (isset($validated['options'])) {
            DB::table('academy_question_options')->where('question_id', $id)->delete();

            foreach ($validated['options'] as $index => $option) {
                DB::table('academy_question_options')->insert([
                    'question_id' => $id,
                    'option_text' => $option['option_text'],
                    'is_correct' => $option['is_correct'],
                    'order' => $index,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $question = DB::table('academy_questions')->find($id);
        $question->options = DB::table('academy_question_options')
            ->where('question_id', $id)
            ->orderBy('order')
            ->get();

        return response()->json($question);
    }

    public function destroy($id)
    {
        $question = DB::table('academy_questions')->find($id);
        if (!$question) {
            return response()->json(['message' => 'Pregunta no encontrada'], 404);
        }

        DB::table('academy_questions')->where('id', $id)->delete();

        return response()->json(['message' => 'Pregunta eliminada']);
    }
}
