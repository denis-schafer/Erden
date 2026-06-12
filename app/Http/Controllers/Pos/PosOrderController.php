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
use Illuminate\Support\Facades\Http;

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

        // Run tasks synchronously (fast: DB insert + print job generation, no Point/QR)
        $this->createPrintJob($order, $validated['detail'], $validated['operator_id']);
        event(new OrderCreated((array) $order));
        $this->queueSync('orders', 'created', $order, ['operator_id' => 'users', 'status_id' => 'status_orders']);

        return response()->json([
            'id' => $id, 
            'message' => 'Pedido creado',
        ]);
    }

    public function sendOrderToPoint($orderId): void
    {
        $orderId = (int) $orderId;
        try {
            $order = DB::table('orders')->find($orderId);
            if (!$order) {
                Log::warning('[MercadoPagoPoint] Order not found for sendToPoint', [
                    'order_id' => $orderId,
                ]);
                return;
            }
            $this->sendToPointTerminal($order, $order->operator_id);
        } catch (\Exception $e) {
            Log::error('[MercadoPagoPoint] sendOrderToPoint failed', [
                'error' => $e->getMessage(),
                'order_id' => $orderId,
            ]);
        }
    }

    public function checkPaymentStatus($orderId)
    {
        $orderId = (int) $orderId;
        $order = DB::table('orders')->where('id', $orderId)->first(['id', 'mp_order_id', 'paid', 'status_id', 'operator_id']);

        if (!$order) {
            return response()->json(['error' => 'Pedido no encontrado'], 404);
        }

        if ($order->paid) {
            return response()->json(['paid' => true, 'status_id' => $order->status_id]);
        }

        // Check 1: MP Point API (if order was sent to Point terminal)
        if (!empty($order->mp_order_id)) {
            try {
                $accessToken = DB::table('configs')->where('name', 'mp_access_token')->value('value');
                if (!empty($accessToken)) {
                    $pointService = new MercadoPagoPointService($accessToken);
                    $statusResult = $pointService->getOrder($order->mp_order_id);

                    if (!empty($statusResult) && isset($statusResult['status']) && $statusResult['status'] === 'processed') {
                        DB::table('orders')->where('id', $orderId)->update([
                            'status_id' => 3,
                            'paid' => 1,
                            'mp_payment_id' => $statusResult['transactions']['payments'][0]['id'] ?? null,
                            'updated_at' => now(),
                        ]);

                        $updatedOrder = DB::table('orders')->where('id', $orderId)->first();
                        $this->queueSync('orders', 'updated', $updatedOrder, ['operator_id' => 'users', 'status_id' => 'status_orders']);
                        event(new \App\Events\OrderPaid($updatedOrder, $order->operator_id));

                        return response()->json(['paid' => true, 'status_id' => 3, 'mp_payment_id' => $statusResult['transactions']['payments'][0]['id'] ?? null]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('[MercadoPagoPoint] Payment check error', [
                    'error' => $e->getMessage(),
                    'order_id' => $orderId,
                ]);
            }
        }

        // Check 2: VPS webhooks via API (QR payments)
        if ($this->checkWebhooksForOrder($order)) {
            $updated = DB::table('orders')->where('id', $orderId)->first();
            return response()->json(['paid' => true, 'status_id' => 3, 'mp_payment_id' => $updated->mp_payment_id]);
        }

        return response()->json(['paid' => false]);
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
                Log::info('[MercadoPagoPoint] Skipping Point terminal', [
                    'reason' => !$operator ? 'operator_not_found' : (empty($operator->posnet_id) ? 'no_posnet_id' : 'mercadopago_qr_disabled'),
                    'operator_id' => $operatorId,
                    'order_id' => $order->id ?? null,
                ]);
                return;
            }

            Log::info('[MercadoPagoPoint] Preparing to send order to terminal', [
                'order_id' => $order->id,
                'operator_id' => $operatorId,
                'operator_username' => $operator->username,
                'terminal_id' => $operator->posnet_id,
                'order_total' => $order->total,
                'current_db' => config('database.connections.mysql.database'),
            ]);

            $accessToken = DB::table('configs')->where('name', 'mp_access_token')->value('value');
            if (empty($accessToken)) {
                Log::warning('[MercadoPagoPoint] No mp_access_token found for company', [
                    'current_db' => config('database.connections.mysql.database'),
                    'configs_table' => DB::table('configs')->pluck('name')->toArray(),
                ]);
                return;
            }

            Log::info('[MercadoPagoPoint] Access token retrieved', [
                'token_prefix' => substr($accessToken, 0, 10) . '...',
                'source_db' => config('database.connections.mysql.database'),
            ]);

            $companyDb = $this->resolveCompanyDb();
            $externalReference = ($companyDb ?? 'unknown') . '-' . $operatorId . '-' . $order->id;
            $amount = number_format($order->total, 2, '.', '');
            $description = 'Pedido #' . $order->id;

            Log::info('[MercadoPagoPoint] Calling createOrder', [
                'external_reference' => $externalReference,
                'amount' => $amount,
                'company_db' => $companyDb,
                'description' => $description,
            ]);

            $pointService = new MercadoPagoPointService($accessToken);
            $result = $pointService->createOrder(
                $operator->posnet_id,
                $amount,
                $externalReference,
                $description
            );

            if ($result['success']) {
                $mpOrderId = $result['data']['id'] ?? null;
                Log::info('[MercadoPagoPoint] Order sent to terminal', [
                    'order_id' => $order->id,
                    'operator' => $operator->username,
                    'terminal' => $operator->posnet_id,
                    'mp_order_id' => $mpOrderId,
                    'mp_status' => $result['data']['status'] ?? null,
                ]);

                // Save MP Order ID for later payment status checks
                if ($mpOrderId) {
                    try {
                        DB::table('orders')->where('id', $order->id)->update([
                            'mp_order_id' => $mpOrderId,
                            'updated_at' => now(),
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('[MercadoPagoPoint] Failed to save mp_order_id', [
                            'error' => $e->getMessage(),
                            'order_id' => $order->id,
                            'mp_order_id' => $mpOrderId,
                        ]);
                    }
                }
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
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function checkPendingPayments()
    {
        $pendingOrders = DB::table('orders')
            ->where('paid', 0)
            ->where('status_id', '!=', 2)
            ->where('created_at', '>=', now()->subDay())
            ->get(['id', 'operator_id']);

        $updated = 0;
        foreach ($pendingOrders as $order) {
            if ($this->checkWebhooksForOrder($order)) {
                $updated++;
            }
        }

        return response()->json(['updated' => $updated]);
    }

    private function checkWebhooksForOrder($order): bool
    {
        try {
            $remoteUrl = DB::table('configs')->where('name', 'remote_url')->value('value');
            $remoteKey = DB::table('configs')->where('name', 'remote_key')->value('value');
            $webhookCode = DB::table('configs')->where('name', 'webhook_code')->value('value');
            $accessToken = DB::table('configs')->where('name', 'mp_access_token')->value('value');
            $companyDb = $this->resolveCompanyDb();

            if (empty($remoteUrl) || empty($remoteKey) || empty($webhookCode) || empty($accessToken) || empty($companyDb)) {
                return false;
            }

            // Fetch pending webhooks from VPS using existing webhook-jobs endpoint
            $response = Http::timeout(10)
                ->withHeaders(['X-Print-Agent-Key' => $remoteKey])
                ->get(rtrim($remoteUrl, '/') . '/pos/webhooks-jobs/pending');

            if (!$response->successful()) {
                return false;
            }

            $webhooks = $response->json() ?? [];

            foreach ($webhooks as $job) {
                // Parse raw_payload to extract payment info
                $payload = is_string($job['raw_payload'] ?? null) 
                    ? json_decode($job['raw_payload'], true) 
                    : ($job['raw_payload'] ?? []);
                
                if (empty($payload)) continue;

                $topic = $job['topic'] ?? $payload['topic'] ?? '';
                $paymentId = null;

                if ($topic === 'payment') {
                    $paymentId = $payload['data']['id'] ?? null;
                } elseif ($topic === 'merchant_order') {
                    $merchantOrderId = $payload['data']['id'] ?? null;
                    if ($merchantOrderId) {
                        $moResponse = Http::withToken($accessToken)
                            ->timeout(10)
                            ->get("https://api.mercadopago.com/merchant_orders/{$merchantOrderId}");
                        if ($moResponse->successful()) {
                            $moData = $moResponse->json();
                            $payments = $moData['payments'] ?? [];
                            $paymentId = $payments[0]['id'] ?? null;
                        }
                    }
                }

                if (!$paymentId) continue;

                // Fetch payment details from MP API
                $mpResponse = Http::withToken($accessToken)
                    ->timeout(10)
                    ->get("https://api.mercadopago.com/v1/payments/{$paymentId}");

                if (!$mpResponse->successful()) continue;

                $payment = $mpResponse->json();
                if (($payment['status'] ?? '') !== 'approved') continue;

                $extRef = $payment['external_reference'] ?? '';
                if (empty($extRef)) continue;

                // Parse external_reference: {companyDb}-{operatorId}-{orderId}
                $parts = explode('-', $extRef, 3);
                if (count($parts) < 3) continue;

                [$extDb, $extOp, $extOrderId] = $parts;

                if ((int)$extOrderId !== $order->id || $extDb !== $companyDb) continue;

                // MATCH! Update order as paid
                DB::table('orders')->where('id', $order->id)->update([
                    'status_id' => 3,
                    'paid' => 1,
                    'mp_payment_id' => $paymentId,
                    'mp_transaction_amount' => $payment['transaction_details']['net_received_amount'] ?? $payment['transaction_amount'] ?? null,
                    'updated_at' => now(),
                ]);

                // ACK webhook on VPS (mark as forwarded/processed)
                Http::timeout(5)
                    ->withHeaders(['X-Print-Agent-Key' => $remoteKey])
                    ->post(rtrim($remoteUrl, '/') . "/pos/webhooks-jobs/{$job['id']}/ack");

                // Fire events
                $updatedOrder = DB::table('orders')->where('id', $order->id)->first();
                $this->queueSync('orders', 'updated', $updatedOrder, ['operator_id' => 'users', 'status_id' => 'status_orders']);
                event(new \App\Events\OrderPaid($updatedOrder, $order->operator_id));

                return true;
            }
        } catch (\Exception $e) {
            Log::error('[CheckWebhooks] Error: ' . $e->getMessage());
        }

        return false;
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