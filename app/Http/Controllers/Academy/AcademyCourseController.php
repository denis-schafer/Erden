<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademyCourseController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('academy_courses');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate(50);

        $courses->getCollection()->transform(function ($course) {
            $course->modules_count = DB::table('academy_modules')->where('course_id', $course->id)->count();
            $course->students_count = DB::table('academy_course_student')->where('course_id', $course->id)->count();
            return $course;
        });

        return response()->json($courses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:500',
            'level' => 'nullable|string|max:50',
            'is_published' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        $validated['is_published'] = $validated['is_published'] ?? false;
        $validated['level'] = $validated['level'] ?? 'beginner';

        $id = DB::table('academy_courses')->insertGetId($validated);

        return response()->json(DB::table('academy_courses')->find($id), 201);
    }

    public function show($id)
    {
        $course = DB::table('academy_courses')->find($id);
        if (!$course) {
            return response()->json(['message' => 'Curso no encontrado'], 404);
        }

        $course->modules = DB::table('academy_modules')
            ->where('course_id', $id)
            ->orderBy('order')
            ->get()
            ->map(function ($module) {
                $module->lessons = DB::table('academy_lessons')
                    ->where('module_id', $module->id)
                    ->orderBy('order')
                    ->get();
                $module->lessons_count = $module->lessons->count();
                return $module;
            });

        $course->modules_count = $course->modules->count();
        $course->students_count = DB::table('academy_course_student')->where('course_id', $id)->count();
        $course->exams = DB::table('academy_exams')->where('course_id', $id)->get();

        return response()->json($course);
    }

    public function update(Request $request, $id)
    {
        $course = DB::table('academy_courses')->find($id);
        if (!$course) {
            return response()->json(['message' => 'Curso no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|string|max:500',
            'level' => 'nullable|string|max:50',
            'is_published' => 'boolean',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(5);
        }

        DB::table('academy_courses')->where('id', $id)->update($validated);

        return response()->json(DB::table('academy_courses')->find($id));
    }

    public function destroy($id)
    {
        $course = DB::table('academy_courses')->find($id);
        if (!$course) {
            return response()->json(['message' => 'Curso no encontrado'], 404);
        }

        DB::table('academy_courses')->where('id', $id)->delete();

        return response()->json(['message' => 'Curso eliminado']);
    }
}
