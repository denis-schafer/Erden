<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Events\MpCodeReceived;

class MercadoPagoController extends Controller
{
    public function getAccessToken(Request $request)
    {
        try {
            // Get credentials from parent DB
            $configMap = DB::connection('mysql_parent')->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $clientId = $configMap['mp_client_id'] ?? null;
            $clientSecret = $configMap['mp_client_secret'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            if (empty($clientId) || empty($clientSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client ID y Client Secret son requeridos. Configúralos en Configuración Global.'
                ], 400);
            }
            
            // Determine OAuth URL based on mode
            $authUrl = $mode === 'production' 
                ? 'https://auth.mercadopago.com/authorization'
                : 'https://auth-sandbox.mercadopago.com/authorization';
            
            // Make OAuth request
            $response = Http::post($authUrl, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);
            
            if ($response->failed()) {
                Log::error('[MercadoPago] OAuth failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener token: ' . ($response->json()['message'] ?? 'Error desconocido')
                ], 400);
            }
            
            $data = $response->json();
            $accessToken = $data['access_token'] ?? '';
            
            if (empty($accessToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibió access_token'
                ], 400);
            }
            
            return response()->json([
                'success' => true,
                'access_token' => $accessToken,
                'mode' => $mode,
            ]);
            
        } catch (\Exception $e) {
            Log::error('[MercadoPago] Error getting access token', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function getConfig(Request $request)
    {
        try {
            $configMap = DB::connection('mysql_parent')->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            return response()->json([
                'success' => true,
                'has_client_id' => !empty($configMap['mp_client_id']),
                'has_client_secret' => !empty($configMap['mp_client_secret']),
                'mode' => $configMap['mp_mode'] ?? 'sandbox',
                'has_token' => !empty($configMap['mp_access_token']),
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public function callback(Request $request)
    {
        $code = $request->query('code');
        $stateParam = $request->query('state');
        $error = $request->query('error');
        $errorDescription = $request->query('error_description');
        
        // Decodificar state (base64 JSON con companyId y codeVerifier)
        $companyId = null;
        $codeVerifier = null;
        
        if ($stateParam) {
            try {
                $stateData = json_decode(base64_decode($stateParam), true);
                $companyId = $stateData['companyId'] ?? null;
                $codeVerifier = $stateData['codeVerifier'] ?? null;
            } catch (\Exception $e) {
                Log::warning('[MercadoPago] Error decoding state parameter', ['error' => $e->getMessage()]);
            }
        }
        
        Log::info('[MercadoPago] Callback received', [
            'code' => $code ? substr($code, 0, 10) . '...' : 'none',
            'company_id' => $companyId,
            'has_code_verifier' => !empty($codeVerifier),
            'error' => $error,
            'error_description' => $errorDescription,
        ]);
        
        if ($error) {
            return response()->make('
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Error - MercadoPago</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; }
                        .error { color: #dc3545; }
                        button { padding: 10px 20px; cursor: pointer; }
                    </style>
                </head>
                <body>
                    <h2 class="error">Error: ' . htmlspecialchars($errorDescription ?? $error) . '</h2>
                    <p>Por favor, intentalo de nuevo.</p>
                    <button onclick="window.close()">Cerrar</button>
                </body>
                </html>
            ', 200, ['Content-Type' => 'text/html']);
        }
        
        if (empty($code) || empty($companyId)) {
            return response()->make('
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Error - MercadoPago</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; }
                        .error { color: #dc3545; }
                    </style>
                </head>
                <body>
                    <h2 class="error">Faltan datos</h2>
                    <p>code: ' . ($code ? 'presente' : 'faltante') . '</p>
                    <p>company_id: ' . ($companyId ?: 'faltante') . '</p>
                    <button onclick="window.close()">Cerrar</button>
                </body>
                </html>
            ', 200, ['Content-Type' => 'text/html']);
        }
        
        // Intercambiar código por access token
        try {
            $configMap = DB::connection('mysql_parent')->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $clientId = $configMap['mp_client_id'] ?? null;
            $clientSecret = $configMap['mp_client_secret'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception('Client ID o Client Secret no configurados en DB padre');
            }
            
            // Obtener redirect_uri de la base de datos hija (company)
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $childConfig = DB::table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $redirectUri = $childConfig['redirect_uri'] ?? null;
            
            // Restaurar conexión a la DB padre para el intercambio
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            if (empty($redirectUri)) {
                throw new \Exception('redirect_uri no configurado en la base de datos de la empresa');
            }
            
            $authUrl = $mode === 'production'
                ? 'https://api.mercadopago.com/oauth/token'
                : 'https://api.sandbox.mercadopago.com/oauth/token';
            
            $requestData = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $codeVerifier ?? '',
            ];
            
            if ($mode === 'sandbox') {
                $requestData['test_token'] = 'true';
            }
            
            Log::info('[MercadoPago] Exchanging code in callback', [
                'company_id' => $companyId,
                'mode' => $mode,
                'redirect_uri' => $redirectUri,
            ]);
            
            // MercadoPago OAuth expects form-data (application/x-www-form-urlencoded)
            $response = Http::asForm()->post($authUrl, $requestData);
            
            if ($response->failed()) {
                throw new \Exception('Error al intercambiar código: ' . ($response->json()['message'] ?? $response->body()));
            }
            
            $data = $response->json();
            $accessToken = $data['access_token'] ?? '';
            
            if (empty($accessToken)) {
                throw new \Exception('No se recibió access_token');
            }
            
            // Guardar en la base de datos de la empresa
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            DB::table('configs')->updateOrInsert(
                ['name' => 'mp_access_token'],
                ['value' => $accessToken, 'type' => 'string', 'updated_at' => now()]
            );
            
            if (isset($data['expires_in'])) {
                $expiresAt = now()->addSeconds($data['expires_in']);
                DB::table('configs')->updateOrInsert(
                    ['name' => 'mp_token_expires_at'],
                    ['value' => $expiresAt->toDateTimeString(), 'type' => 'string', 'updated_at' => now()]
                );
            }
            
            // Restaurar conexión a la DB padre
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $tokenHtml = '<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">'
                . '<p><strong>Access Token:</strong></p>'
                . '<textarea readonly style="width: 100%; height: 80px; font-family: monospace; font-size: 12px;">' . htmlspecialchars($accessToken) . '</textarea>'
                . '<p style="margin-top: 10px; color: #28a745;"><strong>✓ Guardado en configuración (mp_access_token)</strong></p>'
                . '</div>';
            
            $message = 'Token configurado correctamente';
            $messageType = 'success';
            
        } catch (\Exception $e) {
            Log::error('[MercadoPago] Error exchanging code', ['error' => $e->getMessage()]);
            $tokenHtml = '<div style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;">'
                . '<p class="error"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>'
                . '</div>';
            $message = 'Error al configurar token';
            $messageType = 'error';
        }
        
        return response()->make('
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>MercadoPago OAuth - ' . ucfirst($messageType) . '</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 40px; text-align: center; max-width: 600px; margin:0 auto; }
                    .success { color: #28a745; }
                    .error { color: #dc3545; }
                    button { padding: 10px 20px; cursor: pointer; margin-top: 20px; }
                    pre { text-align: left; background: #f8f9fa; padding: 10px; border-radius: 5px; overflow-wrap: break-word; }
                </style>
            </head>
            <body>
                <h2 class="' . $messageType . '">' . ucfirst($message) . '</h2>
                ' . $tokenHtml . '
                <p>Ya puedes cerrar esta pestaña y continuar en el panel de configuración.</p>
                <button onclick="window.close()">Cerrar pestaña</button>
            </body>
            </html>
        ', 200, ['Content-Type' => 'text/html']);
    }
    
    public function exchangeCode(Request $request)
    {
        try {
            $code = $request->input('code');
            $companyId = $request->input('company_id');
            
            if (empty($code) || empty($companyId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Code y company_id son requeridos.'
                ], 400);
            }
            
            $configMap = DB::connection('mysql_parent')->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $clientId = $configMap['mp_client_id'] ?? null;
            $clientSecret = $configMap['mp_client_secret'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            if (empty($clientId) || empty($clientSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client ID y Client Secret no configurados en DB padre.'
                ], 400);
            }
            
            $authUrl = $mode === 'production' 
                ? 'https://api.mercadopago.com/oauth/token'
                : 'https://api.sandbox.mercadopago.com/oauth/token';
            
            $requestData = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $request->input('redirect_uri', ''),
                'code_verifier' => $request->input('code_verifier', ''),
            ];
            
            if ($mode === 'sandbox') {
                $requestData['test_token'] = 'true';
            }
            
            Log::info('[MercadoPago] Exchange request', [
                'client_id' => $clientId,
                'mode' => $mode,
                'authUrl' => $authUrl,
                'has_code_verifier' => !empty($requestData['code_verifier']),
            ]);
            
            $response = Http::post($authUrl, $requestData);
            
            if ($response->failed()) {
                Log::error('[MercadoPago] Exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error al canjear código: ' . ($response->json()['message'] ?? 'Error desconocido')
                ], 400);
            }
            
            $data = $response->json();
            $accessToken = $data['access_token'] ?? '';
            
            if (empty($accessToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibió access_token'
                ], 400);
            }
            
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            DB::table('configs')->updateOrInsert(
                ['name' => 'mp_access_token'],
                ['value' => $accessToken, 'type' => 'string', 'updated_at' => now()]
            );
            
            if (isset($data['expires_in'])) {
                $expiresAt = now()->addSeconds($data['expires_in']);
                DB::table('configs')->updateOrInsert(
                    ['name' => 'mp_token_expires_at'],
                    ['value' => $expiresAt->toDateTimeString(), 'type' => 'string', 'updated_at' => now()]
                );
            }
            
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            return response()->json([
                'success' => true,
                'message' => 'Token configurado correctamente',
                'access_token' => $accessToken,
            ]);
            
        } catch (\Exception $e) {
            Log::error('[MercadoPago] Error exchanging code', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function generateQr(Request $request)
    {
        try {
            $externalReference = $request->input('external_reference');
            $amount = $request->input('amount');
            $description = $request->input('description', 'Pago POS');
            
            if (empty($externalReference) || empty($amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'external_reference y amount son requeridos.'
                ], 400);
            }
            
            $companyId = $request->input('company_id', '');
            
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $configMap = DB::table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $accessToken = $configMap['mp_access_token'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            if (empty($accessToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access Token no configurado.'
                ], 400);
            }
            
            $qrUrl = $mode === 'production'
                ? 'https://api.mercadopago.com/pos/qr'
                : 'https://api.sandbox.mercadopago.com/pos/qr';
            
            $posId = $configMap['mp_pos_id'] ?? null;
            if (empty($posId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'POS ID no configurado.'
                ], 400);
            }
            
            $response = Http::withToken($accessToken)->post($qrUrl, [
                'external_reference' => $externalReference,
                'notification_url' => url('/mp/webhook'),
                'items' => [
                    [
                        'title' => $description,
                        'quantity' => 1,
                        'unit_price' => (float) $amount,
                        'currency_id' => 'ARS',
                    ]
                ],
            ]);
            
            if ($response->failed()) {
                Log::error('[MercadoPago] QR generation failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Error al generar QR: ' . ($response->json()['message'] ?? 'Error desconocido')
                ], 400);
            }
            
            $data = $response->json();
            
            return response()->json([
                'success' => true,
                'qr_data' => $data['qr_data'] ?? '',
                'ticket_url' => $data['ticket_url'] ?? '',
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
    
    public function webhook(Request $request)
    {
        try {
            $data = $request->all();
            
            Log::info('[MercadoPago] Webhook received', [
                'data' => $data,
            ]);
            
            $externalReference = $data['external_reference'] ?? null;
            $paymentId = $data['payment_id'] ?? null;
            $status = $data['status'] ?? null;
            
            if (empty($externalReference) || empty($paymentId)) {
                return response()->json(['success' => true]);
            }
            
            $parts = explode('-', $externalReference);
            if (count($parts) !== 3) {
                return response()->json(['success' => true]);
            }
            
            $companyId = $parts[0];
            $userId = $parts[1];
            $orderId = $parts[2];
            
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            if ($status === 'approved') {
                DB::table('orders')
                    ->where('id', $orderId)
                    ->update([
                        'status_id' => 3,
                        'mp_payment_id' => $paymentId,
                        'mp_transaction_amount' => $data['transaction_amount'] ?? null,
                        'updated_at' => now(),
                    ]);
                
                event(new \App\Events\OrderUpdated($orderId, $companyId));
            }
            
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            Log::error('[MercadoPago] Webhook error', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['success' => true]);
        }
    }
    
    public function getPaymentDetails(Request $request)
    {
        try {
            $paymentId = $request->input('payment_id');
            $companyId = $request->input('company_id');
            
            if (empty($paymentId) || empty($companyId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'payment_id y company_id son requeridos.'
                ], 400);
            }
            
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $configMap = DB::table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $accessToken = $configMap['mp_access_token'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            if (empty($accessToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access Token no configurado.'
                ], 400);
            }
            
            $paymentUrl = $mode === 'production'
                ? "https://api.mercadopago.com/v1/payments/{$paymentId}"
                : "https://api.sandbox.mercadopago.com/v1/payments/{$paymentId}";
            
            $response = Http::withToken($accessToken)->get($paymentUrl);
            
            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener detalles del pago.'
                ], 400);
            }
            
            $data = $response->json();
            
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            return response()->json([
                'success' => true,
                'payment' => $data,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
