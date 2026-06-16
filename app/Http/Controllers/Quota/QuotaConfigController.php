<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class QuotaConfigController extends Controller
{
    public function index()
    {
        $configs = DB::table('quota_configs')->orderBy('id')->get();
        return response()->json($configs);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'value' => 'nullable|string',
        ]);

        DB::table('quota_configs')->where('id', $id)->update([
            'value' => $validated['value'],
            'updated_at' => now(),
        ]);

        $config = DB::table('quota_configs')->where('id', $id)->first();

        return response()->json(['success' => true, 'message' => 'Configuración actualizada']);
    }

    public function getMpOAuthUrl(Request $request)
    {
        $company = $request->session()->get('company');
        if (!$company) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $parentConfigs = DB::connection('mysql_parent')
            ->table('configs')
            ->where('target', 'payment')
            ->pluck('value', 'name')
            ->toArray();

        $clientId = $parentConfigs['mp_client_id'] ?? null;
        if (!$clientId) {
            return response()->json(['error' => 'Client ID no configurado'], 400);
        }

        $mpMode = $parentConfigs['mp_mode'] ?? 'sandbox';
        $redirectUri = DB::table('quota_configs')->where('name', 'redirect_uri')->value('value');

        if (!$redirectUri) {
            return response()->json(['error' => 'redirect_uri no configurado'], 400);
        }

        $authUrl = $mpMode === 'production'
            ? 'https://auth.mercadopago.com/authorization'
            : 'https://auth-sandbox.mercadopago.com/authorization';

        $companyId = $company['id'] ?? '';
        $stateData = base64_encode(json_encode([
            'companyId' => $companyId,
            'redirectUri' => $redirectUri,
        ]));

        $oauthUrl = "{$authUrl}?response_type=code&client_id={$clientId}&redirect_uri=" . urlencode($redirectUri) . "&state={$stateData}";

        return response()->json(['url' => $oauthUrl]);
    }

    public function cashiers()
    {
        $users = DB::table('users')
            ->where('role_id', 2)
            ->where(function ($q) {
                if (Schema::hasColumn('users', 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        return response()->json($users);
    }

    public function getMpClientId(Request $request)
    {
        $parentConfigs = DB::connection('mysql_parent')
            ->table('configs')
            ->where('target', 'payment')
            ->pluck('value', 'name')
            ->toArray();

        return response()->json([
            'mp_client_id' => $parentConfigs['mp_client_id'] ?? null,
            'mp_mode' => $parentConfigs['mp_mode'] ?? 'sandbox',
        ]);
    }
}
