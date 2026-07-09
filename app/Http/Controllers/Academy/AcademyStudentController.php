<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AcademyStudentController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('academy_students');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('dni', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $students = $query->orderBy('created_at', 'desc')->paginate(50);

        $students->getCollection()->transform(function ($student) {
            $student->courses_count = DB::table('academy_course_student')
                ->where('student_id', $student->id)
                ->count();
            return $student;
        });

        return response()->json($students);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'dni' => 'required|string|max:20|unique:academy_students,dni',
            'email' => 'nullable|string|email|max:150',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['dni']);
        $validated['is_active'] = $validated['is_active'] ?? true;

        $id = DB::table('academy_students')->insertGetId($validated);

        return response()->json(DB::table('academy_students')->find($id), 201);
    }

    public function show($id)
    {
        $student = DB::table('academy_students')->find($id);
        if (!$student) {
            return response()->json(['message' => 'Alumno no encontrado'], 404);
        }

        $student->courses = DB::table('academy_course_student')
            ->join('academy_courses', 'academy_course_student.course_id', '=', 'academy_courses.id')
            ->where('academy_course_student.student_id', $id)
            ->select('academy_courses.*', 'academy_course_student.status as enrollment_status', 'academy_course_student.enrolled_at')
            ->get();

        return response()->json($student);
    }

    public function update(Request $request, $id)
    {
        $student = DB::table('academy_students')->find($id);
        if (!$student) {
            return response()->json(['message' => 'Alumno no encontrado'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'dni' => 'sometimes|string|max:20|unique:academy_students,dni,' . $id,
            'email' => 'nullable|string|email|max:150',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['dni']) && $validated['dni'] !== $student->dni) {
            $validated['password'] = Hash::make($validated['dni']);
        }

        DB::table('academy_students')->where('id', $id)->update($validated);

        return response()->json(DB::table('academy_students')->find($id));
    }

    public function destroy($id)
    {
        $student = DB::table('academy_students')->find($id);
        if (!$student) {
            return response()->json(['message' => 'Alumno no encontrado'], 404);
        }

        DB::table('academy_students')->where('id', $id)->delete();

        return response()->json(['message' => 'Alumno eliminado']);
    }

    public function resetPassword($id)
    {
        $student = DB::table('academy_students')->find($id);
        if (!$student) {
            return response()->json(['message' => 'Alumno no encontrado'], 404);
        }

        DB::table('academy_students')
            ->where('id', $id)
            ->update(['password' => Hash::make($student->dni)]);

        return response()->json(['message' => 'Contraseña restablecida al DNI']);
    }
}
