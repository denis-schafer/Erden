<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HairSalonDashboardController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $totalClients = DB::table('hairsalon_clients')->count();

        $todayIncome = DB::table('hairsalon_jobs')
            ->whereDate('created_at', now()->format('Y-m-d'))
            ->where('status', 'completed')
            ->sum('total');

        $todayJobs = DB::table('hairsalon_jobs')
            ->whereDate('created_at', now()->format('Y-m-d'))
            ->count();

        $periodIncome = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('status', 'completed')
            ->sum('total');

        $periodJobs = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->count();

        $periodAvg = $periodJobs > 0 ? $periodIncome / $periodJobs : 0;

        $recentJobs = DB::table('hairsalon_jobs as j')
            ->join('hairsalon_clients as c', 'j.client_id', '=', 'c.id')
            ->join('users as u', 'j.operator_id', '=', 'u.id')
            ->select('j.*', 'c.name as client_name', 'u.name as operator_name')
            ->orderBy('j.created_at', 'desc')
            ->limit(10)
            ->get();

        $jobsByDay = DB::table('hairsalon_jobs')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $byMethod = DB::table('hairsalon_jobs')
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

        return response()->json([
            'total_clients' => $totalClients,
            'today_jobs' => $todayJobs,
            'today_income' => $todayIncome,
            'period_jobs' => $periodJobs,
            'period_income' => $periodIncome,
            'period_avg' => round($periodAvg, 2),
            'recent_jobs' => $recentJobs,
            'jobs_by_day' => $jobsByDay,
            'by_method' => $byMethod,
            'top_services' => $topServices,
        ]);
    }
}
