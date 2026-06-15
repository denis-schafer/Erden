<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotaDashboardController extends Controller
{
    public function index(Request $request)
    {
        $totalPartners = DB::table('users')->where('role_id', 4)->when(Schema::hasColumn('users', 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))->count();

        $totalQuotas = DB::table('quotas')->count();
        $paidQuotas = DB::table('quotas')->where('status', 'paid')->count();
        $pendingQuotas = DB::table('quotas')->where('status', 'pending')->count();

        $totalCollected = DB::table('quotas')->where('status', 'paid')->sum('amount');
        $totalRendered = DB::table('quotas')->where('status', 'paid')->where('rendered', true)->sum('amount');
        $pendingRendered = $totalCollected - $totalRendered;

        $recentPayments = DB::table('quota_payments')
            ->join('users', 'quota_payments.partner_id', '=', 'users.id')
            ->select('quota_payments.*', 'users.name as partner_name', 'users.dni')
            ->orderBy('quota_payments.paid_at', 'desc')
            ->limit(10)
            ->get();

        $pendingByMethod = DB::table('quotas')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->where('status', 'paid')
            ->where('rendered', false)
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

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

        $paymentByMethod = DB::table('quotas')
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->where('status', 'paid')
            ->whereNotNull('payment_method')
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'total_partners' => $totalPartners,
            'total_quotas' => $totalQuotas,
            'paid_quotas' => $paidQuotas,
            'pending_quotas' => $pendingQuotas,
            'payment_rate' => $totalQuotas > 0 ? round(($paidQuotas / $totalQuotas) * 100, 1) : 0,
            'total_collected' => $totalCollected,
            'total_rendered' => $totalRendered,
            'pending_rendered' => $pendingRendered,
            'pending_cash' => $pendingByMethod['cash']->total ?? 0,
            'pending_digital' => $pendingByMethod['digital']->total ?? 0,
            'recent_payments' => $recentPayments,
            'monthly_collection' => $monthlyCollection,
            'payment_by_method' => $paymentByMethod,
        ]);
    }
}
