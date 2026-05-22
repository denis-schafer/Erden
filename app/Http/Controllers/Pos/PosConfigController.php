<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Events\ConfigUpdated;

class PosConfigController extends Controller
{
    public function index()
    {
        $configs = DB::table('configs')->orderBy('id')->get();
        return response()->json($configs);
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
}