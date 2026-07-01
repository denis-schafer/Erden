<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HairSalonStatisticsController extends Controller
{
    public function summary(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $totalJobs = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        $totalIncome = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('status', 'completed')
            ->sum('total');

        $avgTicket = $totalJobs > 0 ? $totalIncome / $totalJobs : 0;

        $byPaymentMethod = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy('payment_method')
            ->get();

        $topServices = DB::table('hairsalon_job_services as js')
            ->join('hairsalon_services as s', 'js.service_id', '=', 's.id')
            ->join('hairsalon_jobs as j', 'js.job_id', '=', 'j.id')
            ->whereDate('j.created_at', '>=', $startDate)
            ->whereDate('j.created_at', '<=', $endDate)
            ->select('s.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(js.price) as total'))
            ->groupBy('s.id', 's.name')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        $jobsByDay = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $byOperator = DB::table('hairsalon_jobs as j')
            ->join('users as u', 'j.operator_id', '=', 'u.id')
            ->whereDate('j.created_at', '>=', $startDate)
            ->whereDate('j.created_at', '<=', $endDate)
            ->select('u.name', DB::raw('COUNT(*) as count'), DB::raw('SUM(j.total) as total'))
            ->groupBy('u.id', 'u.name')
            ->orderBy('total', 'desc')
            ->get();

        return response()->json([
            'total_jobs' => $totalJobs,
            'total_income' => $totalIncome,
            'avg_ticket' => round($avgTicket, 2),
            'by_payment_method' => $byPaymentMethod,
            'top_services' => $topServices,
            'jobs_by_day' => $jobsByDay,
            'by_operator' => $byOperator,
        ]);
    }

    public function salesByPeriod(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $sales = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select(DB::raw('created_at as date'), DB::raw('total as amount'))
            ->orderBy('created_at')
            ->get();

        return response()->json($sales);
    }

    public function servicesByInterval(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $intervals = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as interval_key"))
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')"))
            ->orderBy('interval_key')
            ->pluck('interval_key');

        $services = DB::table('hairsalon_job_services as js')
            ->join('hairsalon_services as s', 'js.service_id', '=', 's.id')
            ->join('hairsalon_jobs as j', 'js.job_id', '=', 'j.id')
            ->whereDate('j.created_at', '>=', $startDate)
            ->whereDate('j.created_at', '<=', $endDate)
            ->select('s.name',
                DB::raw("DATE_FORMAT(j.created_at, '%Y-%m-%d %H:00:00') as interval_key"),
                DB::raw('COUNT(*) as qty'))
            ->groupBy('s.id', 's.name', DB::raw("DATE_FORMAT(j.created_at, '%Y-%m-%d %H:00:00')"))
            ->orderBy('s.name')
            ->get()
            ->groupBy('name');

        $products = [];
        foreach ($services as $name => $items) {
            $data = [];
            foreach ($intervals as $i) {
                $found = $items->firstWhere('interval_key', $i);
                $data[] = $found ? (int) $found->qty : 0;
            }
            $products[] = [
                'name' => $name,
                'data' => $data,
            ];
        }

        return response()->json([
            'intervals' => $intervals,
            'products' => $products,
        ]);
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $jobs = DB::table('hairsalon_jobs as j')
            ->join('hairsalon_clients as c', 'j.client_id', '=', 'c.id')
            ->join('users as u', 'j.operator_id', '=', 'u.id')
            ->whereDate('j.created_at', '>=', $startDate)
            ->whereDate('j.created_at', '<=', $endDate)
            ->select('j.*', 'c.name as client_name', 'u.name as operator_name')
            ->orderBy('j.created_at', 'desc')
            ->get();

        return response()->json($jobs);
    }
}
