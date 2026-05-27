<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Services\SyncService;

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
            $existing = DB::table('categories')->where('name', $data['name'] ?? '')->first();
            if ($existing) {
                $data['id'] = $existing->id;
                DB::table('categories')->where('id', $existing->id)->update($data);
            } else {
                DB::table('categories')->insert($data);
            }
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
            $existing = DB::table('products')->where('name', $data['name'] ?? '')->first();
            if ($existing) {
                $data['id'] = $existing->id;
                DB::table('products')->where('id', $existing->id)->update($data);
            } else {
                DB::table('products')->insert($data);
            }
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
            $existing = DB::table('users')->where('username', $data['username'] ?? '')->first();
            if ($existing) {
                $data['id'] = $existing->id;
                DB::table('users')->where('id', $existing->id)->update($data);
            } else {
                DB::table('users')->insert($data);
            }
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
            $existing = DB::table('status_orders')->where('name', $data['name'] ?? '')->first();
            if ($existing) {
                $data['id'] = $existing->id;
                DB::table('status_orders')->where('id', $existing->id)->update($data);
            } else {
                DB::table('status_orders')->insert($data);
            }
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

    public function backfill(Request $request)
    {
        $status = DB::table('configs')->where('name', 'sync_backfill_status')->value('value');

        if ($request->boolean('force')) {
            DB::table('categories')->update(['sync_id' => null]);
            DB::table('products')->update(['sync_id' => null]);
            DB::table('users')->update(['sync_id' => null]);
            DB::table('status_orders')->update(['sync_id' => null]);
            DB::table('orders')->update(['sync_id' => null]);

            $queueDir = storage_path('sync/queue');
            if (is_dir($queueDir)) {
                $files = glob($queueDir . '/*.json');
                foreach ($files as $file) {
                    @unlink($file);
                }
            }
        } elseif ($status === 'running') {
            return response()->json(['message' => 'Ya hay un backfill en ejecución'], 409);
        }

        DB::table('configs')->updateOrInsert(
            ['name' => 'sync_backfill_status'],
            ['value' => 'running', 'updated_at' => now()]
        );

        try {
            $webhookCode = DB::table('configs')->where('name', 'webhook_code')->value('value');
            if (!$webhookCode) {
                DB::table('configs')->where('name', 'sync_backfill_status')->update(['value' => 'error:no_webhook_code']);
                return response()->json(['message' => 'No hay webhook_code configurado'], 400);
            }

            $totalQueued = 0;

            // Process entities in FK dependency order
            $entityOrder = [
                'categories' => [],
                'status_orders' => [],
                'users' => [],
                'products' => ['category_id' => 'categories'],
                'orders' => ['operator_id' => 'users', 'status_id' => 'status_orders'],
            ];

            foreach ($entityOrder as $entity => $fkMap) {
                $records = DB::table($entity)->whereNull('sync_id')->get();
                $count = $records->count();
                $processed = 0;

                foreach ($records as $record) {
                    $record->sync_id = Str::uuid()->toString();

                    DB::table($entity)->where('id', $record->id)->update(['sync_id' => $record->sync_id]);

                    $data = (array) $record;
                    foreach ($fkMap as $fkField => $fkTable) {
                        $fkValue = $data[$fkField] ?? null;
                        $syncIdField = preg_replace('/_id$/', '_sync_id', $fkField);
                        if ($fkValue) {
                            $fkRecord = DB::table($fkTable)->find($fkValue);
                            $data[$syncIdField] = $fkRecord->sync_id ?? null;
                        } else {
                            $data[$syncIdField] = null;
                        }
                    }

                    SyncService::queueChange(
                        $webhookCode,
                        $entity,
                        $record->id,
                        'created',
                        $data,
                        $record->sync_id
                    );

                    $processed++;
                    $totalQueued++;
                }

                DB::table('configs')->where('name', 'sync_backfill_status')->update([
                    'value' => json_encode([
                        'status' => 'running',
                        'entity' => $entity,
                        'processed' => $processed,
                        'total' => $count,
                        'queued' => $totalQueued,
                    ]),
                    'updated_at' => now(),
                ]);
            }

            DB::table('configs')->where('name', 'sync_backfill_status')->update([
                'value' => json_encode([
                    'status' => 'completed',
                    'queued' => $totalQueued,
                ]),
                'updated_at' => now(),
            ]);

            return response()->json(['status' => 'completed', 'queued' => $totalQueued]);
        } catch (\Exception $e) {
            DB::table('configs')->where('name', 'sync_backfill_status')->update([
                'value' => 'error:' . $e->getMessage(),
                'updated_at' => now(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function backfillStatus()
    {
        $status = DB::table('configs')->where('name', 'sync_backfill_status')->value('value');
        if (!$status) {
            return response()->json(['status' => null]);
        }

        $decoded = json_decode($status, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return response()->json($decoded);
        }

        return response()->json(['status' => $status]);
    }
}
