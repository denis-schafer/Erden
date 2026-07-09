<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyEnrollmentController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('academy_course_student')
            ->join('academy_students', 'academy_course_student.student_id', '=', 'academy_students.id')
            ->join('academy_courses', 'academy_course_student.course_id', '=', 'academy_courses.id')
            ->select(
                'academy_course_student.*',
                'academy_students.first_name',
                'academy_students.last_name',
                'academy_students.dni',
                'academy_courses.name as course_name',
                'academy_courses.slug as course_slug'
            );

        if ($request->filled('course_id')) {
            $query->where('academy_course_student.course_id', $request->course_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('academy_students.first_name', 'like', "%{$search}%")
                  ->orWhere('academy_students.last_name', 'like', "%{$search}%")
                  ->orWhere('academy_students.dni', 'like', "%{$search}%");
            });
        }

        return response()->json($query->orderBy('academy_course_student.created_at', 'desc')->paginate(50));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:academy_courses,id',
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:academy_students,id',
        ]);

        $courseId = $validated['course_id'];
        $created = [];

        foreach ($validated['student_ids'] as $studentId) {
            $exists = DB::table('academy_course_student')
                ->where('course_id', $courseId)
                ->where('student_id', $studentId)
                ->exists();

            if (!$exists) {
                DB::table('academy_course_student')->insert([
                    'course_id' => $courseId,
                    'student_id' => $studentId,
                    'status' => 'active',
                    'enrolled_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created[] = $studentId;
            }
        }

        return response()->json([
            'message' => count($created) . ' alumno(s) inscrito(s)',
            'created' => $created,
        ], 201);
    }

    public function destroy($id)
    {
        $enrollment = DB::table('academy_course_student')->find($id);
        if (!$enrollment) {
            return response()->json(['message' => 'Inscripción no encontrada'], 404);
        }

        DB::table('academy_course_student')->where('id', $id)->delete();

        return response()->json(['message' => 'Inscripción eliminada']);
    }

    public function availableStudents($courseId)
    {
        $enrolledIds = DB::table('academy_course_student')
            ->where('course_id', $courseId)
            ->pluck('student_id');

        $students = DB::table('academy_students')
            ->where('is_active', true)
            ->whereNotIn('id', $enrolledIds)
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'dni']);

        return response()->json($students);
    }
}
