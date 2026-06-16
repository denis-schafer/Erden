<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class OAuthController extends Controller
{
    private function generateCodeVerifier(): string
    {
        $bytes = random_bytes(32);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function generateCodeChallenge(string $verifier): string
    {
        $hash = hash('sha256', $verifier, true);
        return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
    }

    public function lookup(Request $request)
    {
        $name = $request->get('name');

        if (empty($name)) {
            return response()->json(['error' => 'Ingrese un nombre de empresa'], 400);
        }

        $company = DB::connection('mysql_parent')
            ->table('companies')
            ->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($name) . '%'])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('company_modules')
                    ->join('modules', 'company_modules.module_id', '=', 'modules.id')
                    ->whereColumn('company_modules.company_id', 'companies.id')
                    ->where('modules.package', 'quota_admin');
            })
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Empresa no encontrada o no tiene el módulo Cuotas'], 404);
        }

        $parentConfig = DB::connection('mysql_parent')
            ->table('configs')
            ->where('target', 'payment')
            ->pluck('value', 'name')
            ->toArray();

        $mpClientId = $parentConfig['mp_client_id'] ?? null;

        if (empty($mpClientId)) {
            return response()->json(['error' => 'MercadoPago no configurado en el sistema'], 500);
        }

        return response()->json([
            'id' => $company->id,
            'name' => $company->name,
            'db' => $company->db,
            'mp_client_id' => $mpClientId,
            'mp_mode' => 'production',
        ]);
    }

    public function authorizeUrl(Request $request)
    {
        $companyId = $request->get('company_id');

        if (empty($companyId)) {
            return response()->json(['error' => 'Falta company_id'], 400);
        }

        $parentConfig = DB::connection('mysql_parent')
            ->table('configs')
            ->where('target', 'payment')
            ->pluck('value', 'name')
            ->toArray();

        $mpClientId = $parentConfig['mp_client_id'] ?? null;

        if (empty($mpClientId)) {
            return response()->json(['error' => 'MercadoPago no configurado en el sistema'], 500);
        }

        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);
        $redirectUri = 'https://www.erden.com.ar/mp/callback';
        $stateData = base64_encode(json_encode([
            'companyId' => (int) $companyId,
            'codeVerifier' => $codeVerifier,
            'redirectUri' => $redirectUri,
        ]));
        $authUrl = 'https://auth.mercadopago.com/authorization';
        $oauthUrl = "{$authUrl}?response_type=code&client_id={$mpClientId}&redirect_uri=" . urlencode($redirectUri) . "&state={$stateData}&code_challenge={$codeChallenge}&code_challenge_method=S256";

        return response()->json(['url' => $oauthUrl]);
    }

    public function assign(Request $request)
    {
        $token = $request->input('token');
        $companyId = $request->input('company_id');

        if (empty($token) || empty($companyId)) {
            return response()->json(['error' => 'Faltan datos requeridos'], 400);
        }

        $company = DB::connection('mysql_parent')
            ->table('companies')
            ->where('id', $companyId)
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Empresa no encontrada'], 404);
        }

        Config::set('database.connections.mysql.database', $company->db);
        DB::purge('mysql');
        DB::reconnect('mysql');

        DB::table('quota_configs')->updateOrInsert(
            ['name' => 'mp_access_token'],
            ['value' => $token, 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );

        return response()->json([
            'success' => true,
            'message' => "Token asignado a {$company->name} correctamente",
        ]);
    }
}
