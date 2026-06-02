<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Events\OrderDeleted;
use App\Packages\Pos\Helpers\TestModeHelper;
use App\Services\PrintJobService;
use App\Services\MercadoPagoPointService;

class PosOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('orders')
            ->select('orders.*', 'users.name as operator_name', 'users.username as operator_username', 'status_orders.name as status_name')
            ->join('users', 'orders.operator_id', '=', 'users.id')
            ->join('status_orders', 'orders.status_id', '=', 'status_orders.id')
            ->orderBy('orders.created_at', 'desc');

        TestModeHelper::applyFilter($query, 'orders');

        if ($request->has('status') && $request->status !== '') {
            $query->where('orders.status_id', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('orders.created_at', $request->date);
        }

        if ($request->has('operator_id')) {
            $query->where('orders.operator_id', $request->operator_id);
        }

        $perPage = $request->input('per_page', 10);
        $orders = $query->paginate($perPage);

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

        $syncId = Str::uuid()->toString();
        $orderData = TestModeHelper::setTestFlag([
            'dni' => $validated['dni'] ?? null,
            'detail' => json_encode($validated['detail']),
            'total' => $validated['total'],
            'operator_id' => $validated['operator_id'],
            'status_id' => $validated['status_id'] ?? 1,
            'sync_id' => $syncId,
            'paid' => $validated['paid'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('orders')->insertGetId($orderData);

        $order = DB::table('orders')->where('id', $id)->first();

        // Create print job for local agent
        $this->createPrintJob($order, $validated['detail'], $validated['operator_id']);

        // Send to Point Postnet if operator has posnet_id
        $this->sendToPointTerminal($order, $validated['operator_id']);

        event(new OrderCreated((array) $order));

        $this->queueSync('orders', 'created', $order, ['operator_id' => 'users', 'status_id' => 'status_orders']);

        return response()->json([
            'id' => $id, 
            'message' => 'Pedido creado',
        ]);
    }

    private function createPrintJob($order, $detail, $operatorId)
    {
        try {
            $companyDb = $this->resolveCompanyDb();
            if (!$companyDb) {
                Log::warning('Cannot create print job: no valid company_db');
                return;
            }
            $printJobService = new PrintJobService();
            $printJobService->createFromOrder($order, $detail, $operatorId, $companyDb);
        } catch (\Exception $e) {
            Log::error('Error creating print job: ' . $e->getMessage());
        }
    }

    private function sendToPointTerminal($order, int $operatorId): void
    {
        try {
            $operator = DB::table('users')->find($operatorId);
            if (!$operator || empty($operator->posnet_id) || !$operator->mercadopago_qr_enabled) {
                return;
            }

            $accessToken = DB::table('configs')->where('name', 'mp_access_token')->value('value');
            if (empty($accessToken)) {
                Log::warning('[MercadoPagoPoint] No mp_access_token found for company');
                return;
            }

            $companyDb = $this->resolveCompanyDb();
            $externalReference = ($companyDb ?? 'unknown') . '-' . $operatorId . '-' . $order->id;
            $amount = number_format($order->total, 2, '.', '');
            $description = 'Pedido #' . $order->id;

            $pointService = new MercadoPagoPointService($accessToken);
            $result = $pointService->createOrder(
                $operator->posnet_id,
                $amount,
                $externalReference,
                $description
            );

            if ($result['success']) {
                Log::info('[MercadoPagoPoint] Order sent to terminal', [
                    'order_id' => $order->id,
                    'operator' => $operator->username,
                    'terminal' => $operator->posnet_id,
                ]);
            } else {
                Log::warning('[MercadoPagoPoint] Failed to send order to terminal', [
                    'order_id' => $order->id,
                    'operator' => $operator->username,
                    'error' => $result['error'] ?? 'unknown',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('[MercadoPagoPoint] Error sending to Point terminal', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveCompanyDb(): ?string
    {
        $company = session('company', []);
        if (!empty($company['db'])) {
            return $company['db'];
        }
        $sessionDb = session('company_db');
        if ($sessionDb) {
            return $sessionDb;
        }
        $headerDb = request()->header('X-Company-Db');
        if ($headerDb) {
            return $headerDb;
        }
        $connectedDb = config('database.connections.mysql.database');
        if ($connectedDb && $connectedDb !== 'erden') {
            return $connectedDb;
        }
        return null;
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

        $this->queueSync('orders', 'updated', $updatedOrder, ['operator_id' => 'users', 'status_id' => 'status_orders']);

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

        $updatedOrder = DB::table('orders')->where('id', $id)->first();
        $this->queueSync('orders', 'updated', $updatedOrder, ['operator_id' => 'users', 'status_id' => 'status_orders']);

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

        $this->queueSync('orders', 'updated', $updatedOrder, ['operator_id' => 'users', 'status_id' => 'status_orders']);

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
            $companyDb = $this->resolveCompanyDb();
            if (!$companyDb) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo determinar la compañía. Intente cerrar sesión y volver a iniciar.'
                ], 401);
            }
            $printJobService = new PrintJobService();
            $printJobService->createFromOrder($order, $detail, $order->operator_id, $companyDb);
            
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