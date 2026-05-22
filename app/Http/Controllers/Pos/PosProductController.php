<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Events\ProductUpdated;
use App\Events\ProductDisabled;
use App\Events\ProductEnabled;
use App\Events\ProductReordered;
use App\Packages\Pos\Helpers\TestModeHelper;

class PosProductController extends Controller
{
    public function index()
    {
        $query = DB::table('products')
            ->select('products.*', 'categories.name as category_name')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->orderBy('products.order')
            ->orderBy('products.name');

        TestModeHelper::applyFilter($query, 'products');
        $products = $query->get();
        
        return response()->json($products);
    }

    public function byCategory($categoryId)
    {
        $query = DB::table('products')
            ->where('category_id', $categoryId)
            ->where('enable', true)
            ->orderBy('order')
            ->orderBy('name');

        TestModeHelper::applyFilter($query, 'products');
        $products = $query->get();
        
        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_description' => 'nullable|string|max:200',
            'long_description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'enable' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $productData = TestModeHelper::setTestFlag($validated + ['created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('products')->insertGetId($productData);

        $product = DB::table('products')->find($id);
        event(new ProductUpdated((array) $product));

        return response()->json(['id' => $id, 'message' => 'Producto creado']);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'short_description' => 'nullable|string|max:200',
            'long_description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
            'enable' => 'boolean',
            'order' => 'integer|min:0',
        ]);

        $updateData = TestModeHelper::setTestFlag($validated + ['updated_at' => now()]);
        DB::table('products')->where('id', $id)->update($updateData);

        $product = DB::table('products')->find($id);
        event(new ProductUpdated((array) $product));

        return response()->json(['message' => 'Producto actualizado']);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'enable' => 'required|boolean',
        ]);

        $updateData = TestModeHelper::setTestFlag($validated + ['updated_at' => now()]);
        DB::table('products')->where('id', $id)->update($updateData);

        $product = DB::table('products')->where('id', $id)->first();
        event(new ProductUpdated((array) $product));

        return response()->json(['message' => 'Estado actualizado']);
    }

    public function destroy($id)
    {
        $product = DB::table('products')->find($id);
        if ($product) {
            event(new ProductUpdated((array) $product));
        }
        DB::table('products')->where('id', $id)->delete();
        return response()->json(['message' => 'Producto eliminado']);
    }

    public function toggleStatus($id)
    {
        $product = DB::table('products')->find($id);
        
        if (!$product) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $newStatus = !$product->enable;
        
        $updateData = TestModeHelper::setTestFlag([
            'enable' => $newStatus,
            'updated_at' => now(),
        ]);
        DB::table('products')->where('id', $id)->update($updateData);

        if (!$newStatus) {
            event(new ProductDisabled($id));
            // Log: producto deshabilitado
            \App\Services\PosLogService::writeLog(
                'productos',
                'product_disabled',
                'Producto "' . $product->name . '" (ID: ' . $id . ') deshabilitado',
                auth()->id()
            );
        } else {
            event(new ProductEnabled($id));
            // Log: producto habilitado
            \App\Services\PosLogService::writeLog(
                'productos',
                'product_enabled',
                'Producto "' . $product->name . '" (ID: ' . $id . ') habilitado',
                auth()->id()
            );
        }

        return response()->json(['message' => 'Estado actualizado']);
    }

    public function reorder(Request $request)
    {
        $orderData = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|integer|exists:products,id',
            'orders.*.order' => 'required|integer'
        ]);
        
        foreach ($orderData['orders'] as $item) {
            $reorderData = TestModeHelper::setTestFlag(['order' => $item['order'], 'updated_at' => now()]);
            DB::table('products')
                ->where('id', $item['id'])
                ->update($reorderData);
        }
        
        event(new ProductReordered($orderData['orders']));
        
        return response()->json(['message' => 'Orden actualizado']);
    }
}
