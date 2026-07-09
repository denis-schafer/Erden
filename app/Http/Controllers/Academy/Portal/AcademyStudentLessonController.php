<?php

namespace App\Http\Controllers\Academy\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyStudentLessonController extends Controller
{
    public function show(Request $request, $id)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $lesson = DB::table('academy_lessons')
            ->join('academy_modules', 'academy_lessons.module_id', '=', 'academy_modules.id')
            ->join('academy_courses', 'academy_modules.course_id', '=', 'academy_courses.id')
            ->where('academy_lessons.id', $id)
            ->where('academy_lessons.is_published', true)
            ->select('academy_lessons.*', 'academy_modules.name as module_name', 'academy_modules.course_id')
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Lección no encontrada'], 404);
        }

        $enrollment = DB::table('academy_course_student')
            ->where('course_id', $lesson->course_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'No inscrito en este curso'], 403);
        }

        $lesson->completed = DB::table('academy_course_student_lesson')
            ->where('course_student_id', $enrollment->id)
            ->where('lesson_id', $lesson->id)
            ->exists();

        $prevLesson = DB::table('academy_lessons')
            ->join('academy_modules', 'academy_lessons.module_id', '=', 'academy_modules.id')
            ->where('academy_modules.course_id', $lesson->course_id)
            ->where('academy_lessons.id', '<', $id)
            ->where('academy_lessons.is_published', true)
            ->orderBy('academy_lessons.id', 'desc')
            ->select('academy_lessons.id', 'academy_lessons.name')
            ->first();

        $nextLesson = DB::table('academy_lessons')
            ->join('academy_modules', 'academy_lessons.module_id', '=', 'academy_modules.id')
            ->where('academy_modules.course_id', $lesson->course_id)
            ->where('academy_lessons.id', '>', $id)
            ->where('academy_lessons.is_published', true)
            ->orderBy('academy_lessons.id')
            ->select('academy_lessons.id', 'academy_lessons.name')
            ->first();

        return response()->json([
            'lesson' => $lesson,
            'prev_lesson' => $prevLesson,
            'next_lesson' => $nextLesson,
        ]);
    }

    public function complete(Request $request, $id)
    {
        $student = $this->getAuthenticatedStudent($request);
        if (!$student) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $lesson = DB::table('academy_lessons')
            ->join('academy_modules', 'academy_lessons.module_id', '=', 'academy_modules.id')
            ->where('academy_lessons.id', $id)
            ->select('academy_lessons.*', 'academy_modules.course_id')
            ->first();

        if (!$lesson) {
            return response()->json(['message' => 'Lección no encontrada'], 404);
        }

        $enrollment = DB::table('academy_course_student')
            ->where('course_id', $lesson->course_id)
            ->where('student_id', $student->id)
            ->first();

        if (!$enrollment) {
            return response()->json(['message' => 'No inscrito en este curso'], 403);
        }

        $alreadyCompleted = DB::table('academy_course_student_lesson')
            ->where('course_student_id', $enrollment->id)
            ->where('lesson_id', $id)
            ->exists();

        if (!$alreadyCompleted) {
            DB::table('academy_course_student_lesson')->insert([
                'course_student_id' => $enrollment->id,
                'lesson_id' => $id,
                'completed' => true,
                'completed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['message' => 'Lección completada']);
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
