<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use App\Events\HairSalon\HairSalonServiceUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HairSalonServiceController extends Controller
{
    public function index()
    {
        $categories = DB::table('hairsalon_service_categories')
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $services = DB::table('hairsalon_services as s')
            ->leftJoin('hairsalon_service_categories as c', 's.category_id', '=', 'c.id')
            ->select('s.*', 'c.name as category_name')
            ->orderBy('c.order')
            ->orderBy('s.name')
            ->get();

        $products = DB::table('hairsalon_products')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'categories' => $categories,
            'services' => $services,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_min' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:hairsalon_service_categories,id',
            'product_id' => 'nullable|exists:hairsalon_products,id',
            'is_active' => 'boolean',
        ]);

        $id = DB::table('hairsalon_services')->insertGetId([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'duration_min' => $validated['duration_min'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = DB::table('hairsalon_services')->find($id);
        broadcast(new HairSalonServiceUpdated($service, 'created'));

        return response()->json(['success' => true, 'service' => $service]);
    }

    public function update(Request $request, $id)
    {
        $service = DB::table('hairsalon_services')->find($id);
        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_min' => 'nullable|integer|min:0',
            'category_id' => 'nullable|exists:hairsalon_service_categories,id',
            'product_id' => 'nullable|exists:hairsalon_products,id',
            'is_active' => 'boolean',
        ]);

        DB::table('hairsalon_services')->where('id', $id)->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'duration_min' => $validated['duration_min'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'updated_at' => now(),
        ]);

        $service = DB::table('hairsalon_services')->find($id);
        broadcast(new HairSalonServiceUpdated($service, 'updated'));

        return response()->json(['success' => true, 'service' => $service]);
    }

    public function destroy($id)
    {
        $service = DB::table('hairsalon_services')->find($id);
        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado'], 404);
        }

        DB::table('hairsalon_services')->where('id', $id)->delete();
        broadcast(new HairSalonServiceUpdated(['id' => $id], 'deleted'));

        return response()->json(['success' => true, 'message' => 'Servicio eliminado']);
    }

    public function categories()
    {
        $categories = DB::table('hairsalon_service_categories')
            ->orderBy('order')
            ->get();

        return response()->json($categories);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'order' => 'integer|min:0',
        ]);

        $id = DB::table('hairsalon_service_categories')->insertGetId([
            'name' => $validated['name'],
            'order' => $validated['order'] ?? 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'category' => DB::table('hairsalon_service_categories')->find($id),
        ]);
    }

    public function updateCategory(Request $request, $id)
    {
        $category = DB::table('hairsalon_service_categories')->find($id);
        if (!$category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        DB::table('hairsalon_service_categories')->where('id', $id)->update([
            'name' => $validated['name'],
            'order' => $validated['order'] ?? $category->order,
            'is_active' => $validated['is_active'] ?? $category->is_active,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'category' => DB::table('hairsalon_service_categories')->find($id),
        ]);
    }

    public function destroyCategory($id)
    {
        $category = DB::table('hairsalon_service_categories')->find($id);
        if (!$category) {
            return response()->json(['message' => 'Categoría no encontrada'], 404);
        }

        DB::table('hairsalon_services')->where('category_id', $id)->update(['category_id' => null]);
        DB::table('hairsalon_service_categories')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Categoría eliminada']);
    }
}
