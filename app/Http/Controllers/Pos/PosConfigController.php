<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
}