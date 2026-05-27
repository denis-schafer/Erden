<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class SyncController extends Controller
{
    public function push(Request $request)
    {
        $items = $request->input('items', []);

        if (empty($items)) {
            return response()->json(['synced' => 0]);
        }

        $orderWeight = [
            'status_orders' => 0,
            'categories' => 1,
            'users' => 2,
            'products' => 3,
            'orders' => 4,
        ];

        usort($items, function ($a, $b) use ($orderWeight) {
            $wa = $orderWeight[$a['entity_type']] ?? 99;
            $wb = $orderWeight[$b['entity_type']] ?? 99;
            return $wa <=> $wb;
        });

        DB::beginTransaction();
        try {
            foreach ($items as $item) {
                match ($item['entity_type']) {
                    'categories' => $this->upsertCategory($item['data']),
                    'products' => $this->upsertProduct($item['data']),
                    'users' => $this->upsertUser($item['data']),
                    'status_orders' => $this->upsertStatusOrder($item['data']),
                    'orders' => $this->upsertOrder($item['data']),
                    default => null,
                };
            }
            DB::commit();
            return response()->json(['synced' => count($items)]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function upsertCategory(array $data): void
    {
        $syncId = $data['sync_id'] ?? null;
        if (!$syncId) return;

        unset($data['id']);
        $existing = DB::table('categories')->where('sync_id', $syncId)->first();
        if ($existing) {
            DB::table('categories')->where('sync_id', $syncId)->update($data);
        } else {
            DB::table('categories')->insert($data);
        }
    }

    private function upsertProduct(array $data): void
    {
        $syncId = $data['sync_id'] ?? null;
        if (!$syncId) return;

        $this->resolveForeignKey($data, 'category_sync_id', 'categories', 'category_id');

        unset($data['id'], $data['category_sync_id']);
        $existing = DB::table('products')->where('sync_id', $syncId)->first();
        if ($existing) {
            DB::table('products')->where('sync_id', $syncId)->update($data);
        } else {
            DB::table('products')->insert($data);
        }
    }

    private function upsertUser(array $data): void
    {
        $syncId = $data['sync_id'] ?? null;
        if (!$syncId) return;

        unset($data['id']);
        $existing = DB::table('users')->where('sync_id', $syncId)->first();
        if ($existing) {
            DB::table('users')->where('sync_id', $syncId)->update($data);
        } else {
            DB::table('users')->insert($data);
        }
    }

    private function upsertStatusOrder(array $data): void
    {
        $syncId = $data['sync_id'] ?? null;
        if (!$syncId) return;

        unset($data['id']);
        $existing = DB::table('status_orders')->where('sync_id', $syncId)->first();
        if ($existing) {
            DB::table('status_orders')->where('sync_id', $syncId)->update($data);
        } else {
            DB::table('status_orders')->insert($data);
        }
    }

    private function upsertOrder(array $data): void
    {
        $syncId = $data['sync_id'] ?? null;
        if (!$syncId) return;

        $this->resolveForeignKey($data, 'operator_sync_id', 'users', 'operator_id');
        $this->resolveForeignKey($data, 'status_sync_id', 'status_orders', 'status_id');

        unset($data['id'], $data['operator_sync_id'], $data['status_sync_id']);
        $existing = DB::table('orders')->where('sync_id', $syncId)->first();
        if ($existing) {
            DB::table('orders')->where('sync_id', $syncId)->update($data);
        } else {
            DB::table('orders')->insert($data);
        }
    }

    private function resolveForeignKey(array &$data, string $syncIdField, string $table, string $idField): void
    {
        if (!empty($data[$syncIdField])) {
            $referenced = DB::table($table)->where('sync_id', $data[$syncIdField])->first();
            if ($referenced) {
                $data[$idField] = $referenced->id;
            }
        }
    }
}
