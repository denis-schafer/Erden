<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Events\Academy\AcademyConfigUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcademyConfigController extends Controller
{
    public function index()
    {
        $configs = DB::table('academy_configs')->orderBy('id')->get();
        return response()->json($configs);
    }

    public function update(Request $request, $id)
    {
        $config = DB::table('academy_configs')->find($id);
        if (!$config) {
            return response()->json(['message' => 'Configuración no encontrada'], 404);
        }

        $validated = $request->validate([
            'value' => 'nullable|string|max:500',
        ]);

        DB::table('academy_configs')->where('id', $id)->update([
            'value' => $validated['value'],
            'updated_at' => now(),
        ]);

        $config = DB::table('academy_configs')->find($id);
        broadcast(new AcademyConfigUpdated($config));

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
        $path = 'academy/' . $request->type . '/' . $filename;

        $dir = storage_path('app/public/academy/' . $request->type);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $file->move($dir, $filename);

        $config = DB::table('academy_configs')
            ->where('name', $request->type === 'logo' ? 'logo' : 'background_image')
            ->first();

        if ($config) {
            DB::table('academy_configs')->where('id', $config->id)->update([
                'value' => '/storage/' . $path,
                'updated_at' => now(),
            ]);

            broadcast(new AcademyConfigUpdated([
                'name' => $config->name,
                'value' => '/storage/' . $path,
            ]));
        }

        return response()->json([
            'success' => true,
            'url' => '/storage/' . $path,
        ]);
    }

    public function deleteImage(Request $request, $id)
    {
        $config = DB::table('academy_configs')->find($id);
        if (!$config) {
            return response()->json(['message' => 'Configuración no encontrada'], 404);
        }

        DB::table('academy_configs')->where('id', $id)->update([
            'value' => '',
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true]);
    }
}
