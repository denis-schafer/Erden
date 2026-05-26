<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Events\CategoryDisabled;
use App\Events\CategoryEnabled;
use App\Events\CategoryUpdated;
use App\Packages\Pos\Helpers\TestModeHelper;

class PosCategoryController extends Controller
{
    public function index()
    {
        $query = DB::table('categories')
            ->whereNull('deleted_at')
            ->orderBy('name');

        TestModeHelper::applyFilter($query, 'categories');
        $categories = $query->get();
        
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'default' => 'boolean',
            'enable' => 'boolean',
        ]);

        $categoryData = TestModeHelper::setTestFlag($validated + ['created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('categories')->insertGetId($categoryData);

        if (!empty($validated['default'])) {
            DB::table('categories')->where('id', '!=', $id)->whereNull('deleted_at')->update(['default' => 0]);
        }

        event(new CategoryUpdated($id));

        return response()->json(['message' => 'Categoría creada']);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'default' => 'boolean',
            'enable' => 'boolean',
        ]);

        $updateData = TestModeHelper::setTestFlag($validated + ['updated_at' => now()]);
        DB::table('categories')->where('id', $id)->update($updateData);

        if (!empty($validated['default'])) {
            DB::table('categories')->where('id', '!=', $id)->whereNull('deleted_at')->update(['default' => 0]);
        }

        event(new CategoryUpdated($id));

        return response()->json(['message' => 'Categoría actualizada']);
    }

    public function destroy($id)
    {
        event(new CategoryUpdated($id));
        DB::table('categories')->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Categoría eliminada']);
    }

    public function toggleStatus($id)
    {
        $category = DB::table('categories')->find($id);
        
        if (!$category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $newStatus = !$category->enable;
        
        $statusUpdateData = TestModeHelper::setTestFlag([
            'enable' => $newStatus,
            'updated_at' => now(),
        ]);
        DB::table('categories')->where('id', $id)->update($statusUpdateData);

        if (!$newStatus) {
            event(new CategoryDisabled($id));
        } else {
            event(new CategoryEnabled($id));
        }

        return response()->json(['message' => 'Estado actualizado']);
    }
}
