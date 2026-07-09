<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyModuleController extends Controller
{
    public function index($courseId)
    {
        $course = DB::table('academy_courses')->find($courseId);
        if (!$course) {
            return response()->json(['message' => 'Curso no encontrado'], 404);
        }

        $modules = DB::table('academy_modules')
            ->where('course_id', $courseId)
            ->orderBy('order')
            ->get()
            ->map(function ($module) {
                $module->lessons_count = DB::table('academy_lessons')
                    ->where('module_id', $module->id)
                    ->count();
                $module->exams = DB::table('academy_exams')
                    ->where('module_id', $module->id)
                    ->get();
                return $module;
            });

        return response()->json($modules);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:academy_courses,id',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
        ]);

        $validated['order'] = $validated['order'] ?? 0;

        $id = DB::table('academy_modules')->insertGetId($validated);

        return response()->json(DB::table('academy_modules')->find($id), 201);
    }

    public function show($id)
    {
        $module = DB::table('academy_modules')->find($id);
        if (!$module) {
            return response()->json(['message' => 'Módulo no encontrado'], 404);
        }

        $module->lessons = DB::table('academy_lessons')
            ->where('module_id', $id)
            ->orderBy('order')
            ->get();

        $module->exams = DB::table('academy_exams')
            ->where('module_id', $id)
            ->get();

        return response()->json($module);
    }

    public function update(Request $request, $id)
    {
        $module = DB::table('academy_modules')->find($id);
        if (!$module) {
            return response()->json(['message' => 'Módulo no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'order' => 'nullable|integer|min:0',
        ]);

        DB::table('academy_modules')->where('id', $id)->update($validated);

        return response()->json(DB::table('academy_modules')->find($id));
    }

    public function destroy($id)
    {
        $module = DB::table('academy_modules')->find($id);
        if (!$module) {
            return response()->json(['message' => 'Módulo no encontrado'], 404);
        }

        DB::table('academy_modules')->where('id', $id)->delete();

        return response()->json(['message' => 'Módulo eliminado']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:academy_modules,id',
            'modules.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->modules as $item) {
            DB::table('academy_modules')->where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }
}
