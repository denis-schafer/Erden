<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use App\Events\HairSalon\HairSalonProductUpdated;
use App\Events\HairSalon\HairSalonStockUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HairSalonProductController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('hairsalon_products as p')
            ->leftJoin('hairsalon_service_categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoin('hairsalon_services as s', 'p.service_id', '=', 's.id')
            ->select('p.*', 'c.name as category_name', 's.name as service_name');

        if ($search = $request->get('search')) {
            $query->where('p.name', 'like', "%{$search}%");
        }

        if ($request->get('low_stock') === 'true') {
            $query->where('p.quantity', '<=', DB::raw('p.min_stock'));
        }

        $products = $query->orderBy('p.name')->paginate($request->get('per_page', 50));
        $services = DB::table('hairsalon_services')->orderBy('name')->get(['id', 'name', 'category_id']);
        $categories = DB::table('hairsalon_service_categories')->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'products' => $products,
            'services' => $services,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:hairsalon_service_categories,id',
            'service_id' => 'nullable|exists:hairsalon_services,id',
        ]);

        $id = DB::table('hairsalon_products')->insertGetId([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'quantity' => $validated['quantity'],
            'min_stock' => $validated['min_stock'],
            'price' => $validated['price'],
            'category_id' => $validated['category_id'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product = DB::table('hairsalon_products')->find($id);
        broadcast(new HairSalonProductUpdated($product, 'created'));

        return response()->json(['success' => true, 'product' => $product]);
    }

    public function update(Request $request, $id)
    {
        $product = DB::table('hairsalon_products')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'quantity' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
            'category_id' => 'nullable|exists:hairsalon_service_categories,id',
            'service_id' => 'nullable|exists:hairsalon_services,id',
        ]);

        DB::table('hairsalon_products')->where('id', $id)->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'quantity' => $validated['quantity'],
            'min_stock' => $validated['min_stock'],
            'price' => $validated['price'],
            'category_id' => $validated['category_id'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
            'updated_at' => now(),
        ]);

        $product = DB::table('hairsalon_products')->find($id);
        broadcast(new HairSalonProductUpdated($product, 'updated'));

        return response()->json(['success' => true, 'product' => $product]);
    }

    public function destroy($id)
    {
        $product = DB::table('hairsalon_products')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        DB::table('hairsalon_products')->where('id', $id)->delete();
        broadcast(new HairSalonProductUpdated(['id' => $id], 'deleted'));

        return response()->json(['success' => true, 'message' => 'Producto eliminado']);
    }

    public function adjustStock(Request $request, $id)
    {
        $product = DB::table('hairsalon_products')->find($id);
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $validated = $request->validate([
            'type' => 'required|string|in:in,out',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:200',
        ]);

        $operatorId = session('user.id');

        DB::table('hairsalon_products')->where('id', $id)->update([
            'quantity' => DB::raw(($validated['type'] === 'in' ? '+' : '-') . abs($validated['quantity'])),
            'updated_at' => now(),
        ]);

        $movementId = DB::table('hairsalon_stock_movements')->insertGetId([
            'product_id' => $id,
            'type' => $validated['type'],
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'] ?? null,
            'operator_id' => $operatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $movement = DB::table('hairsalon_stock_movements')->find($movementId);
        broadcast(new HairSalonStockUpdated($movement));

        $product = DB::table('hairsalon_products')->find($id);

        return response()->json(['success' => true, 'product' => $product, 'movement' => $movement]);
    }

    public function movements(Request $request, $id)
    {
        $movements = DB::table('hairsalon_stock_movements')
            ->where('product_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 50));

        return response()->json($movements);
    }
}
