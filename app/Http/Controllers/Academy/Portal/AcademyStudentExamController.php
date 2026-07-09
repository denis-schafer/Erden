<?php

namespace App\Http\Controllers\Academy\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyStudentExamController extends Controller
{
    public function show(Request $request, $id)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $exam = DB::table('academy_exams')
            ->join('academy_courses', 'academy_exams.course_id', '=', 'academy_courses.id')
            ->where('academy_exams.id', $id)
            ->select('academy_exams.*', 'academy_courses.name as course_name')
            ->first();

        if (!$exam) {
            return response()->json(['message' => 'Examen no encontrado'], 404);
        }

        $enrollment = DB::table('academy_course_student')
            ->where('course_id', $exam->course_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'No inscrito en este curso'], 403);
        }

        $attemptsCount = DB::table('academy_exam_attempts')
            ->where('exam_id', $id)
            ->where('student_id', $student->id)
            ->count();

        if ($attemptsCount >= $exam->max_attempts) {
            $bestAttempt = DB::table('academy_exam_attempts')
                ->where('exam_id', $id)
                ->where('student_id', $student->id)
                ->orderBy('score', 'desc')
                ->first();

            return response()->json([
                'message' => 'Has alcanzado el máximo de intentos',
                'exam' => $exam,
                'best_attempt' => $bestAttempt,
                'max_attempts_reached' => true,
            ]);
        }

        $exam->questions = DB::table('academy_questions')
            ->where('exam_id', $id)
            ->orderBy('order')
            ->get()
            ->map(function ($question) {
                $question->options = DB::table('academy_question_options')
                    ->where('question_id', $question->id)
                    ->orderBy('order')
                    ->get(['id', 'option_text', 'order']);
                return $question;
            });

        $exam->attempt_number = $attemptsCount + 1;

        return response()->json(['exam' => $exam]);
    }

    public function submit(Request $request, $id)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $exam = DB::table('academy_exams')->find($id);
        if (!$exam) {
            return response()->json(['message' => 'Examen no encontrado'], 404);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|exists:academy_questions,id',
            'answers.*.selected_option_id' => 'required|exists:academy_question_options,id',
        ]);

        $questions = DB::table('academy_questions')
            ->where('exam_id', $id)
            ->get()
            ->keyBy('id');

        $totalScore = 0;
        $maxScore = $questions->sum('points');

        $answersData = [];
        foreach ($validated['answers'] as $answer) {
            $question = $questions->get($answer['question_id']);
            if (!$question) continue;

            $option = DB::table('academy_question_options')->find($answer['selected_option_id']);
            $isCorrect = $option && $option->is_correct;
            $pointsEarned = $isCorrect ? $question->points : 0;
            $totalScore += $pointsEarned;

            $answersData[] = [
                'question_id' => $question->id,
                'selected_option_id' => $answer['selected_option_id'],
                'is_correct' => $isCorrect,
                'points_earned' => $pointsEarned,
            ];
        }

        $passed = $totalScore >= $exam->passing_score;

        $attemptId = DB::table('academy_exam_attempts')->insertGetId([
            'exam_id' => $id,
            'student_id' => $student->id,
            'score' => $totalScore,
            'max_score' => $maxScore,
            'passed' => $passed,
            'started_at' => now()->subSeconds(30),
            'finished_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($answersData as $answer) {
            DB::table('academy_exam_answers')->insert([
                'attempt_id' => $attemptId,
                'question_id' => $answer['question_id'],
                'selected_option_id' => $answer['selected_option_id'],
                'is_correct' => $answer['is_correct'],
                'points_earned' => $answer['points_earned'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'attempt_id' => $attemptId,
            'score' => $totalScore,
            'max_score' => $maxScore,
            'passed' => $passed,
            'message' => $passed ? '¡Aprobado!' : 'No alcanzaste el puntaje mínimo',
        ], 201);
    }

    public function results(Request $request, $attemptId)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $attempt = DB::table('academy_exam_attempts')
            ->where('id', $attemptId)
            ->where('student_id', $student->id)
            ->first();

        if (!$attempt) {
            return response()->json(['message' => 'Intento no encontrado'], 404);
        }

        $attempt->exam = DB::table('academy_exams')->find($attempt->exam_id);

        $attempt->answers = DB::table('academy_exam_answers')
            ->join('academy_questions', 'academy_exam_answers.question_id', '=', 'academy_questions.id')
            ->where('academy_exam_answers.attempt_id', $attemptId)
            ->select(
                'academy_exam_answers.*',
                'academy_questions.question_text',
                'academy_questions.points as max_points'
            )
            ->get()
            ->map(function ($answer) {
                $selected = DB::table('academy_question_options')->find($answer->selected_option_id);
                $answer->selected_option_text = $selected->option_text ?? 'N/A';

                $correct = DB::table('academy_question_options')
                    ->where('question_id', $answer->question_id)
                    ->where('is_correct', true)
                    ->first();
                $answer->correct_option_text = $correct->option_text ?? 'N/A';

                $answer->all_options = DB::table('academy_question_options')
                    ->where('question_id', $answer->question_id)
                    ->orderBy('order')
                    ->get(['id', 'option_text', 'is_correct']);

                return $answer;
            });

        return response()->json($attempt);
    }

    private function getAuthenticatedStudent(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        $parts = explode(':', base64_decode($token));
        $studentId = $parts[0] ?? null;

        return $studentId ? DB::table('academy_students')->find($studentId) : null;
    }
}
