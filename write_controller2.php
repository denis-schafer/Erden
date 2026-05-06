<?php
$file = "C:\\laragon\\www\\erden\\app\\Http\\Controllers\\Pos\\MercadoPagoController.php";

$content = "<?php

namespace App\\Http\\Controllers\\Pos;

use App\\Http\\Controllers\\Controller;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Http;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Config;
use Illuminate\\Support\\Facades\\Log;

class MercadoPagoController extends Controller
{
    public function callback(Request \$request)
    {
        \$code = \$request->query('code');
        \$stateParam = \$request->query('state');
        \$error = \$request->query('error');
        
        \$companyId = null;
        \$codeVerifier = null;
        \$redirectUriFromState = null;
        
        if (\$stateParam) {
            try {
                \$stateData = json_decode(base64_decode(\$stateParam), true);
                \$companyId = \$stateData['companyId'] ?? null;
                \$codeVerifier = \$stateData['codeVerifier'] ?? null;
                \$redirectUriFromState = \$stateData['redirectUri'] ?? null;
            } catch (\\Exception \$e) {
                Log::warning('[MercadoPago] Error decoding state', ['error' => \$e->getMessage()]);
            }
        }
        
        Log::info('[MercadoPago] Callback received', [
            'company_id' => \$companyId,
            'has_code' => !empty(\$code),
            'has_code_verifier' => !empty(\$codeVerifier),
            'error' => \$error,
        ]);
        
        if (\$error) {
            return \$this->errorHtml('Error: ' . (\$request->query('error_description') ?? \$error));
        }
        
        if (empty(\$code) || empty(\$companyId)) {
            return \$this->errorHtml('Datos faltantes (code o companyId)');
        }
        
        try {
            \$company = DB::connection('mysql_parent')
                ->table('companies')
                ->where('id', \$companyId)
                ->first();
            
            if (!\$company) {
                throw new \\Exception('Empresa no encontrada (ID: ' . \$companyId . ')');
            }
            
            \$companyDb = \$company->db;
            
            \$parentConfig = DB::connection('mysql_parent')
                ->table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            \$clientId = \$parentConfig['mp_client_id'] ?? null;
            \$clientSecret = \$parentConfig['mp_client_secret'] ?? null;
            \$mode = \$parentConfig['mp_mode'] ?? 'sandbox';
            
            if (empty(\$clientId) || empty(\$clientSecret)) {
                throw new \\Exception('Client ID o Client Secret no configurados en DB padre');
            }
            
            Config::set('database.connections.mysql.database', \$companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            \$childConfig = DB::table('configs')
                ->pluck('value', 'name')
                ->toArray();
            
            \$redirectUri = \$redirectUriFromState ?? \$childConfig['redirect_uri'] ?? null;
            
            if (empty(\$redirectUri)) {
                throw new \\Exception('redirect_uri no configurado en la empresa (DB: ' . \$companyDb . ')');
            }
            
            \$authUrl = \$mode === 'production'
                ? 'https://api.mercadopago.com/oauth/token'
                : 'https://api.sandbox.mercadopago.com/oauth/token';
            
            \$response = Http::asForm()->post(\$authUrl, [
                'client_id' => \$clientId,
                'client_secret' => \$clientSecret,
                'grant_type' => 'authorization_code',
                'code' => \$code,
                'redirect_uri' => \$redirectUri,
                'code_verifier' => \$codeVerifier ?? '',
            ]);
            
            if (\$response->failed()) {
                throw new \\Exception('Error MercadoPago: ' . (\$response->json()['message'] ?? \$response->body()));
            }
            
            \$data = \$response->json();
            \$accessToken = \$data['access_token'] ?? '';
            
            if (empty(\$accessToken)) {
                throw new \\Exception('No se recibio access_token');
            }
            
            DB::table('configs')->updateOrInsert(
                ['name' => 'mp_access_token'],
                ['value' => \$accessToken, 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
            );
            
            Config::set('database.connections.mysql.database', env('DB_DATABASE', 'erden'));
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            return \$this->successHtml(\$accessToken, \$company);
            
        } catch (\\Exception \$e) {
            Log::error('[MercadoPago] Error', ['error' => \$e->getMessage()]);
            return \$this->errorHtml(\$e->getMessage());
        }
    }
    
    private function successHtml(\$accessToken, \$company)
    {
        \$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>Token Obtenido - MercadoPago</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; max-width: 600px; margin: 0 auto; }
        .success { color: #28a745; }
        textarea { width: 100%; height: 80px; font-family: monospace; font-size: 12px; margin: 10px 0; }
        button { padding: 10px 20px; cursor: pointer; margin-top: 20px; background: #28a745; color: white; border: none; border-radius: 5px; }
    </style>
</head>
<body>
    <h2 class=\"success\">Token obtenido con exito</h2>
    <p>Empresa: <strong>' . htmlspecialchars(\$company->name) . '</strong> (DB: ' . htmlspecialchars(\$company->db) . ')</p>
    <p><strong>Access Token:</strong></p>
    <textarea readonly>' . htmlspecialchars(\$accessToken) . '</textarea>
    <p style=\"margin-top: 10px; color: #28a745;\"><strong>Guardado en configuracion (mp_access_token)</strong></p>
    <p>Ya puedes cerrar esta pestana y continuar en el panel de configuracion.</p>
    <button onclick=\"window.close()\">Cerrar pestana</button>
</body>
</html>';
        
        return response(\$html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
    
    private function errorHtml(\$message)
    {
        \$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>Error - MercadoPago</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; max-width: 600px; margin: 0 auto; }
        .error { color: #dc3545; }
        button { padding: 10px 20px; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>
    <h2 class=\"error\">Error</h2>
    <p>' . htmlspecialchars(\$message) . '</p>
    <button onclick=\"window.close()\">Cerrar</button>
</body>
</html>';
        
        return response(\$html, 400, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
    
    public function exchangeCode(Request \$request)
    {
        return response()->json(['success' => false, 'message' => 'Usa el flujo de nueva pestana'], 400);
    }
    
    public function generateQR(Request \$request)
    {
        return response()->json(['success' => false, 'message' => 'Metodo no implementado'], 501);
    }
    
    public function webhook(Request \$request)
    {
        return response()->json(['status' => 'ok']);
    }
}
";

file_put_contents($file, $content);
echo "Controlador escrito correctamente\n";