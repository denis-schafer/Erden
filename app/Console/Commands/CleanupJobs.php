<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupJobs extends Command
{
    protected $signature = 'pos:cleanup-jobs';
    protected $description = 'Remove stale pending jobs from webhooks_jobs and print_jobs';

    public function handle(): int
    {
        $cutoff = now()->subHours(24);

        $webhookDeleted = DB::connection('mysql_parent')
            ->table('webhooks_jobs')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $printDeleted = DB::connection('mysql_parent')
            ->table('print_jobs')
            ->where('status', 'pending')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Cleaned up {$webhookDeleted} webhook jobs and {$printDeleted} print jobs older than 24h.");

        return self::SUCCESS;
    }
}
