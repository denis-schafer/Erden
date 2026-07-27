<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

        $totalIncome = DB::table('hairsalon_cash_movements')
            ->where('type', 'income')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('amount');

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

        $totalIncome = $jobs->sum('total');
        $totalExpenses = DB::table('hairsalon_cash_movements')
            ->where('type', 'expense')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('amount');

        $spreadsheet = new Spreadsheet();

        // Sheet 1: Trabajos
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Trabajos');
        $sheet1->setCellValue('A1', 'Fecha');
        $sheet1->setCellValue('B1', 'Cliente');
        $sheet1->setCellValue('C1', 'Operador');
        $sheet1->setCellValue('D1', 'Total');
        $sheet1->setCellValue('E1', 'Método');
        $sheet1->setCellValue('F1', 'Estado');
        $sheet1->getStyle('A1:F1')->getFont()->setBold(true);

        $row = 2;
        foreach ($jobs as $job) {
            $method = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'mercadopago' => 'MercadoPago', 'other' => 'Otro'][$job->payment_method] ?? $job->payment_method;
            $status = ['completed' => 'Completado', 'pending' => 'Pendiente', 'cancelled' => 'Cancelado', 'in_progress' => 'En Proceso'][$job->status] ?? $job->status;
            $sheet1->setCellValue('A' . $row, $job->created_at);
            $sheet1->setCellValue('B' . $row, $job->client_name);
            $sheet1->setCellValue('C' . $row, $job->operator_name);
            $sheet1->setCellValue('D' . $row, $job->total);
            $sheet1->setCellValue('E' . $row, $method);
            $sheet1->setCellValue('F' . $row, $status);
            $row++;
        }
        $sheet1->getColumnDimension('A')->setAutoSize(true);
        $sheet1->getColumnDimension('B')->setAutoSize(true);
        $sheet1->getColumnDimension('C')->setAutoSize(true);
        $sheet1->getColumnDimension('D')->setAutoSize(true);
        $sheet1->getColumnDimension('E')->setAutoSize(true);
        $sheet1->getColumnDimension('F')->setAutoSize(true);

        // Sheet 2: Resumen
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Resumen');
        $sheet2->setCellValue('A1', 'Resumen');
        $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summaryRows = [
            ['Total de Trabajos', $jobs->count()],
            ['Ingresos Totales', number_format($totalIncome, 2, '.', '')],
            ['Gastos Totales', number_format($totalExpenses, 2, '.', '')],
            ['Balance', number_format($totalIncome - $totalExpenses, 2, '.', '')],
            ['Fecha Inicio', $startDate],
            ['Fecha Fin', $endDate],
        ];
        foreach ($summaryRows as $i => $rowData) {
            $sheet2->setCellValue('A' . ($i + 2), $rowData[0]);
            $sheet2->setCellValue('B' . ($i + 2), $rowData[1]);
        }
        $sheet2->getColumnDimension('A')->setAutoSize(true);

        // Generate and output
        $writer = new Xlsx($spreadsheet);
        $filename = 'estadisticas_peluqueria_' . $startDate . '_' . $endDate . '.xlsx';

        $tempFile = tempnam(sys_get_temp_dir(), 'hairsalon_stats_');
        $writer->save($tempFile);
        $content = file_get_contents($tempFile);
        unlink($tempFile);

        if (empty($content)) {
            return response()->json(['error' => 'No se pudo generar el archivo Excel'], 500);
        }

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
