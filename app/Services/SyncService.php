<?php

namespace App\Services;

use Illuminate\Support\Str;

class SyncService
{
    public static function queueChange(string $webhookCode, string $entityType, $entityId, string $action, array $data, ?string $syncId = null): void
    {
        $queueDir = storage_path('sync/queue');
        if (!is_dir($queueDir)) {
            mkdir($queueDir, 0755, true);
        }

        $filename = sprintf('%s_%s_%s_%s.json',
            (int)(microtime(true) * 1000),
            $webhookCode,
            $entityType,
            $entityId
        );

        $dateFields = ['created_at', 'updated_at', 'deleted_at', 'date'];
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $ts = strtotime($data[$field]);
                if ($ts !== false) {
                    $data[$field] = date('Y-m-d H:i:s', $ts);
                }
            }
        }

        $payload = [
            'webhook_code' => $webhookCode,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'sync_id' => $syncId,
            'action' => $action,
            'data' => $data,
            'created_at' => now()->toIso8601String(),
        ];

        $tmp = $queueDir . '/' . $filename . '.tmp';
        file_put_contents($tmp, json_encode($payload, JSON_UNESCAPED_UNICODE));
        rename($tmp, $queueDir . '/' . $filename);
    }
}
