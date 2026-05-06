<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class PosDashboardController extends Controller
{
    private function getUserInfo(Request $request)
    {
        $userId = null;
        $userRole = 2;
        
        $token = $request->bearerToken();
        if ($token) {
            try {
                $decoded = json_decode(base64_decode($token), true);
                if (isset($decoded['sub'])) {
                    $userId = $decoded['sub'];
                } elseif (isset($decoded[0])) {
                    $parts = explode(':', base64_decode($token));
                    $userId = $parts[0] ?? null;
                }
                
                if ($userId) {
                    $user = DB::table('users')->find($userId);
                    if ($user) {
                        $userRole = $user->role_id ?? 2;
                    }
                }
            } catch (\Exception $e) {
            }
        }
        
        if (!$userId) {
            $userId = $request->session()->get('user.id');
            $userRole = $request->session()->get('user.role_id') ?? 2;
        }
        
        return ['user_id' => $userId, 'role_id' => $userRole];
    }

    public function stats(Request $request)
    {
        $userInfo = $this->getUserInfo($request);
        $userId = $userInfo['user_id'];
        $userRole = $userInfo['role_id'];
        
        $startDate = $request->input('start_date', now()->subDays(2)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        $userIdFilter = null;
        if ($userRole == 1 && $request->has('user_id')) {
            $userIdFilter = $request->user_id;
        } elseif ($userRole != 1) {
            $userIdFilter = $userId;
        }
        
        $baseQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        if ($userIdFilter) {
            $baseQuery->where('operator_id', $userIdFilter);
        }
        
        $totalOrders = $baseQuery->count();
        $totalSales = $baseQuery->sum('total');
        $avgOrder = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
        
        return response()->json([
            'total_orders' => $totalOrders,
            'total_sales' => round($totalSales, 2),
            'avg_order' => round($avgOrder, 2),
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ]
        ]);
    }

    public function byStatus(Request $request)
    {
        $userInfo = $this->getUserInfo($request);
        $userId = $userInfo['user_id'];
        $userRole = $userInfo['role_id'];
        
        $startDate = $request->input('start_date', now()->subDays(2)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        $userIdFilter = null;
        if ($userRole == 1 && $request->has('user_id')) {
            $userIdFilter = $request->user_id;
        } elseif ($userRole != 1) {
            $userIdFilter = $userId;
        }
        
        $query = DB::table('orders')
            ->select('status_orders.name as status', 'status_orders.id as status_id', DB::raw('COUNT(*) as count'), DB::raw('COALESCE(SUM(orders.total), 0) as total'))
            ->join('status_orders', 'orders.status_id', '=', 'status_orders.id')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        if ($userIdFilter) {
            $query->where('orders.operator_id', $userIdFilter);
        }
        
        $data = $query->groupBy('status_orders.id', 'status_orders.name')
            ->orderBy('status_orders.id')
            ->get();
        
        return response()->json($data);
    }

    public function topProducts(Request $request)
    {
        $userInfo = $this->getUserInfo($request);
        $userId = $userInfo['user_id'];
        $userRole = $userInfo['role_id'];
        
        $startDate = $request->input('start_date', now()->subDays(2)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        
        $userIdFilter = null;
        if ($userRole == 1 && $request->has('user_id')) {
            $userIdFilter = $request->user_id;
        } elseif ($userRole != 1) {
            $userIdFilter = $userId;
        }
        
        $query = DB::table('orders')
            ->select('orders.detail', DB::raw('SUM(orders.total) as total_sales'), DB::raw('COUNT(*) as order_count'))
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        if ($userIdFilter) {
            $query->where('orders.operator_id', $userIdFilter);
        }
        
        $orders = $query->groupBy('orders.id', 'orders.detail')->get();
        
        $productStats = [];
        foreach ($orders as $order) {
            $detail = is_string($order->detail) ? json_decode($order->detail, true) : $order->detail;
            if (isset($detail['items'])) {
                foreach ($detail['items'] as $item) {
                    $name = $item['name'] ?? 'Unknown';
                    if (!isset($productStats[$name])) {
                        $productStats[$name] = ['name' => $name, 'qty' => 0, 'total' => 0];
                    }
                    $productStats[$name]['qty'] += ($item['qty'] ?? 1);
                    $productStats[$name]['total'] += ($item['amount'] ?? 0) * ($item['qty'] ?? 1);
                }
            }
        }
        
        usort($productStats, fn($a, $b) => $b['total'] <=> $a['total']);
        
        return response()->json(array_slice($productStats, 0, 10));
    }

    public function salesTrend(Request $request)
    {
        $userInfo = $this->getUserInfo($request);
        $userId = $userInfo['user_id'];
        $userRole = $userInfo['role_id'];
        
        $days = $request->input('days', 7);
        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->toDateString();
        
        $userIdFilter = null;
        if ($userRole == 1 && $request->has('user_id')) {
            $userIdFilter = $request->user_id;
        } elseif ($userRole != 1) {
            $userIdFilter = $userId;
        }
        
        $query = DB::table('orders')
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as orders'), DB::raw('COALESCE(SUM(total), 0) as total'))
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        if ($userIdFilter) {
            $query->where('operator_id', $userIdFilter);
        }
        
        $data = $query->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();
        
        return response()->json($data);
    }

    public function cashiers(Request $request)
    {
        $cashiers = DB::table('users')
            ->select('id', 'name', 'username')
            ->where('role_id', 2)
            ->where('enable', true)
            ->orderBy('name')
            ->get();
        
        return response()->json($cashiers);
    }
}