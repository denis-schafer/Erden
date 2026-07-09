<?php

namespace App\Http\Controllers\Academy\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyStudentCourseController extends Controller
{
    public function index(Request $request)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $enrollments = DB::table('academy_course_student')
            ->join('academy_courses', 'academy_course_student.course_id', '=', 'academy_courses.id')
            ->where('academy_course_student.student_id', $student->id)
            ->where('academy_courses.is_published', true)
            ->select(
                'academy_courses.*',
                'academy_course_student.status as enrollment_status',
                'academy_course_student.enrolled_at',
                'academy_course_student.completed_at'
            )
            ->get()
            ->map(function ($course) use ($student) {
                $totalLessons = DB::table('academy_modules')
                    ->join('academy_lessons', 'academy_modules.id', '=', 'academy_lessons.module_id')
                    ->where('academy_modules.course_id', $course->id)
                    ->count();

                $courseStudentId = DB::table('academy_course_student')
                    ->where('course_id', $course->id)
                    ->where('student_id', $student->id)
                    ->value('id');

                $completedLessons = $courseStudentId
                    ? DB::table('academy_course_student_lesson')
                        ->where('course_student_id', $courseStudentId)
                        ->count()
                    : 0;

                $course->total_lessons = $totalLessons;
                $course->completed_lessons = $completedLessons;
                $course->progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

                return $course;
            });

        return response()->json($enrollments);
    }

    public function modules(Request $request, $courseId)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $course = DB::table('academy_courses')->where('id', $courseId)->where('is_published', true)->first();
        if (!$course) {
            return response()->json(['message' => 'Curso no encontrado'], 404);
        }

        $enrollment = DB::table('academy_course_student')
            ->where('course_id', $courseId)
            ->where('student_id', $student->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'No inscrito en este curso'], 403);
        }

        $modules = DB::table('academy_modules')
            ->where('course_id', $courseId)
            ->orderBy('order')
            ->get()
            ->map(function ($module) use ($enrollment, $student) {
                $module->lessons = DB::table('academy_lessons')
                    ->where('module_id', $module->id)
                    ->where('is_published', true)
                    ->orderBy('order')
                    ->get()
                    ->map(function ($lesson) use ($enrollment) {
                        $completed = DB::table('academy_course_student_lesson')
                            ->where('course_student_id', $enrollment->id)
                            ->where('lesson_id', $lesson->id)
                            ->exists();
                        $lesson->completed = $completed;
                        return $lesson;
                    });

                $total = $module->lessons->count();
                $completed = $module->lessons->where('completed', true)->count();
                $module->progress = $total > 0 ? round(($completed / $total) * 100) : 0;

                $module->exams = DB::table('academy_exams')
                    ->where('course_id', $enrollment->course_id)
                    ->where('module_id', $module->id)
                    ->get()
                    ->map(function ($exam) use ($student) {
                        $attempts = DB::table('academy_exam_attempts')
                            ->where('exam_id', $exam->id)
                            ->where('student_id', $student->id)
                            ->get();
                        $exam->attempts = $attempts;
                        $exam->best_score = $attempts->max('score') ?? 0;
                        $exam->passed = $attempts->where('passed', true)->isNotEmpty();
                        return $exam;
                    });

                return $module;
            });

        $courseExams = DB::table('academy_exams')
            ->where('course_id', $courseId)
            ->whereNull('module_id')
            ->get()
            ->map(function ($exam) use ($student) {
                $attempts = DB::table('academy_exam_attempts')
                    ->where('exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->get();
                $exam->attempts = $attempts;
                $exam->best_score = $attempts->max('score') ?? 0;
                $exam->passed = $attempts->where('passed', true)->isNotEmpty();
                return $exam;
            });

        return response()->json([
            'course' => $course,
            'modules' => $modules,
            'course_exams' => $courseExams,
        ]);
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
