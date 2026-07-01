<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use App\Events\HairSalon\HairSalonConfigUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HairSalonConfigController extends Controller
{
    public function index()
    {
        $configs = DB::table('hairsalon_configs')->orderBy('id')->get();
        return response()->json($configs);
    }

    public function update(Request $request, $id)
    {
        $config = DB::table('hairsalon_configs')->find($id);
        if (!$config) {
            return response()->json(['message' => 'Configuración no encontrada'], 404);
        }

        $validated = $request->validate([
            'value' => 'nullable|string|max:500',
        ]);

        DB::table('hairsalon_configs')->where('id', $id)->update([
            'value' => $validated['value'],
            'updated_at' => now(),
        ]);

        $config = DB::table('hairsalon_configs')->find($id);
        broadcast(new HairSalonConfigUpdated($config));

        return response()->json(['success' => true, 'config' => $config]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:png,jpg,jpeg,svg|max:2048',
            'type' => 'required|string|in:logo,background',
        ]);

        $file = $request->file('file');
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = 'hairsalon/' . $request->type . '/' . $filename;

        // Ensure directory exists
        $dir = storage_path('app/public/hairsalon/' . $request->type);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Move file directly
        $file->move($dir, $filename);

        $config = DB::table('hairsalon_configs')
            ->where('name', $request->type === 'logo' ? 'logo' : 'background_image')
            ->first();

        if ($config) {
            DB::table('hairsalon_configs')->where('id', $config->id)->update([
                'value' => '/storage/' . $path,
                'updated_at' => now(),
            ]);

            broadcast(new HairSalonConfigUpdated([
                'name' => $config->name,
                'value' => '/storage/' . $path,
            ]));
        }

        return response()->json([
            'success' => true,
            'url' => '/storage/' . $path,
        ]);
    }
}
