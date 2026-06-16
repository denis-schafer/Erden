<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class QuotaMercadoPagoController extends Controller
{
    public function createPreference(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $parts = explode(':', base64_decode($token));
        $userId = $parts[0] ?? null;
        $companyDb = $parts[1] ?? Config::get('database.connections.mysql.database');

        if (!$userId) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $validated = $request->validate([
            'quota_ids' => 'required|array|min:1',
            'quota_ids.*' => 'integer',
        ]);

        $quotas = DB::table('quotas')
            ->whereIn('id', $validated['quota_ids'])
            ->where('partner_id', $userId)
            ->where('status', 'pending')
            ->get();

        if ($quotas->isEmpty()) {
            return response()->json(['message' => 'No hay cuotas pendientes para pagar'], 400);
        }

        // Check pool fee constraint: pool fees require all regular quotas to be paid
        $hasPoolFee = $quotas->contains('type', 'pool_fee');
        if ($hasPoolFee) {
            $planId = $quotas->first()->quota_plan_id;
            $pendingRegular = DB::table('quotas')
                ->where('partner_id', $userId)
                ->where('quota_plan_id', $planId)
                ->where('type', 'regular')
                ->where('status', 'pending')
                ->whereNotIn('id', $validated['quota_ids'])
                ->count();

            if ($pendingRegular > 0) {
                return response()->json([
                    'message' => 'Debe pagar todas las cuotas regulares antes de pagar los derechos de pileta',
                ], 400);
            }
        }

        $totalAmount = $quotas->sum('amount');

        $partner = DB::table('users')->find($userId);
        $dni = $partner->dni ?? '';
        $description = 'Pago de cuotas' . ($dni ? " - {$dni}" : '');

        $accessToken = DB::table('quota_configs')->where('name', 'mp_access_token')->value('value');

        // Fallback to POS configs table (some OAuth flows save there)
        if (empty($accessToken)) {
            $accessToken = DB::table('configs')->where('name', 'mp_access_token')->value('value');
        }

        if (empty($accessToken)) {
            return response()->json(['message' => 'MercadoPago no configurado'], 400);
        }

        $externalReference = $companyDb . '-' . $userId . '-' . time();
        $notificationUrl = url('/mp/webhook?company_db=' . $companyDb);

        try {
            $mpResponse = Http::withToken($accessToken)->post('https://api.mercadopago.com/checkout/preferences', [
                'items' => [
                    [
                        'title' => $description,
                        'quantity' => 1,
                        'unit_price' => (float)$totalAmount,
                        'currency_id' => 'ARS',
                    ]
                ],
                'external_reference' => $externalReference,
                'notification_url' => $notificationUrl,
                'back_urls' => [
                    'success' => url('/asociados?status=success'),
                    'failure' => url('/asociados?status=failure'),
                    'pending' => url('/asociados?status=pending'),
                ],
                'auto_return' => 'approved',
            ]);

            if ($mpResponse->failed()) {
                Log::error('[QuotaMP] Error creating preference', [
                    'status' => $mpResponse->status(),
                    'body' => $mpResponse->body(),
                ]);
                return response()->json(['message' => 'Error al crear pago en MercadoPago'], 500);
            }

            $preference = $mpResponse->json();
            $preferenceId = $preference['id'] ?? null;
            $initPoint = $preference['init_point'] ?? null;

            if (!$initPoint) {
                return response()->json(['message' => 'No se recibió punto de inicio de MP'], 500);
            }

            $now = now();
            $paymentId = DB::table('quota_payments')->insertGetId([
                'partner_id' => $userId,
                'total_amount' => $totalAmount,
                'payment_method' => 'mercadopago',
                'mp_preference_id' => $preferenceId,
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($quotas as $quota) {
                DB::table('quota_payment_items')->insert([
                    'quota_payment_id' => $paymentId,
                    'quota_id' => $quota->id,
                    'amount' => $quota->amount,
                ]);

                DB::table('quotas')->where('id', $quota->id)->update([
                    'mp_preference_id' => $preferenceId,
                    'updated_at' => $now,
                ]);
            }

            return response()->json([
                'success' => true,
                'init_point' => $initPoint,
                'preference_id' => $preferenceId,
                'total_amount' => $totalAmount,
            ]);
        } catch (\Exception $e) {
            Log::error('[QuotaMP] Exception: ' . $e->getMessage());
            return response()->json(['message' => 'Error de conexión con MercadoPago'], 500);
        }
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');
        $stateParam = $request->query('state');
        $error = $request->query('error');

        $companyId = null;
        $codeVerifier = null;
        $redirectUri = null;

        if ($stateParam) {
            try {
                $stateData = json_decode(base64_decode($stateParam), true);
                $companyId = $stateData['companyId'] ?? null;
                $codeVerifier = $stateData['codeVerifier'] ?? null;
                $redirectUri = $stateData['redirectUri'] ?? null;
            } catch (\Exception $e) {
                Log::warning('[QuotaMP] Error decoding state', ['error' => $e->getMessage()]);
            }
        }

        if ($error) {
            return $this->errorHtml("Error: " . ($request->query('error_description') ?? $error));
        }

        if (empty($code) || empty($companyId)) {
            return $this->errorHtml("Datos faltantes (code o companyId)");
        }

        try {
            $company = DB::connection('mysql_parent')
                ->table('companies')
                ->where('id', $companyId)
                ->first();

            if (!$company) {
                return $this->errorHtml("Empresa no encontrada (ID: " . $companyId . ")");
            }

            $parentConfig = DB::connection('mysql_parent')
                ->table('configs')
                ->where('target', 'payment')
                ->pluck('value', 'name')
                ->toArray();

            $clientId = $parentConfig['mp_client_id'] ?? null;
            $clientSecret = $parentConfig['mp_client_secret'] ?? null;

            if (empty($clientId) || empty($clientSecret)) {
                return $this->errorHtml("Credenciales MP no configuradas");
            }

            $response = Http::asForm()->post('https://api.mercadopago.com/oauth/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $codeVerifier ?? '',
            ]);

            if ($response->failed()) {
                return $this->errorHtml("Error al obtener token: " . ($response->json()['message'] ?? $response->body()));
            }

            $data = $response->json();
            $accessToken = $data['access_token'] ?? '';

            if (empty($accessToken)) {
                return $this->errorHtml("No se recibió access_token");
            }

            return redirect()->away('/oauth?token=' . urlencode($accessToken) . '&company_id=' . $company->id . '&company_name=' . urlencode($company->name));
        } catch (\Exception $e) {
            Log::error('[QuotaMP Callback] Error: ' . $e->getMessage());
            return $this->errorHtml("Error interno");
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
    <h2 class=\"success\">Token obtenido correctamente</h2>
    <p><strong>Access Token:</strong></p>
    <textarea readonly>" . htmlspecialchars($accessToken) . "</textarea>
    <p style=\"margin-top: 10px; color: #28a745;\"><strong>Guardado en quota_configs</strong></p>
    <button onclick=\"window.close()\">Cerrar</button>
</body>
</html>";

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
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

        return response($html, 400, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
