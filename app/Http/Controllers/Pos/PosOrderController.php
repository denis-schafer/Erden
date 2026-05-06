<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Events\OrderDeleted;
use App\Services\RawPrinterService;

class PosOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('orders')
            ->select('orders.*', 'users.name as operator_name', 'users.username as operator_username', 'status_orders.name as status_name')
            ->join('users', 'orders.operator_id', '=', 'users.id')
            ->join('status_orders', 'orders.status_id', '=', 'status_orders.id')
            ->orderBy('orders.created_at', 'desc');

        if ($request->has('status')) {
            $query->where('orders.status_id', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('orders.created_at', $request->date);
        }

        $orders = $query->get();

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'dni' => 'nullable|string|max:20',
            'detail' => 'required|array',
            'total' => 'required|numeric|min:0',
            'operator_id' => 'required|exists:users,id',
            'status_id' => 'required|exists:status_orders,id',
            'paid' => 'boolean',
        ]);

        $id = DB::table('orders')->insertGetId([
            'dni' => $validated['dni'] ?? null,
            'detail' => json_encode($validated['detail']),
            'total' => $validated['total'],
            'operator_id' => $validated['operator_id'],
            'status_id' => $validated['status_id'] ?? 1,
            'paid' => $validated['paid'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = DB::table('orders')->where('id', $id)->first();
        $printResult = ['success' => false, 'message' => ''];

        // Try to print ticket if enabled
        $printResult = $this->tryPrintTicket($order, $validated['detail'], $validated['operator_id']);

        event(new OrderCreated((array) $order));

        return response()->json([
            'id' => $id, 
            'message' => 'Pedido creado',
            'print' => $printResult
        ]);
    }

    private function tryPrintTicket($order, $detail, $operatorId)
    {
        try {
            // Get operator (user) to get printer config from user settings
            $operator = DB::table('users')->find($operatorId);
            
            if (!$operator) {
                return ['success' => false, 'message' => 'Usuario operador no encontrado'];
            }

            // Check if printing is enabled for this user
            $enablePrint = $operator->enable_print ?? false;
            
            if (!$enablePrint) {
                return ['success' => false, 'enable_print' => false, 'message' => 'Impresión deshabilitada para este usuario'];
            }

            // Get printer config from user
            $printerIp = $operator->printer_ip ?? null;

            if (empty($printerIp)) {
                return ['success' => false, 'printer_configured' => false, 'message' => 'Impresora no configurada para este usuario'];
            }

            $printerPort = $operator->printer_port ?? 9100;
            $printerWidth = (int) ($operator->printer_width ?? 80);

            // Get ticket title from configs (business-level setting)
            $ticketTitleConfig = DB::table('configs')->where('name', 'ticket_title')->first();
            $ticketTitle = $ticketTitleConfig ? $ticketTitleConfig->value : 'MI NEGOCIO';

            // Get business info
            $businessName = DB::table('configs')->where('name', 'business_name')->first();
            $businessAddress = DB::table('configs')->where('name', 'business_address')->first();
            $businessPhone = DB::table('configs')->where('name', 'business_phone')->first();
            $businessNit = DB::table('configs')->where('name', 'business_nit')->first();

            $businessInfo = [
                'name' => $businessName ? $businessName->value : '',
                'address' => $businessAddress ? $businessAddress->value : '',
                'phone' => $businessPhone ? $businessPhone->value : '',
                'nit' => $businessNit ? $businessNit->value : '',
            ];

            // Get operator name
            $userName = $operator->name ?? 'Caja';

            // Prepare order data for printing
            $cartItems = [];
            if (isset($detail['items'])) {
                foreach ($detail['items'] as $item) {
                    $cartItems[] = [
                        'name' => $item['name'] ?? 'Producto',
                        'qty' => $item['qty'] ?? 1,
                        'amount' => $item['amount'] ?? 0,
                    ];
                }
            }

            $orderData = [
                'order_id' => $order->id,
                'total' => $order->total,
                'user_name' => $userName,
                'cart' => $cartItems,
            ];

            // Print ticket
            $printerService = new RawPrinterService();
            $printerService->printTicket($orderData, [
                'ip' => $printerIp,
                'port' => $printerPort,
                'width' => $printerWidth,
                'title' => $ticketTitle,
                'business' => $businessInfo,
            ]);

            return ['success' => true, 'message' => 'Ticket impreso'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    public function show($id)
    {
        $order = DB::table('orders')
            ->select('orders.*', 'users.name as operator_name', 'status_orders.name as status_name')
            ->join('users', 'orders.operator_id', '=', 'users.id')
            ->join('status_orders', 'orders.status_id', '=', 'status_orders.id')
            ->where('orders.id', $id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        $order->detail = json_decode($order->detail);

        return response()->json($order);
    }

    public function cancel($id)
    {
        $order = DB::table('orders')->where('id', $id)->first();
        
        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        DB::table('orders')->where('id', $id)->update([
            'status_id' => 4, // cancelled
            'updated_at' => now(),
        ]);

        $updatedOrder = DB::table('orders')->where('id', $id)->first();
        event(new OrderUpdated((array) $updatedOrder));

        return response()->json(['message' => 'Pedido cancelado', 'success' => true]);
    }

    public function destroy($id)
    {
        $order = DB::table('orders')->where('id', $id)->first();
        
        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        DB::table('orders')->where('id', $id)->update([
            'status_id' => 2,
            'updated_at' => now(),
        ]);

        event(new OrderDeleted($id));

        return response()->json(['message' => 'Pedido eliminado', 'success' => true]);
    }

    public function reprint(Request $request, $id)
    {
        $order = DB::table('orders')->where('id', $id)->first();
        
        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        $detail = json_decode($order->detail);
        
        // Get printer config from operator (user) - same as tryPrintTicket
        $operator = DB::table('users')->find($order->operator_id);
        
        if (!$operator) {
            return response()->json(['success' => false, 'message' => 'Usuario operador no encontrado'], 404);
        }

        // Check if printing is enabled for this user
        $enablePrint = $operator->enable_print ?? false;
        
        if (!$enablePrint) {
            return response()->json(['success' => false, 'enable_print' => false, 'message' => 'Impresión deshabilitada para este usuario'], 400);
        }

        // Get printer config from user
        $printerIp = $operator->printer_ip ?? null;

        if (empty($printerIp)) {
            return response()->json(['success' => false, 'message' => 'Impresora no configurada para este usuario'], 400);
        }

        $printerPort = $operator->printer_port ?? 9100;
        $printerWidth = (int) ($operator->printer_width ?? 80);

        // Get ticket title from configs (business-level setting)
        $ticketTitleConfig = DB::table('configs')->where('name', 'ticket_title')->first();
        $ticketTitle = $ticketTitleConfig ? $ticketTitleConfig->value : 'MI NEGOCIO';

        // Get business info
        $businessName = DB::table('configs')->where('name', 'business_name')->first();
        $businessAddress = DB::table('configs')->where('name', 'business_address')->first();
        $businessPhone = DB::table('configs')->where('name', 'business_phone')->first();
        $businessNit = DB::table('configs')->where('name', 'business_nit')->first();

        $businessInfo = [
            'name' => $businessName ? $businessName->value : '',
            'address' => $businessAddress ? $businessAddress->value : '',
            'phone' => $businessPhone ? $businessPhone->value : '',
            'nit' => $businessNit ? $businessNit->value : '',
        ];

        // Get operator name
        $userName = $operator->name ?? 'Caja';

        try {
            // Prepare order data
            $cartItems = [];
            if (isset($detail->items)) {
                foreach ($detail->items as $item) {
                    $cartItems[] = [
                        'name' => $item->name,
                        'qty' => $item->qty,
                        'amount' => $item->amount,
                    ];
                }
            }

            $orderData = [
                'order_id' => $order->id,
                'total' => $order->total,
                'user_name' => $userName,
                'cart' => $cartItems,
            ];

            $printerService = new RawPrinterService();
            $printerService->printTicket($orderData, [
                'ip' => $printerIp,
                'port' => $printerPort,
                'width' => $printerWidth,
                'title' => $ticketTitle,
                'business' => $businessInfo,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Ticket reimpreso exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reimprimir: ' . $e->getMessage()
            ], 500);
        }
    }
}