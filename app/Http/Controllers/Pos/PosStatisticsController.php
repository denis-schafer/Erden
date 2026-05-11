<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class PosStatisticsController extends Controller
{
    /**
     * Resumen general: total pedidos, ventas, promedio
     */
    public function summary(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->input('user_id');
        $statusId = $request->input('status_id');

        $query = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($userId) {
            $query->where('operator_id', $userId);
        }
        if ($statusId) {
            $query->where('status_id', $statusId);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_orders,
            SUM(total) as total_sales,
            AVG(total) as avg_order,
            SUM(CASE WHEN paid = 1 THEN 1 ELSE 0 END) as paid_orders,
            SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as canceled_orders,
            SUM(CASE WHEN status_id = 4 THEN total ELSE 0 END) as canceled_amount
        ')->first();

        // Get canceled orders separately for clarity
        $canceledQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status_id', 4);
        
        if ($userId) {
            $canceledQuery->where('operator_id', $userId);
        }
        
        $canceledStats = $canceledQuery->selectRaw('
            COUNT(*) as count,
            SUM(total) as amount
        ')->first();

        return response()->json([
            'total_orders' => $stats->total_orders ?? 0,
            'total_sales' => $stats->total_sales ?? 0,
            'avg_order' => $stats->avg_order ?? 0,
            'paid_orders' => $stats->paid_orders ?? 0,
            'canceled_orders' => $canceledStats->count ?? 0,
            'canceled_amount' => $canceledStats->amount ?? 0,
            'completion_rate' => $stats->total_orders > 0 
                ? ($stats->paid_orders / $stats->total_orders) * 100 
                : 0,
        ]);
    }

    /**
     * Ventas agrupadas por día/semana/mes
     */
    public function salesByPeriod(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(3)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->input('user_id');

        $query = DB::table('orders')
            ->select('created_at as date', 'total')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status_id', '!=', 4)
            ->orderBy('created_at');

        if ($userId) {
            $query->where('operator_id', $userId);
        }

        return response()->json($query->get());
    }

    /**
     * Productos más vendidos
     */
    public function topProducts(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->input('user_id');
        $limit = $request->input('limit', 20);

        // Parse JSON detail and aggregate
        $ordersQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status_id', '!=', 4);

        if ($userId) {
            $ordersQuery->where('operator_id', $userId);
        }

        $orders = $ordersQuery->get();
        
        $productStats = [];
        $totalItems = 0;
        $totalAmount = 0;

        foreach ($orders as $order) {
            $detail = json_decode($order->detail, true);
            if (isset($detail['items']) && is_array($detail['items'])) {
                foreach ($detail['items'] as $item) {
                    $name = $item['name'] ?? 'Unknown';
                    $qty = $item['qty'] ?? 1;
                    $amount = $item['amount'] ?? 0;
                    
                    if (!isset($productStats[$name])) {
                        $productStats[$name] = [
                            'name' => $name,
                            'quantity' => 0,
                            'amount' => 0,
                        ];
                    }
                    $productStats[$name]['quantity'] += $qty;
                    $productStats[$name]['amount'] += ($amount * $qty);
                    $totalItems += $qty;
                    $totalAmount += ($amount * $qty);
                }
            }
        }

        // Sort by quantity descending
        uasort($productStats, function ($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });

        // Add percentages
        $topProducts = array_slice($productStats, 0, $limit);
        foreach ($topProducts as &$product) {
            $product['quantity_pct'] = $totalItems > 0 ? ($product['quantity'] / $totalItems) * 100 : 0;
            $product['amount_pct'] = $totalAmount > 0 ? ($product['amount'] / $totalAmount) * 100 : 0;
        }

        // Calculate total amount
        $totalAmount = array_sum(array_map(fn($p) => $p['amount'], array_values($topProducts)));
        
        return response()->json([
            'products' => array_values($topProducts),
            'total_unique' => count($productStats),
            'total_items' => $totalItems,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Exportar a Excel (XLSX)
     */
    public function export(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->input('user_id');
        $statusId = $request->input('status_id');

        $query = DB::table('orders')
            ->join('users', 'orders.operator_id', '=', 'users.id')
            ->whereBetween('orders.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($userId) {
            $query->where('orders.operator_id', $userId);
        }
        if ($statusId) {
            $query->where('orders.status_id', $statusId);
        }

        $orders = $query->select('orders.*', 'users.name as operator_name')->get();

        // Get statistics
        $statsQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        if ($userId) {
            $statsQuery->where('operator_id', $userId);
        }
        if ($statusId) {
            $statsQuery->where('status_id', $statusId);
        }

        $stats = $statsQuery->selectRaw('
            COUNT(*) as total_orders,
            SUM(total) as total_sales,
            AVG(total) as avg_order,
            SUM(CASE WHEN status_id = 4 THEN 1 ELSE 0 END) as canceled_orders,
            SUM(CASE WHEN status_id = 4 THEN total ELSE 0 END) as canceled_amount
        ')->first();

        // Get top products
        $ordersForProducts = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status_id', '!=', 4);
        
        if ($userId) {
            $ordersForProducts->where('operator_id', $userId);
        }
        if ($statusId) {
            $ordersForProducts->where('status_id', $statusId);
        }

        $ordersData = $ordersForProducts->get();
        
        $productStats = [];
        $totalItems = 0;
        $totalAmount = 0;

        foreach ($ordersData as $order) {
            $detail = json_decode($order->detail, true);
            if (isset($detail['items']) && is_array($detail['items'])) {
                foreach ($detail['items'] as $item) {
                    $name = $item['name'] ?? 'Unknown';
                    $qty = $item['qty'] ?? 1;
                    $amount = $item['amount'] ?? 0;
                    
                    if (!isset($productStats[$name])) {
                        $productStats[$name] = [
                            'name' => $name,
                            'quantity' => 0,
                            'amount' => 0,
                        ];
                    }
                    $productStats[$name]['quantity'] += $qty;
                    $productStats[$name]['amount'] += ($amount * $qty);
                    $totalItems += $qty;
                    $totalAmount += ($amount * $qty);
                }
            }
        }

        uasort($productStats, function ($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });

        $topProducts = array_slice($productStats, 0, 20);
        foreach ($topProducts as &$product) {
            $product['quantity_pct'] = $totalItems > 0 ? ($product['quantity'] / $totalItems) * 100 : 0;
            $product['amount_pct'] = $totalAmount > 0 ? ($product['amount'] / $totalAmount) * 100 : 0;
        }

        // Generate Excel with multiple sheets
        $sheet1Data = [
            ['Resumen General'],
            ['Total Pedidos', $stats->total_orders ?? 0],
            ['Total Recaudado', number_format($stats->total_sales ?? 0, 2, '.', '')],
            ['Ticket Promedio', number_format($stats->avg_order ?? 0, 2, '.', '')],
            ['Total Items Vendidos', $totalItems],
            ['Productos Únicos', count($productStats)],
            ['Fecha Inicio', $startDate],
            ['Fecha Fin', $endDate],
            ['Pedidos Cancelados', $stats->canceled_orders ?? 0],
            ['Monto Cancelado', number_format($stats->canceled_amount ?? 0, 2, '.', '')],
        ];

        $sheet2Headers = ['ID', 'Fecha', 'Total', 'Operador', 'Estado', 'Pagado', 'ID Pago (MP)'];
        $sheet2Rows = [];
        foreach ($orders as $order) {
            $sheet2Rows[] = [
                $order->id,
                $order->created_at,
                number_format($order->total, 2, '.', ''),
                $order->operator_name,
                $order->status_id == 3 ? 'Completado' : ($order->status_id == 4 ? 'Cancelado' : 'Pendiente'),
                $order->paid ? 'Sí' : 'No',
                $order->mp_payment_id ?? 'N/A'
            ];
        }

        $sheet3Headers = ['Producto', 'Cantidad Vendida', '% Cantidad', 'Total', '% del Total'];
        $sheet3Rows = [];
        foreach ($topProducts as $product) {
            $sheet3Rows[] = [
                $product['name'],
                $product['quantity'],
                number_format($product['quantity_pct'], 1) . '%',
                number_format($product['amount'], 2, '.', ''),
                number_format($product['amount_pct'], 1) . '%'
            ];
        }

        // Return JSON data for frontend to generate Excel (XLSX is a frontend library)
        return response()->json([
            'summary' => $sheet1Data,
            'orders' => ['headers' => $sheet2Headers, 'rows' => $sheet2Rows],
            'products' => ['headers' => $sheet3Headers, 'rows' => $sheet3Rows],
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }
}
