<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Events\ConfigUpdated;
use App\Http\Controllers\Controller;

class PosConfigController extends Controller
{
    public function index()
    {
        $configs = DB::table('configs')->orderBy('id')->get();
        return response()->json($configs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'value' => 'nullable|string',
            'type' => 'nullable|string',
        ]);

        DB::table('configs')->updateOrInsert(
            ['name' => $validated['name']],
            [
                'value' => $validated['value'] ?? '',
                'type' => $validated['type'] ?? 'string',
                'updated_at' => now()
            ]
        );

        $config = DB::table('configs')->where('name', $validated['name'])->first();

        $event = new ConfigUpdated([
            'name' => $config->name,
            'value' => $config->value,
        ]);

        try {
            broadcast($event);
        } catch (\Exception $e) {
            Log::error('Broadcast failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Configuración guardada']);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'value' => 'nullable|string',
        ]);

        DB::table('configs')->where('id', $id)->update([
            'value' => $validated['value'],
            'updated_at' => now()
        ]);

        $config = DB::table('configs')->where('id', $id)->first();
        
        Log::info('Config updated, broadcasting event:', [
            'name' => $config->name,
            'value' => $config->value
        ]);
        
        // Create and broadcast the event
        $event = new ConfigUpdated([
            'name' => $config->name,
            'value' => $config->value,
        ]);
        
        try {
            broadcast($event);
            Log::info('Event broadcasted successfully');
        } catch (\Exception $e) {
            Log::error('Broadcast failed: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => 'Configuración actualizada']);
    }

    public function printAgentInfo(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        $company = $request->session()->get('company');

        if (!$sessionUser || !$company) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $parentCompany = DB::connection('mysql_parent')
            ->table('companies')
            ->where('db', $company['db'])
            ->first();

        return response()->json([
            'agent_key' => $parentCompany->print_agent_key ?? '',
            'server_url' => url('/'),
            'download_available' => Storage::exists('print-agent/ErdenPrintAgent.exe'),
        ]);
    }

    public function regeneratePrintAgentKey(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        $company = $request->session()->get('company');

        if (!$sessionUser || !$company) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $isAdmin = $sessionUser['role_id'] == 1 || $request->session()->get('is_global_admin', false);
        if (!$isAdmin) {
            return response()->json(['error' => 'Solo administradores pueden regenerar la clave'], 403);
        }

        $newKey = (string) Str::uuid();

        DB::connection('mysql_parent')
            ->table('companies')
            ->where('db', $company['db'])
            ->update(['print_agent_key' => $newKey]);

        Log::info("Print agent key regenerated for company DB: {$company['db']}");

        return response()->json([
            'success' => true,
            'agent_key' => $newKey,
        ]);
    }

    public function downloadAgent()
    {
        $filePath = 'print-agent/ErdenPrintAgent.exe';

        if (!Storage::exists($filePath)) {
            return response()->json(['error' => 'Agente no disponible. Genera el .exe siguiendo las instrucciones.'], 404);
        }

        return Storage::download($filePath, 'ErdenPrintAgent.exe');
    }

    public function syncSettings(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        $company = $request->session()->get('company');

        if (!$sessionUser || !$company) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $remoteUrl = DB::table('configs')->where('name', 'remote_url')->first();
        $remoteKey = DB::table('configs')->where('name', 'remote_key')->first();

        return response()->json([
            'remote_url' => $remoteUrl->value ?? '',
            'remote_key' => $remoteKey->value ?? '',
        ]);
    }

    public function updateSyncSettings(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        $company = $request->session()->get('company');

        if (!$sessionUser || !$company) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $isAdmin = $sessionUser['role_id'] == 1 || $request->session()->get('is_global_admin', false);
        if (!$isAdmin) {
            return response()->json(['error' => 'Solo administradores pueden modificar esta configuracion'], 403);
        }

        $validated = $request->validate([
            'remote_url' => 'nullable|string|max:255',
            'remote_key' => 'nullable|string|max:255',
        ]);

        DB::table('configs')->updateOrInsert(
            ['name' => 'remote_url'],
            ['value' => $validated['remote_url'] ?? '', 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );

        DB::table('configs')->updateOrInsert(
            ['name' => 'remote_key'],
            ['value' => $validated['remote_key'] ?? '', 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );

        Log::info("Sync settings updated for company DB: {$company['db']}");

        return response()->json([
            'success' => true,
            'remote_url' => $validated['remote_url'] ?? '',
            'remote_key' => $validated['remote_key'] ?? '',
        ]);
    }

    public function webhookCode(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        $company = $request->session()->get('company');

        if (!$sessionUser || !$company) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $config = DB::table('configs')->where('name', 'webhook_code')->first();
        return response()->json([
            'webhook_code' => $config->value ?? '',
        ]);
    }

    public function updateWebhookCode(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        $company = $request->session()->get('company');

        if (!$sessionUser || !$company) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        $isAdmin = $sessionUser['role_id'] == 1 || $request->session()->get('is_global_admin', false);
        if (!$isAdmin) {
            return response()->json(['error' => 'Solo administradores pueden modificar esta configuracion'], 403);
        }

        $validated = $request->validate([
            'webhook_code' => 'nullable|string|max:50',
        ]);

        $webhookCode = $validated['webhook_code'] ?? '';

        // Update child DB config
        DB::table('configs')->updateOrInsert(
            ['name' => 'webhook_code'],
            ['value' => $webhookCode, 'type' => 'string', 'updated_at' => now(), 'created_at' => now()]
        );

        // Update parent DB companies table
        DB::connection('mysql_parent')
            ->table('companies')
            ->where('db', $company['db'])
            ->update(['webhook_code' => $webhookCode]);

        Log::info("Webhook code updated for company DB: {$company['db']} = {$webhookCode}");

        return response()->json([
            'success' => true,
            'webhook_code' => $webhookCode,
        ]);
    }

    public function upload(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|in:logo,background_image',
            'file' => 'required|image|max:2048',
        ]);

        $file = $request->file('file');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . time() . '.' . $file->getClientOriginalExtension();
        $folder = $validated['name'] === 'logo' ? 'logo' : 'background';
        $dir = storage_path('app/public/pos/' . $folder);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $file->move($dir, $filename);
        $url = '/storage/pos/' . $folder . '/' . $filename;

        DB::table('configs')->updateOrInsert(
            ['name' => $validated['name']],
            ['value' => $url, 'type' => 'image', 'updated_at' => now(), 'created_at' => now()]
        );

        broadcast(new ConfigUpdated(['name' => $validated['name'], 'value' => $url]));

        return response()->json(['success' => true, 'url' => $url]);
    }

    public function deleteImage($id)
    {
        $config = DB::table('configs')->find($id);
        if (!$config) {
            return response()->json(['message' => 'Config no encontrada'], 404);
        }

        DB::table('configs')->where('id', $id)->update([
            'value' => '',
            'updated_at' => now(),
        ]);

        broadcast(new ConfigUpdated(['name' => $config->name, 'value' => '']));

        return response()->json(['success' => true]);
    }
}