<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class QuotaGenerate extends Command
{
    protected $signature = 'quota:generate {plan_id : The ID of the quota plan} {--year= : Year to generate quotas for (default: current year)} {--company= : Company DB name} {--force : Regenerate quotas even if they exist}';
    protected $description = 'Generate quotas for all partners in a plan';

    public function handle(): int
    {
        $planId = $this->argument('plan_id');
        $companyDb = $this->option('company');
        $force = $this->option('force');
        $year = (int)($this->option('year') ?? now()->year);

        if ($year < 2020 || $year > 2099) {
            $this->error("Invalid year: {$year}");
            return 1;
        }

        if ($companyDb) {
            $this->info("Switching to company database: {$companyDb}");
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        $plan = DB::table('quota_plans')->find($planId);
        if (!$plan) {
            $this->error("Plan with ID {$planId} not found.");
            return 1;
        }

        $this->info("Plan: {$plan->name} - Year: {$year}");

        $existing = DB::table('quotas')->where('quota_plan_id', $planId)->whereYear('due_date', $year)->count();
        if ($existing > 0 && !$force) {
            $this->warn("{$existing} quotas already exist for this plan in {$year}. Use --force to regenerate.");
            return 1;
        }

        if ($existing > 0 && $force) {
            $this->warn("Deleting {$existing} existing quotas for year {$year}...");
            DB::table('quotas')->where('quota_plan_id', $planId)->whereYear('due_date', $year)->delete();
        }

        $partners = DB::table('users')
            ->where('role_id', 4)
            ->whereNull('deleted_at')
            ->where('enable', true)
            ->get();

        $this->info("Generating quotas for {$partners->count()} partners...");

        $bar = $this->output->createProgressBar($partners->count());
        $bar->start();

        $generated = 0;
        $exempted = 0;
        $errors = [];

        $monthsMap = [
            'monthly' => 1, 'bimonthly' => 2, 'quarterly' => 3,
            'four_monthly' => 4, 'biannual' => 6,
        ];
        $months = $monthsMap[$plan->frequency] ?? 1;

        foreach ($partners as $partner) {
            try {
                $config = DB::table('quota_partner_config')
                    ->where('partner_id', $partner->id)
                    ->where('quota_plan_id', $planId)
                    ->first();

                if ($config && $config->is_exempt) {
                    $exempted++;
                    $bar->advance();
                    continue;
                }

                $amount = $config->amount ?? $plan->amount;
                $poolFeeAmount = $config->pool_fee_amount ?? $plan->pool_fee_amount;
                $poolFeeCount = $config->pool_fee_count ?? $plan->pool_fee_count;

                if ($amount <= 0) {
                    $bar->advance();
                    continue;
                }

                for ($i = 1; $i <= $plan->installment_count; $i++) {
                    $month = ($i - 1) * $months + 1;
                    $y = $year;
                    if ($month > 12) {
                        $y += intdiv($month - 1, 12);
                        $month = (($month - 1) % 12) + 1;
                    }
                    $dueDate = "{$y}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";

                    DB::table('quotas')->insert([
                        'partner_id' => $partner->id,
                        'quota_plan_id' => $planId,
                        'type' => 'regular',
                        'installment_number' => $i,
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $generated++;
                }

                if ($poolFeeCount > 0) {
                    $poolMonths = [10, 11, 12, 1];
                    for ($i = 1; $i <= $poolFeeCount; $i++) {
                        $m = $poolMonths[($i - 1) % 4];
                        $y = $m >= 10 ? $year : $year + 1;
                        $dueDate = "{$y}-" . str_pad($m, 2, '0', STR_PAD_LEFT) . "-01";

                        DB::table('quotas')->insert([
                            'partner_id' => $partner->id,
                            'quota_plan_id' => $planId,
                            'type' => 'pool_fee',
                            'installment_number' => $i,
                            'amount' => $poolFeeAmount,
                            'due_date' => $dueDate,
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $generated++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = ['partner' => $partner->name, 'message' => $e->getMessage()];
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Generated: {$generated} quotas");
        $this->info("Exempted: {$exempted} partners");
        $this->info("Total partners processed: {$partners->count()}");

        if (!empty($errors)) {
            $this->warn('Errors:');
            foreach ($errors as $error) {
                $this->warn("  - {$error['partner']}: {$error['message']}");
            }
        }

        return 0;
    }
}
