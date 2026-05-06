<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    public function index()
    {
        $configs = DB::connection('mysql_parent')->table('configs')->orderBy('target')->orderBy('name')->get();
        return response()->json($configs);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'value' => 'nullable|string',
        ]);

        DB::connection('mysql_parent')->table('configs')->where('id', $id)->update([
            'value' => $validated['value'],
            'updated_at' => now()
        ]);

        $config = DB::connection('mysql_parent')->table('configs')->where('id', $id)->first();

        return response()->json(['success' => true, 'message' => 'Configuración actualizada', 'config' => $config]);
    }

    public function getByTarget($target)
    {
        $configs = DB::connection('mysql_parent')
            ->table('configs')
            ->where('target', $target)
            ->orderBy('name')
            ->get();

        return response()->json($configs);
    }
}