<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class SyncPush extends Command
{
    protected $signature = 'sync:push';
    protected $description = 'Sends queued local changes to the remote VPS server';

    public function handle(): int
    {
        $queueDir = storage_path('sync/queue');
        if (!is_dir($queueDir)) {
            return 0;
        }

        $files = glob($queueDir . '/*.json');
        if (empty($files)) {
            return 0;
        }

        natsort($files);
        $allItems = [];

        foreach ($files as $file) {
            $content = @file_get_contents($file);
            if ($content === false) continue;

            $item = json_decode($content, true);
            if (!$item || empty($item['webhook_code']) || empty($item['entity_type'])) {
                @unlink($file);
                continue;
            }

            $allItems[] = $item;
        }

        if (empty($allItems)) {
            return 0;
        }

        $grouped = collect($allItems)->groupBy('webhook_code');

        foreach ($grouped as $webhookCode => $items) {
            $companyDb = $this->resolveCompanyDb($webhookCode);
            if (!$companyDb) {
                $this->warn(sprintf('[%s] No company DB found for webhook_code, skipping', $webhookCode));
                continue;
            }

            $this->switchToDb($companyDb);

            $remoteUrl = $this->getConfig('remote_url');
            $remoteKey = $this->getConfig('remote_key');

            if (empty($remoteUrl) || empty($remoteKey)) {
                $this->warn(sprintf('[%s] remote_url or remote_key not configured in company DB', $webhookCode));
                continue;
            }

            $this->info(sprintf('[%s] Pushing %d items to %s', $webhookCode, $items->count(), $remoteUrl));

            $chunks = array_chunk($items->toArray(), 100);
            $allSuccess = true;

            foreach ($chunks as $chunk) {
                try {
                    $response = Http::timeout(30)
                        ->withHeaders([
                            'X-Print-Agent-Key' => $remoteKey,
                            'Content-Type' => 'application/json',
                        ])
                        ->post(rtrim($remoteUrl, '/') . '/pos/sync/push', [
                            'items' => $chunk,
                        ]);

                    if ($response->successful()) {
                        $this->info(sprintf('[%s] Synced %d items', $webhookCode, count($chunk)));
                    } else {
                        $this->warn(sprintf('[%s] Server responded %d: %s', $webhookCode, $response->status(), $response->body()));
                        $allSuccess = false;
                        break;
                    }
                } catch (\Exception $e) {
                    $this->warn(sprintf('[%s] Connection error: %s', $webhookCode, $e->getMessage()));
                    $allSuccess = false;
                    break;
                }
            }

            if ($allSuccess) {
                foreach ($items as $item) {
                    $pattern = sprintf('%s/*_%s_%s_%s.json',
                        $queueDir,
                        preg_quote($item['webhook_code'], '/'),
                        preg_quote($item['entity_type'], '/'),
                        preg_quote((string)$item['entity_id'], '/')
                    );
                    foreach (glob($pattern) as $f) {
                        @unlink($f);
                    }
                }
                $this->info(sprintf('[%s] Queue files cleaned', $webhookCode));
            }
        }

        return 0;
    }

    private function resolveCompanyDb(string $webhookCode): ?string
    {
        try {
            $company = DB::connection('mysql_parent')
                ->table('companies')
                ->where('webhook_code', $webhookCode)
                ->first(['db']);

            return $company?->db;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function switchToDb(string $dbName): void
    {
        Config::set('database.connections.mysql.database', $dbName);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function getConfig(string $name): string
    {
        try {
            $config = DB::table('configs')->where('name', $name)->first();
            return $config->value ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
