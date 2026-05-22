<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class MercadoPagoController extends Controller
{
    public function callback(Request $request)
    {
        $code = $request->query("code");
        $stateParam = $request->query("state");
        $error = $request->query("error");
        
        $companyId = null;
        $codeVerifier = null;
        $redirectUriFromState = null;
        
        if ($stateParam) {
            try {
                $stateData = json_decode(base64_decode($stateParam), true);
                $companyId = $stateData["companyId"] ?? null;
                $codeVerifier = $stateData["codeVerifier"] ?? null;
                $redirectUriFromState = $stateData["redirectUri"] ?? null;
            } catch (\Exception $e) {
                Log::warning("[MercadoPago] Error decoding state", ["error" => $e->getMessage()]);
            }
        }
        
        if ($error) {
            return $this->errorHtml("Error: " . ($request->query("error_description") ?? $error));
        }
        
        if (empty($code) || empty($companyId)) {
            return $this->errorHtml("Datos faltantes (code o companyId)");
        }
        
        try {
            $company = DB::connection("mysql_parent")
                ->table("companies")
                ->where("id", $companyId)
                ->first();
            
            if (!$company) {
                throw new \Exception("Empresa no encontrada (ID: " . $companyId . ")");
            }
            
            $companyDb = $company->db;
            
            $parentConfig = DB::connection("mysql_parent")
                ->table("configs")
                ->pluck("value", "name")
                ->toArray();
            
            $clientId = $parentConfig["mp_client_id"] ?? null;
            $clientSecret = $parentConfig["mp_client_secret"] ?? null;
            $mode = $parentConfig["mp_mode"] ?? "sandbox";
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception("Client ID o Client Secret no configurados en DB padre");
            }
            
            Config::set("database.connections.mysql.database", $companyDb);
            DB::purge("mysql");
            DB::reconnect("mysql");
            
            $childConfig = DB::table("configs")
                ->pluck("value", "name")
                ->toArray();
            
            $redirectUri = $redirectUriFromState ?? $childConfig["redirect_uri"] ?? null;
            
            if (empty($redirectUri)) {
                throw new \Exception("redirect_uri no configurado en la empresa (DB: " . $companyDb . ")");
            }
            
            $authUrl = "https://api.mercadopago.com/oauth/token";
            
            $response = Http::asForm()->post($authUrl, [
                "client_id" => $clientId,
                "client_secret" => $clientSecret,
                "grant_type" => "authorization_code",
                "code" => $code,
                "redirect_uri" => $redirectUri,
                "code_verifier" => $codeVerifier ?? "",
            ]);
            
            if ($response->failed()) {
                throw new \Exception("Error MercadoPago: " . ($response->json()["message"] ?? $response->body()));
            }
            
            $data = $response->json();
            $accessToken = $data["access_token"] ?? "";
            
            if (empty($accessToken)) {
                throw new \Exception("No se recibio access_token");
            }
            
            DB::table("configs")->updateOrInsert(
                ["name" => "mp_access_token"],
                ["value" => $accessToken, "type" => "string", "updated_at" => now(), "created_at" => now()]
            );
            
            Config::set("database.connections.mysql.database", env("DB_DATABASE", "erden"));
            DB::purge("mysql");
            DB::reconnect("mysql");
            
            return $this->successHtml($accessToken, $company);
            
        } catch (\Exception $e) {
            Log::error("[MercadoPago] Error", ["error" => $e->getMessage()]);
            return $this->errorHtml($e->getMessage());
        }
    }
    
    private function successHtml($accessToken, $company)
    {
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>Token Obtenido</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; max-width: 600px; margin:0 auto; }
        .success { color: #28a745; }
        textarea { width: 100%; height: 100px; font-family: monospace; font-size: 11px; margin: 10px 0; }
        button { padding: 10px 20px; cursor: pointer; margin-top: 20px; background: #28a745; color: white; border: none; border-radius: 5px; }
    </style>
    <script>
        window.onload = function() {
            var token = '" . addslashes($accessToken) . "';
            if (window.opener && !window.opener.closed) {
                window.opener.postMessage({ type: 'mp_token_obtained', token: token }, '*');
                setTimeout(function() { window.close(); }, 3000);
            } else {
                setTimeout(function() { window.close(); }, 5000);
            }
        };
    </script>
</head>
<body>
    <h2 class=\"success\">✅ Token obtenido</h2>
    <p><strong>Access Token:</strong></p>
    <textarea readonly>" . htmlspecialchars($accessToken) . "</textarea>
    <p style=\"margin-top: 10px; color: #28a745;\"><strong>✓ Guardado</strong></p>
    <button onclick=\"window.close()\">Cerrar</button>
</body>
</html>";
        
        return response($html, 200, ["Content-Type" => "text/html; charset=UTF-8"]);
    }
    
    private function errorHtml($message)
    {
        $html = "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>Error - MercadoPago</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; max-width: 600px; margin:0 auto; }
        .error { color: #dc3545; }
        button { padding: 10px 20px; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>
    <h2 class=\"error\">Error</h2>
    <p>" . htmlspecialchars($message) . "</p>
    <button onclick=\"window.close()\">Cerrar</button>
</body>
</html>";
        
        return response($html, 400, ["Content-Type" => "text/html; charset=UTF-8"]);
    }
    
    public function exchangeCode(Request $request)
    {
        return response()->json(["success" => false, "message" => "Usa el flujo de nueva pestana"], 400);
    }
    
    public function generateQR(Request $request)
    {
        try {
            $orderId = $request->input('order_id');
            $amount = $request->input('amount');
            $description = $request->input('description', 'Pago POS');
            $companyDb = $request->input('company_db', '');
            $userId = $request->input('user_id', '');
            
            if (empty($orderId) || empty($amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'order_id y amount son requeridos.'
                ], 400);
            }
            
            if (empty($companyDb)) {
                return response()->json([
                    'success' => false,
                    'message' => 'company_db es requerido.'
                ], 400);
            }
            
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $configMap = DB::table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $accessToken = $configMap['mp_access_token'] ?? null;
            
            if (empty($accessToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access Token no configurado en la empresa.'
                ], 400);
            }
            
            // Crear preferencia de pago (como en foodsal)
            $externalReference = $companyDb . '-' . $userId . '-' . $orderId;
            
            $paymentUrl = 'https://api.mercadopago.com/checkout/preferences';
            
            // Build notification URL
            $webhookCode = $configMap['webhook_code'] ?? '';
            $notificationUrl = rtrim(preg_replace('/\/mp\/callback$/', '', $configMap['redirect_uri'] ?? config('app.url')), '/') . '/mp/webhook?company_db=' . $companyDb;
            if (!empty($webhookCode)) {
                $notificationUrl .= '&whc=' . urlencode($webhookCode);
            }
            
            $response = Http::withToken($accessToken)->post($paymentUrl, [
                'items' => [
                    [
                        'id' => (string)$orderId,
                        'title' => $description,
                        'description' => $description,
                        'quantity' => 1,
                        'unit_price' => (float)$amount,
                        'currency_id' => 'ARS',
                    ]
                ],
                'external_reference' => $externalReference,
                // Usar redirect_uri de configs como base para notification_url
                // redirect_uri tiene formato: https://tunnel-url/mp/callback, hay que quitar /mp/callback
                'notification_url' => $notificationUrl,
            ]);
            
            if ($response->failed()) {
                Log::error('[MercadoPago] Payment creation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error al crear pago: ' . ($response->json()['message'] ?? $response->body())
                ], 400);
            }
            
            $pref = $response->json();
            $initPoint = $pref['init_point'] ?? '';
            
            if (empty($initPoint)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibió init_point de MercadoPago'
                ], 400);
            }
            
            Log::info('[MercadoPago] Preference created:', [
                'init_point' => $initPoint,
                'preference_id' => $pref['id'] ?? null,
            ]);
            
            // Generar QR desde la URL (como en foodsal)
            $qrBase64 = $this->generateQrFromUrl($initPoint);
            
            return response()->json([
                'success' => true,
                'qr_base64' => $qrBase64,
                'payment_url' => $initPoint,
            ]);
            
        } catch (\Exception $e) {
            Log::error('[MercadoPago] Error generating QR', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    private function generateQrFromUrl($url)
    {
        try {
            if (empty($url)) {
                Log::warning('[MercadoPago] QR generation failed: URL is empty');
                return null;
            }
            
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($url);
            Log::info('[MercadoPago] Generating QR from URL:', ['url' => $url]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $qrUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $imageData = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode === 200 && $imageData !== false) {
                Log::info('[MercadoPago] QR generated successfully');
                return 'data:image/png;base64,' . base64_encode($imageData);
            }
            
            Log::warning('[MercadoPago] QR generation failed:', [
                'http_code' => $httpCode,
                'curl_error' => $curlError,
            ]);
            return null;
            
        } catch (\Exception $e) {
            Log::warning('[MercadoPago] Error generating QR from URL:', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    public function webhook(Request $request)
    {
        error_log('[MP WEBHOOK ENTRY] ' . $request->method() . ' ' . $request->fullUrl());
        // Log EXTENSIVO para debugging
        Log::info('[MP Webhook] === NUEVO REQUEST ===');
        Log::info('[MP Webhook] Method: ' . $request->method());
        Log::info('[MP Webhook] URL: ' . $request->fullUrl());
        Log::info('[MP Webhook] Headers:', $request->headers->all());
        Log::info('[MP Webhook] Body (raw): ' . $request->getContent());
        Log::info('[MP Webhook] Body (parsed):', $request->all());
        Log::info('[MP Webhook] Query params:', $request->query->all());
        
        $payload = $request->all();
        $rawPayload = $request->getContent();
        $whc = $request->query('whc');
        $companyDb = $request->query('company_db');
        
        // Early payment/order ID extraction for dedup (VPS mode)
        $topic = $payload['topic'] ?? $request->query('topic');
        $data = $payload['data'] ?? [];
        $extractedId = null;
        
        if (in_array($topic, ['payment', 'payment.created'])) {
            if (isset($data['id'])) {
                $extractedId = $data['id'];
            } elseif (isset($payload['resource'])) {
                $resource = $payload['resource'];
                $extractedId = is_numeric($resource) ? $resource : (preg_match('/\/(\d+)$/', $resource, $m) ? $m[1] : null);
            }
        }
        if ($topic === 'merchant_order') {
            if (isset($payload['resource'])) {
                $resource = $payload['resource'];
                $extractedId = is_numeric($resource) ? $resource : (preg_match('/\/(\d+)$/', $resource, $m) ? $m[1] : null);
            }
            if (!$extractedId && isset($payload['id'])) {
                $extractedId = $payload['id'];
            }
        }
        
        // If webhook_code (whc) is present
        if (!empty($whc)) {
            if (!empty($companyDb)) {
                // VPS mode: store for agent relay with dedup
                Log::info('[MP Webhook] whc=' . $whc . ' detected (VPS mode), storing for agent relay');
                
                try {
                    // Dedup by (webhook_code, topic, payment_id) if ID extracted,
                    // otherwise fallback to raw_payload (across all statuses)
                    $existing = false;
                    if (!empty($extractedId)) {
                        $existing = DB::connection('mysql_parent')
                            ->table('webhooks_jobs')
                            ->where('webhook_code', $whc)
                            ->where('topic', $topic)
                            ->where('payment_id', $extractedId)
                            ->exists();
                    }
                    
                    if (!$existing) {
                        $existing = DB::connection('mysql_parent')
                            ->table('webhooks_jobs')
                            ->where('webhook_code', $whc)
                            ->where('raw_payload', $rawPayload)
                            ->exists();
                    }
                    
                    if (!$existing) {
                        DB::connection('mysql_parent')
                            ->table('webhooks_jobs')
                            ->insert([
                                'webhook_code' => $whc,
                                'company_db' => $companyDb,
                                'raw_payload' => $rawPayload,
                                'topic' => $topic,
                                'payment_id' => $extractedId,
                                'status' => 'pending',
                                'created_at' => now(),
                            ]);
                        
                        Log::info('[MP Webhook] Stored in webhooks_jobs for agent relay');
                    } else {
                        Log::info('[MP Webhook] Duplicate webhook, skipping');
                    }
                } catch (\Exception $e) {
                    Log::error('[MP Webhook] Error storing webhook job: ' . $e->getMessage());
                }
                
                return response()->json(['status' => 'ok']);
            }
            
            // Local mode: whc present, company_db absent (forwarded by agent)
            // Look up company_db from parent companies table using webhook_code
            Log::info('[MP Webhook] whc=' . $whc . ' detected (local mode), looking up company');
            
            try {
                $company = DB::connection('mysql_parent')
                    ->table('companies')
                    ->where('webhook_code', $whc)
                    ->first();
                
                if (!$company || empty($company->db)) {
                    Log::warning('[MP Webhook] No company found for webhook_code: ' . $whc);
                    return response()->json(['status' => 'ok']);
                }
                
                $companyDb = $company->db;
                Log::info('[MP Webhook] Resolved company_db=' . $companyDb . ' from whc=' . $whc);
            } catch (\Exception $e) {
                Log::error('[MP Webhook] Error looking up company: ' . $e->getMessage());
                return response()->json(['status' => 'ok']);
            }
        }
        
        try {
            // Obtener payment_id del webhook
            $paymentId = null;
            $topic = $payload['topic'] ?? $request->query('topic');
            $data = $payload['data'] ?? [];
            
            Log::info('[MP Webhook] Topic: ' . $topic);
            
            // Manejar topic=payment
            if ($topic === 'payment') {
                if (isset($data['id'])) {
                    $paymentId = $data['id'];
                    Log::info('[MP Webhook] Payment ID from data.id: ' . $paymentId);
                }
                
                if (!$paymentId && isset($payload['resource'])) {
                    $resource = $payload['resource'];
                    $paymentId = is_numeric($resource) ? $resource : (preg_match('/\/(\d+)$/', $resource, $m) ? $m[1] : null);
                    Log::info('[MP Webhook] Payment ID from resource: ' . $paymentId);
                }
            }
            
            // Manejar topic=merchant_order - necesitamos obtener el payment_id del merchant_order
            if ($topic === 'merchant_order') {
                $merchantOrderId = null;
                if (isset($payload['resource'])) {
                    $resource = $payload['resource'];
                    $merchantOrderId = is_numeric($resource) ? $resource : (preg_match('/\/(\d+)$/', $resource, $m) ? $m[1] : null);
                }
                if (!$merchantOrderId && isset($payload['id'])) {
                    $merchantOrderId = $payload['id'];
                }
                
                Log::info('[MP Webhook] Merchant Order ID: ' . $merchantOrderId);
                
                if ($merchantOrderId) {
                    // Obtener access token primero para consultar el merchant_order
                    if (!empty($companyDb)) {
                        Config::set('database.connections.mysql.database', $companyDb);
                        DB::purge('mysql');
                        DB::reconnect('mysql');
                        $accessToken = DB::table('configs')->where('name', 'mp_access_token')->value('value');
                        
                        if ($accessToken) {
                            $moResponse = Http::withToken($accessToken)->get("https://api.mercadolibre.com/merchant_orders/{$merchantOrderId}");
                            if ($moResponse->successful()) {
                                $moData = $moResponse->json();
                                $payments = $moData['payments'] ?? [];
                                if (!empty($payments[0]['id'])) {
                                    $paymentId = $payments[0]['id'];
                                    Log::info('[MP Webhook] Payment ID from merchant_order: ' . $paymentId);
                                }
                            }
                        }
                    }
                }
            }
            
            if (!$paymentId) {
                Log::warning('[MP Webhook] NO se encontró payment_id en:', $payload);
                return response()->json(['status' => 'ok']);
            }
            
            Log::info('[MP Webhook] company_db from URL: ' . ($companyDb ?? 'NULL'));
            
            if (empty($companyDb)) {
                Log::warning('[MP Webhook] No company_db in URL');
                return response()->json(['status' => 'ok']);
            }
            
            // Cambiar a la DB de la empresa
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
            Log::info('[MP Webhook] Switched to DB: ' . $companyDb);
            
            // Obtener token de la empresa
            $accessToken = DB::table('configs')->where('name', 'mp_access_token')->value('value');
            if (empty($accessToken)) {
                Log::warning('[MP Webhook] No access token for DB: ' . $companyDb);
                return response()->json(['status' => 'ok']);
            }
            
            // Consultar el pago a MercadoPago
            Log::info('[MP Webhook] Fetching payment from MP:', ['payment_id' => $paymentId]);
            $response = Http::withToken($accessToken)->get("https://api.mercadopago.com/v1/payments/{$paymentId}");
            
            if ($response->failed()) {
                Log::error('[MP Webhook] Error fetching payment:', ['response' => $response->body()]);
                return response()->json(['status' => 'ok']);
            }
            
            $payment = $response->json();
            Log::info('[MP Webhook] Payment data:', $payment);
            
            $paymentStatus = $payment['status'] ?? '';
            $externalRef = $payment['external_reference'] ?? '';
            
            Log::info('[MP Webhook] Payment status: ' . $paymentStatus);
            Log::info('[MP Webhook] External reference: ' . $externalRef);
            
            if ($paymentStatus !== 'approved') {
                Log::info('[MP Webhook] Payment not approved, ignoring');
                return response()->json(['status' => 'ok']);
            }
            
            // Parsear external_reference: companyDb-operatorId-orderId
            $parts = explode('-', $externalRef, 3);
            if (count($parts) < 3) {
                Log::warning('[MP Webhook] Invalid external_reference: ' . $externalRef);
                return response()->json(['status' => 'ok']);
            }
            
            [$extCompanyDb, $operatorId, $orderId] = $parts;
            Log::info('[MP Webhook] Parsed:', [
                'ext_company_db' => $extCompanyDb,
                'operator_id' => $operatorId,
                'order_id' => $orderId
            ]);
            
            // Verificar que la DB coincida (seguridad)
            if ($extCompanyDb !== $companyDb) {
                Log::warning('[MP Webhook] Company DB mismatch', [
                    'url' => $companyDb,
                    'payment' => $extCompanyDb
                ]);
                return response()->json(['status' => 'ok']);
            }
            
            // Actualizar la orden
            DB::table('orders')->where('id', $orderId)->update([
                'status_id' => 3,
                'paid' => 1,
                'mp_payment_id' => $paymentId,
                'mp_transaction_amount' => $payment['transaction_details']['net_received_amount'] ?? $payment['transaction_amount'] ?? null,
                'updated_at' => now(),
            ]);
            
            Log::info('[MP Webhook] Order updated successfully');
            
            // Obtener la orden actualizada
            $updatedOrder = DB::table('orders')->where('id', $orderId)->first();
            
            // Emitir evento para notificar al cajero
            event(new \App\Events\OrderPaid($updatedOrder, $operatorId));
            Log::info('[MP Webhook] OrderPaid event fired for operator: ' . $operatorId);
            
        } catch (\Exception $e) {
            Log::error('[MP Webhook] Exception:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        return response()->json(['status' => 'ok']);
    }
}
