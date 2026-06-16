<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotaDailyRateController extends Controller
{
    public function index()
    {
        $rates = DB::table('daily_charge_rates')
            ->orderBy('name')
            ->get();

        return response()->json($rates);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        $id = DB::table('daily_charge_rates')->insertGetId([
            'name' => $validated['name'],
            'amount' => $validated['amount'],
            'is_active' => $validated['is_active'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Tarifa diaria creada correctamente',
        ]);
    }

    public function update(Request $request, $id)
    {
        $rate = DB::table('daily_charge_rates')->where('id', $id)->first();
        if (!$rate) {
            return response()->json(['message' => 'Tarifa no encontrada'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'is_active' => 'boolean',
        ]);

        DB::table('daily_charge_rates')->where('id', $id)->update([
            'name' => $validated['name'],
            'amount' => $validated['amount'],
            'is_active' => $validated['is_active'] ?? true,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarifa diaria actualizada correctamente',
        ]);
    }

    public function destroy($id)
    {
        $rate = DB::table('daily_charge_rates')->where('id', $id)->first();
        if (!$rate) {
            return response()->json(['message' => 'Tarifa no encontrada'], 404);
        }

        DB::table('daily_charge_rates')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tarifa diaria eliminada correctamente',
        ]);
    }
}
