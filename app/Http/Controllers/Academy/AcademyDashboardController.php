<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyDashboardController extends Controller
{
    public function index(Request $request)
    {
        $coursesCount = DB::table('academy_courses')->count();
        $studentsCount = DB::table('academy_students')->count();
        $enrollmentsCount = DB::table('academy_course_student')->count();
        $examsCount = DB::table('academy_exams')->count();

        $recentStudents = DB::table('academy_students')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $recentCourses = DB::table('academy_courses')
            ->where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $courseStats = DB::table('academy_course_student')
            ->select('course_id', DB::raw('count(*) as total'))
            ->groupBy('course_id')
            ->get()
            ->keyBy('course_id');

        $courses = DB::table('academy_courses')->get()->map(function ($course) use ($courseStats) {
            $course->student_count = $courseStats[$course->id]->total ?? 0;
            return $course;
        });

        return response()->json([
            'courses_count' => $coursesCount,
            'students_count' => $studentsCount,
            'enrollments_count' => $enrollmentsCount,
            'exams_count' => $examsCount,
            'recent_students' => $recentStudents,
            'recent_courses' => $recentCourses,
            'courses' => $courses,
        ]);
    }
}
