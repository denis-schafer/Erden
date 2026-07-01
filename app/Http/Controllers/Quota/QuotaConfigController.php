<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use App\Events\QuotaConfigUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        broadcast(new QuotaConfigUpdated($config));

        return response()->json(['success' => true, 'config' => $config]);
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|in:portal_logo,portal_bg,logo,background_image',
            'file' => 'required|image|max:2048',
        ]);

        $file = $request->file('file');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . time() . '.' . $file->getClientOriginalExtension();
        $folder = in_array($validated['name'], ['logo', 'portal_logo']) ? 'logo' : 'background';
        $dir = storage_path('app/public/quota/' . $folder);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $filename);
        $url = '/storage/quota/' . $folder . '/' . $filename;

        DB::table('quota_configs')->updateOrInsert(
            ['name' => $validated['name']],
            ['value' => $url, 'type' => 'image', 'updated_at' => now(), 'created_at' => now()]
        );

        $config = DB::table('quota_configs')->where('name', $validated['name'])->first();
        broadcast(new QuotaConfigUpdated($config));

        return response()->json(['success' => true, 'url' => $url]);
    }

    public function deleteImage(Request $request, $id)
    {
        $config = DB::table('quota_configs')->find($id);
        if (!$config) {
            return response()->json(['message' => 'Config no encontrada'], 404);
        }

        DB::table('quota_configs')->where('id', $id)->update([
            'value' => '',
            'updated_at' => now(),
        ]);

        $config = DB::table('quota_configs')->find($id);
        broadcast(new QuotaConfigUpdated($config));

        return response()->json(['success' => true]);
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

    public function portalConfig(Request $request)
    {
        $companyDb = $request->get('company_db');

        if ($companyDb) {
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        $names = ['portal_logo', 'portal_bg', 'portal_primary_color', 'portal_secondary_color'];
        $configs = DB::table('quota_configs')
            ->whereIn('name', $names)
            ->pluck('value', 'name')
            ->toArray();

        return response()->json([
            'logo' => $configs['portal_logo'] ?? '',
            'bg' => $configs['portal_bg'] ?? '',
            'primary_color' => $configs['portal_primary_color'] ?? '#667eea',
            'secondary_color' => $configs['portal_secondary_color'] ?? '#764ba2',
        ]);
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
