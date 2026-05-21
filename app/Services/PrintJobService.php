<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrintJobService
{
    protected $rawPrinter;

    public function __construct()
    {
        $this->rawPrinter = new RawPrinterService();
    }

    public function createFromOrder($order, $detail, $operatorId, $companyDb = null): void
    {
        $operator = DB::table('users')->find($operatorId);
        if (!$operator || empty($operator->enable_print) || empty($operator->printer_ip)) {
            return;
        }

        $configs = DB::table('configs')->whereIn('name', [
            'ticket_title', 'business_name', 'business_address',
            'business_phone', 'business_nit'
        ])->pluck('value', 'name');

        $items = $detail['items'] ?? [];
        $cartItems = [];
        foreach ($items as $item) {
            $cartItems[] = [
                'name' => $item['name'] ?? '',
                'qty' => $item['qty'] ?? 1,
                'amount' => $item['amount'] ?? 0,
            ];
        }

        $orderData = [
            'order_id' => $order->id,
            'total' => $order->total,
            'user_name' => $operator->name ?? $operator->username ?? 'Caja',
            'cart' => $cartItems,
        ];

        $printerWidth = (int) ($operator->printer_width ?? 80);
        $ticketTitle = $configs->get('ticket_title', 'MI NEGOCIO');

        $businessInfo = [
            'name' => $configs->get('business_name', ''),
            'address' => $configs->get('business_address', ''),
            'phone' => $configs->get('business_phone', ''),
            'nit' => $configs->get('business_nit', ''),
        ];

        $ticketData = $this->rawPrinter->generateTicketData(
            $orderData,
            $printerWidth,
            $ticketTitle,
            $businessInfo
        );

        $printingMode = DB::table('configs')->where('name', 'printing_mode')->value('value');

        if ($printingMode === 'local') {
            try {
                $this->rawPrinter->sendToNetworkPrinter(
                    $operator->printer_ip,
                    $operator->printer_port ?? 9100,
                    base64_decode($ticketData)
                );
                Log::info("Direct print for order #{$order->id} to {$operator->printer_ip}");
            } catch (\Exception $e) {
                Log::error("Direct print failed for order #{$order->id}: " . $e->getMessage());
            }
            return;
        }

        DB::connection('mysql_parent')->table('print_jobs')->insert([
            'company_db' => $companyDb ?? config('database.connections.mysql.database', 'erden'),
            'order_id' => $order->id,
            'printer_ip' => $operator->printer_ip,
            'printer_port' => $operator->printer_port ?? 9100,
            'printer_width' => $operator->printer_width ?? '80mm',
            'ticket_data' => $ticketData,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        Log::info("PrintJob created for order #{$order->id} to printer {$operator->printer_ip} (company_db: {$companyDb})");
    }
}
