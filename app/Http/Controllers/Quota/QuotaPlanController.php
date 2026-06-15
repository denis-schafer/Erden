<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotaPlanController extends Controller
{
    public function index()
    {
        $plans = DB::table('quota_plans')->orderBy('created_at', 'desc')->get();

        foreach ($plans as $plan) {
            $plan->partners_count = DB::table('quota_partner_config')
                ->where('quota_plan_id', $plan->id)
                ->count();
            $plan->quotas_count = DB::table('quotas')
                ->where('quota_plan_id', $plan->id)
                ->count();
            $plan->paid_count = DB::table('quotas')
                ->where('quota_plan_id', $plan->id)
                ->where('status', 'paid')
                ->count();
        }

        return response()->json($plans);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|string|in:monthly,bimonthly,quarterly,four_monthly,biannual',
            'amount' => 'required|numeric|min:0',
            'pool_fee_amount' => 'required|numeric|min:0',
            'pool_fee_count' => 'required|integer|min:1|max:12',
        ]);

        $installmentCounts = [
            'monthly' => 12,
            'bimonthly' => 6,
            'quarterly' => 4,
            'four_monthly' => 3,
            'biannual' => 2,
        ];

        $planId = DB::table('quota_plans')->insertGetId([
            'name' => $validated['name'],
            'frequency' => $validated['frequency'],
            'installment_count' => $installmentCounts[$validated['frequency']],
            'amount' => $validated['amount'],
            'pool_fee_amount' => $validated['pool_fee_amount'],
            'pool_fee_count' => $validated['pool_fee_count'],
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Plan creado correctamente',
            'plan' => ['id' => $planId],
        ]);
    }

    public function update(Request $request, $id)
    {
        $plan = DB::table('quota_plans')->find($id);
        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|string|in:monthly,bimonthly,quarterly,four_monthly,biannual',
            'amount' => 'required|numeric|min:0',
            'pool_fee_amount' => 'required|numeric|min:0',
            'pool_fee_count' => 'required|integer|min:1|max:12',
            'is_active' => 'boolean',
        ]);

        $installmentCounts = [
            'monthly' => 12,
            'bimonthly' => 6,
            'quarterly' => 4,
            'four_monthly' => 3,
            'biannual' => 2,
        ];

        DB::table('quota_plans')->where('id', $id)->update([
            'name' => $validated['name'],
            'frequency' => $validated['frequency'],
            'installment_count' => $installmentCounts[$validated['frequency']],
            'amount' => $validated['amount'],
            'pool_fee_amount' => $validated['pool_fee_amount'],
            'pool_fee_count' => $validated['pool_fee_count'],
            'is_active' => $validated['is_active'] ?? true,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Plan actualizado']);
    }

    public function destroy($id)
    {
        $plan = DB::table('quota_plans')->find($id);
        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        $quotasCount = DB::table('quotas')->where('quota_plan_id', $id)->count();
        if ($quotasCount > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el plan porque tiene cuotas generadas. Desactívelo en su lugar.',
            ], 400);
        }

        DB::table('quota_plans')->where('id', $id)->delete();

        return response()->json(['success' => true, 'message' => 'Plan eliminado']);
    }

    public function generate(Request $request, $id)
    {
        $plan = DB::table('quota_plans')->find($id);
        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        $year = $request->integer('year', now()->year);

        if ($year < 2020 || $year > 2099) {
            return response()->json(['message' => 'Año inválido'], 400);
        }

        $partners = DB::table('users')
            ->where('role_id', 4)
            ->when(Schema::hasColumn('users', 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))
            ->where('enable', true)
            ->get();

        $generated = 0;
        $skipped = 0;
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
                    ->where('quota_plan_id', $id)
                    ->first();

                if ($config && $config->is_exempt) {
                    continue;
                }

                $amount = $config->amount ?? $plan->amount;
                $poolFeeAmount = $config->pool_fee_amount ?? $plan->pool_fee_amount;
                $poolFeeCount = $config->pool_fee_count ?? $plan->pool_fee_count;

                if ($amount <= 0) continue;

                $existingCount = DB::table('quotas')
                    ->where('partner_id', $partner->id)
                    ->where('quota_plan_id', $id)
                    ->where('type', 'regular')
                    ->whereYear('due_date', $year)
                    ->count();

                if ($existingCount > 0) {
                    $skipped++;
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
                        'quota_plan_id' => $id,
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
                        $month = $poolMonths[($i - 1) % 4];
                        $y = $month >= 10 ? $year : $year + 1;
                        $dueDate = "{$y}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";

                        DB::table('quotas')->insert([
                            'partner_id' => $partner->id,
                            'quota_plan_id' => $id,
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
        }

        return response()->json([
            'success' => true,
            'generated' => $generated,
            'skipped' => $skipped,
            'partners' => count($partners),
            'year' => $year,
            'errors' => $errors,
            'message' => "{$generated} cuotas generadas, {$skipped} socios saltados (ya tenían cuotas para {$year})",
        ]);
    }

    public function generateStatus(Request $request)
    {
        return response()->json(['status' => 'idle']);
    }
}
