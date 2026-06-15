<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotaStatisticsController extends Controller
{
    public function summary(Request $request)
    {
        $partnerBase = DB::table('users')->where('role_id', 4);
        if (Schema::hasColumn('users', 'deleted_at')) {
            $partnerBase->whereNull('deleted_at');
        }
        $totalPartners = (clone $partnerBase)->count();
        $activePartners = (clone $partnerBase)->where('enable', true)->count();

        $totalRegularQuotas = DB::table('quotas')->where('type', 'regular')->count();
        $paidRegularQuotas = DB::table('quotas')->where('type', 'regular')->where('status', 'paid')->count();
        $totalPoolFees = DB::table('quotas')->where('type', 'pool_fee')->count();
        $paidPoolFees = DB::table('quotas')->where('type', 'pool_fee')->where('status', 'paid')->count();

        $totalCollected = DB::table('quotas')->where('status', 'paid')->sum('amount');
        $totalPending = DB::table('quotas')->where('status', 'pending')->sum('amount');

        $renderedCash = DB::table('quotas')
            ->where('status', 'paid')
            ->where('payment_method', 'cash')
            ->where('rendered', true)
            ->sum('amount');

        $renderedDigital = DB::table('quotas')
            ->where('status', 'paid')
            ->where('payment_method', 'digital')
            ->where('rendered', true)
            ->sum('amount');

        $pendingCash = DB::table('quotas')
            ->where('status', 'paid')
            ->where('payment_method', 'cash')
            ->where('rendered', false)
            ->sum('amount');

        $pendingDigital = DB::table('quotas')
            ->where('status', 'paid')
            ->where('payment_method', 'digital')
            ->where('rendered', false)
            ->sum('amount');

        $monthlyCollection = DB::table('quotas')
            ->select(
                DB::raw('MONTH(paid_at) as month'),
                DB::raw('YEAR(paid_at) as year'),
                DB::raw('SUM(amount) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->groupBy('year', 'month')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'partners' => [
                'total' => $totalPartners,
                'active' => $activePartners,
            ],
            'quotas' => [
                'regular' => ['total' => $totalRegularQuotas, 'paid' => $paidRegularQuotas, 'pending' => $totalRegularQuotas - $paidRegularQuotas],
                'pool_fees' => ['total' => $totalPoolFees, 'paid' => $paidPoolFees, 'pending' => $totalPoolFees - $paidPoolFees],
            ],
            'financial' => [
                'total_collected' => $totalCollected,
                'total_pending' => $totalPending,
            ],
            'rendering' => [
                'rendered_cash' => $renderedCash,
                'rendered_digital' => $renderedDigital,
                'pending_cash' => $pendingCash,
                'pending_digital' => $pendingDigital,
                'cash_in_hand' => $pendingCash,
                'digital_in_hand' => $pendingDigital,
            ],
            'monthly_collection' => $monthlyCollection,
        ]);
    }

    public function export(Request $request)
    {
        $partners = DB::table('users')
            ->where('role_id', 4)
            ->when(Schema::hasColumn('users', 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))
            ->get();

        $data = [];
        foreach ($partners as $partner) {
            $totalQuotas = DB::table('quotas')->where('partner_id', $partner->id)->count();
            $paidQuotas = DB::table('quotas')->where('partner_id', $partner->id)->where('status', 'paid')->count();
            $pendingQuotas = DB::table('quotas')->where('partner_id', $partner->id)->where('status', 'pending')->count();
            $totalAmount = DB::table('quotas')->where('partner_id', $partner->id)->sum('amount');
            $paidAmount = DB::table('quotas')->where('partner_id', $partner->id)->where('status', 'paid')->sum('amount');

            $data[] = [
                'dni' => $partner->dni,
                'nombre' => $partner->first_name,
                'apellido' => $partner->last_name,
                'telefono' => $partner->phone,
                'cuotas_totales' => $totalQuotas,
                'cuotas_pagadas' => $paidQuotas,
                'cuotas_pendientes' => $pendingQuotas,
                'monto_total' => $totalAmount,
                'monto_pagado' => $paidAmount,
                'monto_deuda' => $totalAmount - $paidAmount,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'filename' => 'estado_cuotas_' . date('Y-m-d') . '.csv',
        ]);
    }

    public function cashierBalance()
    {
        $controller = app(QuotaPaymentController::class);
        return $controller->cashierBalance(request());
    }
}
