<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

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
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status_id', '!=', 2);

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
            SUM(CASE WHEN paid = 1 THEN 1 ELSE 0 END) as paid_orders
        ')->first();

        // Get canceled orders separately for clarity
        $canceledQuery = DB::table('orders')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status_id', 2);
        
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
            ->where('status_id', '!=', 2)
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
            ->where('status_id', '!=', 2);

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

    public function productsByInterval(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(3)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->input('user_id');

        $query = DB::table('orders')
            ->select('created_at', 'detail')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status_id', '!=', 2)
            ->orderBy('created_at');

        if ($userId) {
            $query->where('operator_id', $userId);
        }

        $orders = $query->get();

        $intervalData = [];
        foreach ($orders as $order) {
            $dt = \Carbon\Carbon::parse($order->created_at);
            $minute = floor($dt->minute / 10) * 10;
            $interval = $dt->format('Y-m-d H:') . str_pad($minute, 2, '0', STR_PAD_LEFT);

            $detail = json_decode($order->detail, true);
            foreach ($detail['items'] ?? [] as $item) {
                $name = $item['name'] ?? 'Unknown';
                $qty = $item['qty'] ?? 1;
                $key = $interval . '|' . $name;
                $intervalData[$key] = ($intervalData[$key] ?? 0) + $qty;
            }
        }

        $intervals = [];
        $productMap = [];
        foreach ($intervalData as $key => $qty) {
            [$interval, $product] = explode('|', $key, 2);
            if (!in_array($interval, $intervals)) $intervals[] = $interval;
            if (!isset($productMap[$product])) $productMap[$product] = [];
            $productMap[$product][$interval] = $qty;
        }

        sort($intervals);

        $products = [];
        foreach ($productMap as $name => $data) {
            $series = [];
            foreach ($intervals as $interval) {
                $series[] = $data[$interval] ?? 0;
            }
            $products[] = ['name' => $name, 'data' => $series];
        }

        return response()->json(['intervals' => $intervals, 'products' => $products]);
    }

    /**
     * Exportar a Excel (XLSX) con 4 hojas: Resumen, Pedidos, Productos, Gráficos
     */
    public function export(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->input('user_id');
        $statusId = $request->input('status_id');
        $selectedProducts = $request->input('selected_products');

        if ($selectedProducts && is_string($selectedProducts)) {
            $selectedProducts = json_decode($selectedProducts, true);
        }
        if (!is_array($selectedProducts)) {
            $selectedProducts = null;
        }

        // ---- Query helpers ----
        $applyUserAndStatus = function ($query) use ($userId, $statusId) {
            if ($userId) $query->where('operator_id', $userId);
            if ($statusId) $query->where('status_id', $statusId);
        };

        $dateRange = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

        // 1. Orders with operator name (all statuses)
        $orders = DB::table('orders')
            ->join('users', 'orders.operator_id', '=', 'users.id')
            ->whereBetween('orders.created_at', $dateRange);
        $applyUserAndStatus($orders);
        $orders = $orders->select('orders.*', 'users.name as operator_name')->get();

        // 2. Summary stats (exclude canceled)
        $statsQuery = DB::table('orders')
            ->whereBetween('created_at', $dateRange)
            ->where('status_id', '!=', 2);
        $applyUserAndStatus($statsQuery);
        $stats = $statsQuery->selectRaw('
            COUNT(*) as total_orders,
            SUM(total) as total_sales,
            AVG(total) as avg_order
        ')->first();

        // 2b. Canceled orders stats (separate query)
        $canceledQuery = DB::table('orders')
            ->whereBetween('created_at', $dateRange)
            ->where('status_id', 2);
        $applyUserAndStatus($canceledQuery);
        $canceledStats = $canceledQuery->selectRaw('
            COUNT(*) as canceled_orders,
            SUM(total) as canceled_amount
        ')->first();

        // 3. Top products (exclude canceled)
        $ordersForProducts = DB::table('orders')
            ->whereBetween('created_at', $dateRange)
            ->where('status_id', '!=', 2);
        $applyUserAndStatus($ordersForProducts);

        $ordersData = $ordersForProducts->get();
        $productStats = [];
        $totalItems = 0;
        foreach ($ordersData as $order) {
            $detail = json_decode($order->detail, true);
            if (isset($detail['items']) && is_array($detail['items'])) {
                foreach ($detail['items'] as $item) {
                    $name = $item['name'] ?? 'Unknown';
                    $qty = $item['qty'] ?? 1;
                    $amount = $item['amount'] ?? 0;
                    if (!isset($productStats[$name])) {
                        $productStats[$name] = ['name' => $name, 'quantity' => 0, 'amount' => 0];
                    }
                    $productStats[$name]['quantity'] += $qty;
                    $productStats[$name]['amount'] += ($amount * $qty);
                    $totalItems += $qty;
                }
            }
        }
        uasort($productStats, fn($a, $b) => $b['quantity'] - $a['quantity']);
        $topProducts = array_slice($productStats, 0, 20);
        $totalTopAmount = array_sum(array_map(fn($p) => $p['amount'], $topProducts));
        foreach ($topProducts as &$product) {
            $product['quantity_pct'] = $totalItems > 0 ? ($product['quantity'] / $totalItems) * 100 : 0;
            $product['amount_pct'] = $totalTopAmount > 0 ? ($product['amount'] / $totalTopAmount) * 100 : 0;
        }

        // 4. Sales by period (for bar chart)
        $salesData = DB::table('orders')
            ->select('created_at')
            ->whereBetween('created_at', $dateRange)
            ->where('status_id', '!=', 2);
        $applyUserAndStatus($salesData);
        $salesData = $salesData->get();

        $salesByInterval = [];
        foreach ($salesData as $sale) {
            $dt = \Carbon\Carbon::parse($sale->created_at);
            $minute = floor($dt->minute / 10) * 10;
            $interval = $dt->format('Y-m-d H:') . str_pad($minute, 2, '0', STR_PAD_LEFT);
            $salesByInterval[$interval] = ($salesByInterval[$interval] ?? 0) + 1;
        }
        ksort($salesByInterval);

        // 5. Products by interval (for line chart)
        $intervalData = [];
        $productOrders = DB::table('orders')
            ->select('created_at', 'detail')
            ->whereBetween('created_at', $dateRange)
            ->where('status_id', '!=', 2);
        $applyUserAndStatus($productOrders);
        $productOrders = $productOrders->get();

        foreach ($productOrders as $order) {
            $dt = \Carbon\Carbon::parse($order->created_at);
            $minute = floor($dt->minute / 10) * 10;
            $interval = $dt->format('Y-m-d H:') . str_pad($minute, 2, '0', STR_PAD_LEFT);
            $detail = json_decode($order->detail, true);
            foreach ($detail['items'] ?? [] as $item) {
                $name = $item['name'] ?? 'Unknown';
                $qty = $item['qty'] ?? 1;
                $key = $interval . '|' . $name;
                $intervalData[$key] = ($intervalData[$key] ?? 0) + $qty;
            }
        }

        $intervals = [];
        $productMap = [];
        foreach ($intervalData as $key => $qty) {
            [$interval, $product] = explode('|', $key, 2);
            if (!in_array($interval, $intervals)) $intervals[] = $interval;
            if (!isset($productMap[$product])) $productMap[$product] = [];
            $productMap[$product][$interval] = $qty;
        }
        sort($intervals);

        if ($selectedProducts) {
            $productMap = array_intersect_key($productMap, array_flip($selectedProducts));
        }

        uasort($productMap, fn($a, $b) => array_sum($b) - array_sum($a));

        $lineProducts = [];
        foreach ($productMap as $name => $data) {
            $series = [];
            foreach ($intervals as $interval) {
                $series[] = $data[$interval] ?? 0;
            }
            $lineProducts[] = ['name' => $name, 'data' => $series];
        }

        // ===== Create PhpSpreadsheet workbook =====
        $spreadsheet = new Spreadsheet();

        // ---- Sheet 1: Resumen ----
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Resumen');
        $sheet1->setCellValue('A1', 'Resumen General');
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summaryRows = [
            ['Total Pedidos', $stats->total_orders ?? 0],
            ['Total Recaudado', number_format($stats->total_sales ?? 0, 2, '.', '')],
            ['Ticket Promedio', number_format($stats->avg_order ?? 0, 2, '.', '')],
            ['Total Items Vendidos', $totalItems],
            ['Productos Únicos', count($productStats)],
            ['Fecha Inicio', $startDate],
            ['Fecha Fin', $endDate],
            ['Pedidos Cancelados', $canceledStats->canceled_orders ?? 0],
            ['Monto Cancelado', number_format($canceledStats->canceled_amount ?? 0, 2, '.', '')],
        ];
        foreach ($summaryRows as $i => [$label, $value]) {
            $row = $i + 2;
            $sheet1->setCellValue('A' . $row, $label);
            $sheet1->setCellValue('B' . $row, $value);
            $sheet1->getStyle('A' . $row)->getFont()->setBold(true);
        }
        $sheet1->getColumnDimension('A')->setAutoSize(true);
        $sheet1->getColumnDimension('B')->setAutoSize(true);

        // ---- Sheet 2: Pedidos ----
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Pedidos');
        $orderHeaders = ['ID', 'Fecha', 'Total', 'Operador', 'Estado', 'Pagado', 'ID Pago (MP)'];
        foreach ($orderHeaders as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet2->setCellValue($col . '1', $header);
            $sheet2->getStyle($col . '1')->getFont()->setBold(true);
        }
        $row = 2;
        foreach ($orders as $order) {
            $sheet2->setCellValue('A' . $row, $order->id);
            $sheet2->setCellValue('B' . $row, $order->created_at);
            $sheet2->setCellValue('C' . $row, number_format($order->total, 2, '.', ''));
            $sheet2->setCellValue('D' . $row, $order->operator_name);
            $sheet2->setCellValue('E' . $row, match ($order->status_id) { 3 => 'Completado', 2 => 'Cancelado', default => 'Pendiente' });
            $sheet2->setCellValue('F' . $row, $order->paid ? 'Sí' : 'No');
            $sheet2->setCellValue('G' . $row, $order->mp_payment_id ?? 'N/A');
            $row++;
        }
        foreach (range('A', 'G') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        // ---- Sheet 3: Productos ----
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Productos');
        $prodHeaders = ['Producto', 'Cantidad Vendida', '% Cantidad', 'Total', '% del Total'];
        foreach ($prodHeaders as $i => $header) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet3->setCellValue($col . '1', $header);
            $sheet3->getStyle($col . '1')->getFont()->setBold(true);
        }
        $row = 2;
        foreach ($topProducts as $product) {
            $sheet3->setCellValue('A' . $row, $product['name']);
            $sheet3->setCellValue('B' . $row, $product['quantity']);
            $sheet3->setCellValue('C' . $row, number_format($product['quantity_pct'], 1) . '%');
            $sheet3->setCellValue('D' . $row, number_format($product['amount'], 2, '.', ''));
            $sheet3->setCellValue('E' . $row, number_format($product['amount_pct'], 1) . '%');
            $row++;
        }
        foreach (range('A', 'E') as $col) {
            $sheet3->getColumnDimension($col)->setAutoSize(true);
        }

        // ---- Sheet 4: Gráficos ----
        $sheet4 = $spreadsheet->createSheet();
        $sheet4->setTitle('Gráficos');

        $colorPalette = [
            '0D6EFD', 'DC3545', '198754', 'FFC107', '0DCAF0',
            '6F42C1', 'FD7E14', '20C997', 'E83E8C', '17A2B8',
            '6610F2', 'D63384', '14B8A6', 'F97316', '84CC16',
            '06B6D4', 'A855F7', 'EC4899', 'F59E0B', '8B5CF6'
        ];

        // --- Bar Chart: Ventas por Período ---
        $barTitleRow = 1;
        $barHeaderRow = 2;
        $barFirstRow = 3;
        $barIntervalLabels = array_keys($salesByInterval);
        $barCounts = array_values($salesByInterval);
        $barLastRow = $barFirstRow + count($barIntervalLabels) - 1;

        $sheet4->setCellValue('A' . $barTitleRow, 'Ventas por Período');
        $sheet4->getStyle('A' . $barTitleRow)->getFont()->setBold(true)->setSize(12);
        $sheet4->setCellValue('A' . $barHeaderRow, 'Intervalo');
        $sheet4->setCellValue('B' . $barHeaderRow, 'Pedidos');
        $sheet4->getStyle('A' . $barHeaderRow . ':B' . $barHeaderRow)->getFont()->setBold(true);

        foreach ($barIntervalLabels as $i => $interval) {
            $sheet4->setCellValue('A' . ($barFirstRow + $i), substr($interval, 11, 5));
            $sheet4->setCellValue('B' . ($barFirstRow + $i), $barCounts[$i]);
        }

        $barLabel = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficos!$B$' . $barHeaderRow, null, 1);
        $barCategory = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficos!$A$' . $barFirstRow . ':$A$' . $barLastRow, null, count($barIntervalLabels));
        $barValues = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Gráficos!$B$' . $barFirstRow . ':$B$' . $barLastRow, null, count($barCounts));

        $barSeries = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            [0],
            [$barLabel],
            [$barCategory],
            [$barValues]
        );

        $barPlotArea = new PlotArea(null, [$barSeries]);
        $barLegend = new Legend();
        $barTitle = new Title('Ventas por Período');

        $barChart = new Chart('ventas', $barTitle, $barLegend, $barPlotArea);
        $barChart->setTopLeftPosition('D1');
        $barChart->setBottomRightPosition('O16');
        $sheet4->addChart($barChart);

        // --- Doughnut Chart: Distribución de Productos ---
        $doughnutTitleRow = $barLastRow + 2;
        $doughnutHeaderRow = $doughnutTitleRow + 1;
        $doughnutFirstRow = $doughnutTitleRow + 2;
        $doughnutLastRow = $doughnutFirstRow + count($topProducts) - 1;

        $sheet4->setCellValue('A' . $doughnutTitleRow, 'Distribución de Productos');
        $sheet4->getStyle('A' . $doughnutTitleRow)->getFont()->setBold(true)->setSize(12);
        $sheet4->setCellValue('A' . $doughnutHeaderRow, 'Producto');
        $sheet4->setCellValue('B' . $doughnutHeaderRow, 'Cantidad');
        $sheet4->getStyle('A' . $doughnutHeaderRow . ':B' . $doughnutHeaderRow)->getFont()->setBold(true);

        $doughnutRow = $doughnutFirstRow;
        foreach ($topProducts as $product) {
            $sheet4->setCellValue('A' . $doughnutRow, $product['name']);
            $sheet4->setCellValue('B' . $doughnutRow, $product['quantity']);
            $doughnutRow++;
        }

        $doughnutLabel = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficos!$B$' . $doughnutHeaderRow, null, 1);
        $doughnutCategory = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Gráficos!$A$' . $doughnutFirstRow . ':$A$' . $doughnutLastRow, null, count($topProducts));
        $doughnutValues = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Gráficos!$B$' . $doughnutFirstRow . ':$B$' . $doughnutLastRow, null, count($topProducts));

        $doughnutSeries = new DataSeries(
            DataSeries::TYPE_DOUGHNUTCHART,
            null,
            [0],
            [$doughnutLabel],
            [$doughnutCategory],
            [$doughnutValues]
        );

        $doughnutPlotArea = new PlotArea(null, [$doughnutSeries]);
        $doughnutLegend = new Legend(Legend::POSITION_RIGHT);
        $doughnutTitle = new Title('Distribución de Productos');

        $doughnutChart = new Chart('distribucion', $doughnutTitle, $doughnutLegend, $doughnutPlotArea);
        $doughnutChart->setTopLeftPosition('D18');
        $doughnutChart->setBottomRightPosition('O33');
        $sheet4->addChart($doughnutChart);

        // --- Line Chart: Productos Vendidos cada 10 min ---
        if (!empty($lineProducts) && !empty($intervals)) {
            $lineTitleRow = $doughnutLastRow + 2;
            $lineHeaderRow = $lineTitleRow + 1;
            $lineFirstRow = $lineTitleRow + 2;
            $lineLastRow = $lineFirstRow + count($intervals) - 1;

            $sheet4->setCellValue('A' . $lineTitleRow, 'Productos Vendidos cada 10 min');
            $sheet4->getStyle('A' . $lineTitleRow)->getFont()->setBold(true)->setSize(12);
            $sheet4->setCellValue('A' . $lineHeaderRow, 'Intervalo');
            $sheet4->getStyle('A' . $lineHeaderRow)->getFont()->setBold(true);

            foreach ($lineProducts as $i => $product) {
                $col = Coordinate::stringFromColumnIndex($i + 2);
                $sheet4->setCellValue($col . $lineHeaderRow, $product['name']);
                $sheet4->getStyle($col . $lineHeaderRow)->getFont()->setBold(true);
            }

            foreach ($intervals as $i => $interval) {
                $sheet4->setCellValue('A' . ($lineFirstRow + $i), substr($interval, 11, 5));
                foreach ($lineProducts as $j => $product) {
                    $col = Coordinate::stringFromColumnIndex($j + 2);
                    $sheet4->setCellValue($col . ($lineFirstRow + $i), $product['data'][$i]);
                }
            }

            $lineSeriesLabels = [];
            $lineSeriesValues = [];
            foreach ($lineProducts as $i => $product) {
                $col = Coordinate::stringFromColumnIndex($i + 2);
                $lineSeriesLabels[] = new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    'Gráficos!' . $col . '$' . $lineHeaderRow,
                    null, 1
                );
                $values = new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    'Gráficos!' . $col . '$' . $lineFirstRow . ':$' . $col . '$' . $lineLastRow,
                    null, count($intervals)
                );
                $values->setFillColor($colorPalette[$i % count($colorPalette)]);
                $lineSeriesValues[] = $values;
            }

            $lineCategory = new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                'Gráficos!$A$' . $lineFirstRow . ':$A$' . $lineLastRow,
                null, count($intervals)
            );

            $lineDataSeries = new DataSeries(
                DataSeries::TYPE_LINECHART,
                null,
                range(0, count($lineProducts) - 1),
                $lineSeriesLabels,
                [$lineCategory],
                $lineSeriesValues
            );

            $linePlotArea = new PlotArea(null, [$lineDataSeries]);
            $lineLegend = new Legend(Legend::POSITION_BOTTOM);
            $lineTitle = new Title('Productos Vendidos cada 10 min');

            $lineChart = new Chart('productos', $lineTitle, $lineLegend, $linePlotArea);
            $lineChart->setTopLeftPosition('D35');
            $lineChart->setBottomRightPosition('O55');
            $sheet4->addChart($lineChart);
        }

        // Auto-size columns for readability
        $sheet4->getColumnDimension('A')->setAutoSize(true);
        $sheet4->getColumnDimension('B')->setAutoSize(true);

        // ===== Generate and return XLSX =====
        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);

        $filename = 'estadisticas_' . $startDate . '_' . $endDate . '.xlsx';

        $tempFile = tempnam(sys_get_temp_dir(), 'estadisticas_');
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        if (empty($content)) {
            return response()->json(['error' => 'No se pudo generar el archivo Excel'], 500);
        }

        // Bypass Laravel Response entirely to prevent any BOM injection during transmission
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
}
