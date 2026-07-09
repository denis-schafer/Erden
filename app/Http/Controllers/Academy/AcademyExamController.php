<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyExamController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('academy_exams')
            ->join('academy_courses', 'academy_exams.course_id', '=', 'academy_courses.id')
            ->select('academy_exams.*', 'academy_courses.name as course_name');

        if ($request->filled('course_id')) {
            $query->where('academy_exams.course_id', $request->course_id);
        }

        $exams = $query->orderBy('academy_exams.created_at', 'desc')->paginate(50);

        $exams->getCollection()->transform(function ($exam) {
            $exam->questions_count = DB::table('academy_questions')->where('exam_id', $exam->id)->count();
            $exam->attempts_count = DB::table('academy_exam_attempts')->where('exam_id', $exam->id)->count();
            return $exam;
        });

        return response()->json($exams);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:academy_courses,id',
            'module_id' => 'nullable|exists:academy_modules,id',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'passing_score' => 'nullable|numeric|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
        ]);

        $validated['passing_score'] = $validated['passing_score'] ?? 6;
        $validated['max_attempts'] = $validated['max_attempts'] ?? 3;

        $id = DB::table('academy_exams')->insertGetId($validated);

        return response()->json(DB::table('academy_exams')->find($id), 201);
    }

    public function show($id)
    {
        $exam = DB::table('academy_exams')->find($id);
        if (!$exam) {
            return response()->json(['message' => 'Examen no encontrado'], 404);
        }

        $exam->questions = DB::table('academy_questions')
            ->where('exam_id', $id)
            ->orderBy('order')
            ->get()
            ->map(function ($question) {
                $question->options = DB::table('academy_question_options')
                    ->where('question_id', $question->id)
                    ->orderBy('order')
                    ->get();
                return $question;
            });

        return response()->json($exam);
    }

    public function update(Request $request, $id)
    {
        $exam = DB::table('academy_exams')->find($id);
        if (!$exam) {
            return response()->json(['message' => 'Examen no encontrado'], 404);
        }

        $validated = $request->validate([
            'course_id' => 'sometimes|exists:academy_courses,id',
            'module_id' => 'nullable|exists:academy_modules,id',
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'passing_score' => 'nullable|numeric|min:0|max:100',
            'max_attempts' => 'nullable|integer|min:1',
        ]);

        DB::table('academy_exams')->where('id', $id)->update($validated);

        return response()->json(DB::table('academy_exams')->find($id));
    }

    public function destroy($id)
    {
        $exam = DB::table('academy_exams')->find($id);
        if (!$exam) {
            return response()->json(['message' => 'Examen no encontrado'], 404);
        }

        DB::table('academy_exams')->where('id', $id)->delete();

        return response()->json(['message' => 'Examen eliminado']);
    }
}
