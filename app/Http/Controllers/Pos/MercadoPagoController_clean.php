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
            $configMap = DB::connection('mysql_parent')->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $clientId = $configMap['mp_client_id'] ?? null;
            $clientSecret = $configMap['mp_client_secret'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            if (empty($clientId) || empty($clientSecret)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client ID y Client Secret son requeridos.'
                ], 400);
            }
            
            $authUrl = $mode === 'production' 
                ? 'https://auth.mercadopago.com/authorization'
                : 'https://auth-sandbox.mercadopago.com/authorization';
            
            $response = Http::post($authUrl, [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);
            
            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error: ' . ($response->json()['message'] ?? '')
                ], 400);
            }
            
            $data = $response->json();
            
            return response()->json([
                'success' => true,
                'access_token' => $data['access_token'] ?? '',
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
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
        
        $companyId = null;
        $codeVerifier = null;
        
        if ($stateParam) {
            try {
                $stateData = json_decode(base64_decode($stateParam), true);
                $companyId = $stateData['companyId'] ?? null;
                $codeVerifier = $stateData['codeVerifier'] ?? null;
            } catch (\Exception $e) {
                Log::warning('[MercadoPago] Error decoding state', ['error' => $e->getMessage()]);
            }
        }
        
        Log::info('[MercadoPago] Callback received', [
            'company_id' => $companyId,
            'has_code' => !empty($code),
            'has_code_verifier' => !empty($codeVerifier),
            'error' => $error,
        ]);
        
        if ($error) {
            return response()->make('
                <html><body style="font-family: Arial; padding: 40px; text-align: center;">
                    <h2 style="color: #dc3545;">Error: ' . htmlspecialchars($errorDescription ?? $error) . '</h2>
                    <button onclick="window.close()">Cerrar</button>
                </body></html>
            ', 200, ['Content-Type' => 'text/html']);
        }
        
        if (empty($code) || empty($companyId)) {
            return response()->make('
                <html><body style="font-family: Arial; padding: 40px; text-align: center;">
                    <h2 style="color: #dc3545;">Datos faltantes</h2>
                    <button onclick="window.close()">Cerrar</button>
                </body></html>
            ', 200, ['Content-Type' => 'text/html']);
        }
        
        try {
            $configMap = DB::connection('mysql_parent')->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $clientId = $configMap['mp_client_id'] ?? null;
            $clientSecret = $configMap['mp_client_secret'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            if (empty($clientId) || empty($clientSecret)) {
                throw new \Exception('Client ID o Client Secret no configurados');
            }
            
            // Obtener redirect_uri de la base de datos hija
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $childConfig = DB::table('configs')->pluck('value', 'name')->toArray();
            $redirectUri = $childConfig['redirect_uri'] ?? null;
            
            // Restaurar conexión a la DB padre
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            if (empty($redirectUri)) {
                throw new \Exception('redirect_uri no configurado en la empresa');
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
            
            Log::info('[MercadoPago] Exchanging code', [
                'redirect_uri' => $redirectUri,
                'has_code_verifier' => !empty($codeVerifier),
            ]);
            
            $response = Http::asForm()->post($authUrl, $requestData);
            
            if ($response->failed()) {
                throw new \Exception('Error: ' . ($response->json()['message'] ?? $response->body()));
            }
            
            $data = $response->json();
            $accessToken = $data['access_token'] ?? '';
            
            if (empty($accessToken)) {
                throw new \Exception('No se recibió access_token');
            }
            
            // Guardar token en la base de datos hija
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
            
            // Restaurar conexión
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $messageType = 'success';
            $message = 'Token configurado correctamente';
            $tokenHtml = '<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px;">
                <p><strong>Access Token:</strong></p>
                <textarea readonly style="width: 100%; height: 80px; font-family: monospace;">' . htmlspecialchars($accessToken) . '</textarea>
                <p style="color: #28a745; margin-top: 10px;"><strong>✓ Guardado en configuración</strong></p>
            </div>';
            
        } catch (\Exception $e) {
            Log::error('[MercadoPago] Error', ['error' => $e->getMessage()]);
            $messageType = 'error';
            $message = 'Error al configurar token';
            $tokenHtml = '<div style="margin: 20px 0; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;">
                <p style="color: #dc3545;"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
            </div>';
        }
        
        return response()->make('
            <html>
            <head>
                <meta charset="UTF-8">
                <title>MercadoPago - ' . ucfirst($messageType) . '</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 40px; text-align: center; max-width: 600px; margin: 0 auto; }
                    .success { color: #28a745; }
                    .error { color: #dc3545; }
                </style>
            </head>
            <body>
                <h2 class="' . $messageType . '">' . ucfirst($message) . '</h2>
                ' . $tokenHtml . '
                <p>Ya puedes cerrar esta pestaña.</p>
                <button onclick="window.close()">Cerrar</button>
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
                return response()->json(['success' => false, 'message' => 'Faltan datos'], 400);
            }
            
            $configMap = DB::connection('mysql_parent')->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            $clientId = $configMap['mp_client_id'] ?? null;
            $clientSecret = $configMap['mp_client_secret'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
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
            
            $response = Http::asForm()->post($authUrl, $requestData);
            
            if ($response->failed()) {
                return response()->json(['success' => false, 'message' => 'Error'], 400);
            }
            
            $data = $response->json();
            $accessToken = $data['access_token'] ?? '';
            
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            DB::table('configs')->updateOrInsert(
                ['name' => 'mp_access_token'],
                ['value' => $accessToken, 'type' => 'string', 'updated_at' => now()]
            );
            
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            return response()->json(['success' => true, 'access_token' => $accessToken]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function generateQr(Request $request)
    {
        try {
            $externalReference = $request->input('external_reference');
            $amount = $request->input('amount');
            
            if (empty($externalReference) || empty($amount)) {
                return response()->json(['success' => false, 'message' => 'Faltan datos'], 400);
            }
            
            $companyId = $request->input('company_id');
            
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $configMap = DB::table('configs')->pluck('value', 'name')->toArray();
            $accessToken = $configMap['mp_access_token'] ?? null;
            $mode = $configMap['mp_mode'] ?? 'sandbox';
            
            $qrUrl = $mode === 'production'
                ? 'https://api.mercadopago.com/pos/qr'
                : 'https://api.sandbox.mercadopago.com/pos/qr';
            
            $response = Http::withToken($accessToken)->post($qrUrl, [
                'external_reference' => $externalReference,
                'items' => [['title' => 'Pago POS', 'quantity' => 1, 'unit_price' => (float) $amount]],
            ]);
            
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            if ($response->failed()) {
                return response()->json(['success' => false, 'message' => 'Error generando QR'], 400);
            }
            
            return response()->json(['success' => true, 'data' => $response->json()]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    public function webhook(Request $request)
    {
        try {
            $externalReference = $request->input('external_reference');
            $paymentId = $request->input('payment_id');
            
            if (empty($externalReference) || empty($paymentId)) {
                return response()->json(['success' => true]);
            }
            
            $parts = explode('-', $externalReference);
            if (count($parts) !== 3) {
                return response()->json(['success' => true]);
            }
            
            $companyId = $parts[0];
            $orderId = $parts[2];
            
            config(['database.connections.mysql.database' => $companyId]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            DB::table('orders')->where('id', $orderId)->update([
                'status_id' => 3,
                'mp_payment_id' => $paymentId,
                'updated_at' => now(),
            ]);
            
            config(['database.connections.mysql.database' => env('DB_DATABASE')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            return response()->json(['success' => true]);
            
        } catch (\Exception $e) {
            return response()->json(['success' => true]);
        }
    }
}
