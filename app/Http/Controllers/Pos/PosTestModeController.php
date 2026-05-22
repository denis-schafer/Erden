<?php

namespace App\Http\Controllers\Pos;

use App\Events\ConfigUpdated;
use App\Http\Controllers\Controller;
use App\Packages\Pos\Helpers\TestModeHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosTestModeController extends Controller
{
    public function status(): JsonResponse
    {
        return response()->json([
            'enabled' => TestModeHelper::isTestMode(),
        ]);
    }

    public function enable(): JsonResponse
    {
        $config = DB::table('configs')->where('name', 'test_mode')->first();

        if ($config) {
            DB::table('configs')->where('id', $config->id)->update([
                'value' => '1',
                'updated_at' => now(),
            ]);
        } else {
            DB::table('configs')->insert([
                'name' => 'test_mode',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try {
            broadcast(new ConfigUpdated(['name' => 'test_mode', 'value' => '1']));
        } catch (\Exception $e) {
        }

        return response()->json(['success' => true, 'message' => 'Modo Test activado']);
    }

    public function disable(): JsonResponse
    {
        $deleted = TestModeHelper::purgeTestData();

        $config = DB::table('configs')->where('name', 'test_mode')->first();

        if ($config) {
            DB::table('configs')->where('id', $config->id)->update([
                'value' => '0',
                'updated_at' => now(),
            ]);
        }

        try {
            broadcast(new ConfigUpdated(['name' => 'test_mode', 'value' => '0']));
        } catch (\Exception $e) {
        }

        return response()->json([
            'success' => true,
            'message' => 'Modo Producción activado. Datos de prueba eliminados.',
            'deleted' => $deleted,
        ]);
    }
}
