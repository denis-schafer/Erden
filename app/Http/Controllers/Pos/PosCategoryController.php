<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Events\CategoryDisabled;
use App\Events\CategoryEnabled;
use App\Events\CategoryUpdated;

class PosCategoryController extends Controller
{
    public function index()
    {
        $categories = DB::table('categories')
            ->orderBy('name')
            ->get();
        
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'default' => 'boolean',
            'enable' => 'boolean',
        ]);

        $id = DB::table('categories')->insertGetId($validated + ['created_at' => now(), 'updated_at' => now()]);

        if (!empty($validated['default'])) {
            DB::table('categories')->where('id', '!=', $id)->update(['default' => 0]);
        }

        event(new CategoryUpdated($id));

        return response()->json(['id' => $id, 'message' => 'Categoría creada']);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'default' => 'boolean',
            'enable' => 'boolean',
        ]);

        DB::table('categories')->where('id', $id)->update($validated + ['updated_at' => now()]);

        if (!empty($validated['default'])) {
            DB::table('categories')->where('id', '!=', $id)->update(['default' => 0]);
        }

        event(new CategoryUpdated($id));

        return response()->json(['message' => 'Categoría actualizada']);
    }

    public function destroy($id)
    {
        event(new CategoryUpdated($id));
        DB::table('categories')->where('id', $id)->delete();
        return response()->json(['message' => 'Categoría eliminada']);
    }

    public function toggleStatus($id)
    {
        $category = DB::table('categories')->find($id);
        
        if (!$category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $newStatus = !$category->enable;
        
        DB::table('categories')->where('id', $id)->update([
            'enable' => $newStatus,
            'updated_at' => now(),
        ]);

        if (!$newStatus) {
            event(new CategoryDisabled($id));
        } else {
            event(new CategoryEnabled($id));
        }

        return response()->json(['message' => 'Estado actualizado']);
    }
}
