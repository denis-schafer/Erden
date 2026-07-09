<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademyLessonController extends Controller
{
    public function index($moduleId)
    {
        $module = DB::table('academy_modules')->find($moduleId);
        if (!$module) {
            return response()->json(['message' => 'Módulo no encontrado'], 404);
        }

        $lessons = DB::table('academy_lessons')
            ->where('module_id', $moduleId)
            ->orderBy('order')
            ->get();

        return response()->json($lessons);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => 'required|exists:academy_modules,id',
            'name' => 'required|string|max:200',
            'content' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            'order' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);

        $validated['order'] = $validated['order'] ?? 0;
        $validated['is_published'] = $validated['is_published'] ?? true;

        $id = DB::table('academy_lessons')->insertGetId($validated);

        return response()->json(DB::table('academy_lessons')->find($id), 201);
    }

    public function show($id)
    {
        $lesson = DB::table('academy_lessons')->find($id);
        if (!$lesson) {
            return response()->json(['message' => 'Lección no encontrada'], 404);
        }

        return response()->json($lesson);
    }

    public function update(Request $request, $id)
    {
        $lesson = DB::table('academy_lessons')->find($id);
        if (!$lesson) {
            return response()->json(['message' => 'Lección no encontrada'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:200',
            'content' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            'order' => 'nullable|integer|min:0',
            'is_published' => 'boolean',
        ]);

        DB::table('academy_lessons')->where('id', $id)->update($validated);

        return response()->json(DB::table('academy_lessons')->find($id));
    }

    public function destroy($id)
    {
        $lesson = DB::table('academy_lessons')->find($id);
        if (!$lesson) {
            return response()->json(['message' => 'Lección no encontrada'], 404);
        }

        DB::table('academy_lessons')->where('id', $id)->delete();

        return response()->json(['message' => 'Lección eliminada']);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'lessons' => 'required|array',
            'lessons.*.id' => 'required|exists:academy_lessons,id',
            'lessons.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->lessons as $item) {
            DB::table('academy_lessons')->where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return response()->json(['message' => 'Orden actualizado']);
    }
}
