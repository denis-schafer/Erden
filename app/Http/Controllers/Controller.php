<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Services\SyncService;

abstract class Controller
{
    protected function queueSync(string $entityType, string $action, ?object $record, array $foreignKeys = []): void
    {
        if (!$record || empty($record->sync_id)) {
            return;
        }

        try {
            $webhookCode = DB::table('configs')->where('name', 'webhook_code')->value('value');
            if (!$webhookCode) {
                return;
            }

            $data = (array) $record;

            foreach ($foreignKeys as $fkField => $fkTable) {
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
                $entityType,
                $record->id ?? null,
                $action,
                $data,
                $record->sync_id
            );
        } catch (\Exception $e) {
            // Silently fail - sync will be retried
        }
    }
}
