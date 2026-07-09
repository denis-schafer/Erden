<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyGradingController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('academy_exam_attempts')
            ->join('academy_exams', 'academy_exam_attempts.exam_id', '=', 'academy_exams.id')
            ->join('academy_students', 'academy_exam_attempts.student_id', '=', 'academy_students.id')
            ->join('academy_courses', 'academy_exams.course_id', '=', 'academy_courses.id')
            ->select(
                'academy_exam_attempts.*',
                'academy_exams.name as exam_name',
                'academy_students.first_name',
                'academy_students.last_name',
                'academy_students.dni',
                'academy_courses.name as course_name'
            );

        if ($request->filled('exam_id')) {
            $query->where('academy_exam_attempts.exam_id', $request->exam_id);
        }

        if ($request->filled('student_id')) {
            $query->where('academy_exam_attempts.student_id', $request->student_id);
        }

        if ($request->has('passed')) {
            $query->where('academy_exam_attempts.passed', $request->boolean('passed'));
        }

        return response()->json($query->orderBy('academy_exam_attempts.created_at', 'desc')->paginate(50));
    }

    public function show($attemptId)
    {
        $attempt = DB::table('academy_exam_attempts')->find($attemptId);
        if (!$attempt) {
            return response()->json(['message' => 'Intento no encontrado'], 404);
        }

        $attempt->exam = DB::table('academy_exams')->find($attempt->exam_id);
        $attempt->student = DB::table('academy_students')->find($attempt->student_id);

        $attempt->answers = DB::table('academy_exam_answers')
            ->join('academy_questions', 'academy_exam_answers.question_id', '=', 'academy_questions.id')
            ->leftJoin('academy_question_options', 'academy_exam_answers.selected_option_id', '=', 'academy_question_options.id')
            ->where('academy_exam_answers.attempt_id', $attemptId)
            ->select(
                'academy_exam_answers.*',
                'academy_questions.question_text',
                'academy_questions.points as max_points',
                'academy_question_options.option_text as selected_option_text'
            )
            ->get()
            ->map(function ($answer) {
                $answer->all_options = DB::table('academy_question_options')
                    ->where('question_id', $answer->question_id)
                    ->orderBy('order')
                    ->get();
                return $answer;
            });

        return response()->json($attempt);
    }

    public function release($attemptId)
    {
        $attempt = DB::table('academy_exam_attempts')->find($attemptId);
        if (!$attempt) {
            return response()->json(['message' => 'Intento no encontrado'], 404);
        }

        DB::table('academy_exam_attempts')
            ->where('id', $attemptId)
            ->update(['finished_at' => $attempt->finished_at ?? now()]);

        return response()->json(['message' => 'Calificación liberada']);
    }
}
