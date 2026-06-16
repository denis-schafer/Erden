<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotaDailySummaryController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->get('year', date('Y'));

        $query = DB::table('daily_charges')
            ->whereYear('created_at', $year);

        $totalAmount = (clone $query)->sum('amount');
        $totalCount = (clone $query)->count();

        $cashTotal = (clone $query)->where('payment_method', 'cash')->sum('amount');
        $digitalTotal = (clone $query)->where('payment_method', 'digital')->sum('amount');

        $hasRendered = Schema::hasColumn('daily_charges', 'rendered');

        $renderedAmount = 0;
        $pendingRenderedAmount = 0;

        if ($hasRendered) {
            $renderedAmount = (clone $query)->where('rendered', true)->sum('amount');
            $pendingRenderedAmount = $totalAmount - $renderedAmount;
        }

        return response()->json([
            'year' => (int) $year,
            'total_amount' => $totalAmount,
            'total_count' => $totalCount,
            'cash_total' => $cashTotal,
            'digital_total' => $digitalTotal,
            'rendered_amount' => $renderedAmount,
            'pending_rendered_amount' => $pendingRenderedAmount,
        ]);
    }
}
