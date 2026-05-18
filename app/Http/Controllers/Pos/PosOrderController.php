<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Events\OrderDeleted;
use App\Services\PrintJobService;

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

        // Create print job for local agent
        $this->createPrintJob($order, $validated['detail'], $validated['operator_id']);

        event(new OrderCreated((array) $order));

        return response()->json([
            'id' => $id, 
            'message' => 'Pedido creado',
        ]);
    }

    private function createPrintJob($order, $detail, $operatorId)
    {
        try {
            $printJobService = new PrintJobService();
            $printJobService->createFromOrder($order, $detail, $operatorId);
        } catch (\Exception $e) {
            Log::error('Error creating print job: ' . $e->getMessage());
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

    public function togglePaid(Request $request, $id)
    {
        $order = DB::table('orders')->where('id', $id)->first();

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        if ($order->status_id == 2) {
            return response()->json(['message' => 'No se puede cambiar el pago de una orden anulada'], 400);
        }

        $token = $request->bearerToken();
        $isAdmin = false;
        if ($token) {
            try {
                $decoded = json_decode(base64_decode($token), true);
                $userId = $decoded['sub'] ?? null;
                if ($userId) {
                    $user = DB::table('users')->find($userId);
                    $isAdmin = $user && ($user->role_id == 1 || $user->is_global_admin);
                }
            } catch (\Exception $e) {}
        }
        if (!$isAdmin) {
            $sessionUser = $request->session()->get('user');
            $isAdmin = $sessionUser && ($sessionUser['role_id'] == 1 || $request->session()->get('is_global_admin', false));
        }

        if (!$isAdmin && $order->paid) {
            return response()->json(['message' => 'No tienes permiso para desmarcar el pago de una orden'], 403);
        }

        $newPaid = $order->paid ? 0 : 1;

        DB::table('orders')->where('id', $id)->update([
            'paid' => $newPaid,
            'updated_at' => now(),
        ]);

        $updatedOrder = DB::table('orders')->where('id', $id)->first();
        event(new OrderUpdated((array) $updatedOrder));

        return response()->json([
            'message' => $newPaid ? 'Pedido marcado como pagado' : 'Pedido desmarcado como pagado',
            'success' => true,
            'order' => $updatedOrder,
        ]);
    }

    public function reprint(Request $request, $id)
    {
        $order = DB::table('orders')->where('id', $id)->first();
        
        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        $detail = json_decode($order->detail, true) ?? [];

        try {
            $printJobService = new PrintJobService();
            $printJobService->createFromOrder($order, $detail, $order->operator_id);
            
            return response()->json([
                'success' => true,
                'message' => 'Ticket enviado a impresión'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reimprimir: ' . $e->getMessage()
            ], 500);
        }
    }
}