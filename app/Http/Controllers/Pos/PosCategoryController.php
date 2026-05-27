<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

        $syncId = Str::uuid()->toString();
        $categoryData = TestModeHelper::setTestFlag($validated + ['sync_id' => $syncId, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('categories')->insertGetId($categoryData);

        if (!empty($validated['default'])) {
            DB::table('categories')->where('id', '!=', $id)->whereNull('deleted_at')->update(['default' => 0]);
        }

        event(new CategoryUpdated($id));

        $category = DB::table('categories')->find($id);
        $this->queueSync('categories', 'created', $category);

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

        $category = DB::table('categories')->find($id);
        $this->queueSync('categories', 'updated', $category);

        return response()->json(['message' => 'Categoría actualizada']);
    }

    public function destroy($id)
    {
        $category = DB::table('categories')->find($id);
        event(new CategoryUpdated($id));
        DB::table('categories')->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
        if ($category) {
            $category->deleted_at = now();
            $category->updated_at = now();
            $this->queueSync('categories', 'deleted', $category);
        }
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

        $category = DB::table('categories')->find($id);
        $this->queueSync('categories', 'updated', $category);

        return response()->json(['message' => 'Estado actualizado']);
    }
}
