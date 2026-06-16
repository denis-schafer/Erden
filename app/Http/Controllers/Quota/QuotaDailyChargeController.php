<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuotaDailyChargeController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('daily_charges')
            ->join('users', 'daily_charges.charged_by', '=', 'users.id')
            ->leftJoin('daily_charge_rates', 'daily_charges.daily_rate_id', '=', 'daily_charge_rates.id');

        $hasRenderedColumn = Schema::hasColumn('daily_charges', 'rendered_by');

        if ($hasRenderedColumn) {
            $query->leftJoin('users as renderers', 'daily_charges.rendered_by', '=', 'renderers.id');
        }

        $query->select(
            'daily_charges.*',
            'users.name as charged_by_name',
            'daily_charge_rates.name as rate_name'
        );

        if ($hasRenderedColumn) {
            $query->addSelect('renderers.name as rendered_by_name');
        } else {
            $query->addSelect(DB::raw('NULL as rendered_by_name'));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('daily_charges.person_name', 'like', "%{$s}%")
                  ->orWhere('daily_charges.person_dni', 'like', "%{$s}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('daily_charges.created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('daily_charges.created_at', '<=', $request->date_to);
        }

        if ($request->filled('rendered') && Schema::hasColumn('daily_charges', 'rendered')) {
            $query->where('daily_charges.rendered', $request->rendered === 'true');
        }

        $charges = $query->orderBy('daily_charges.created_at', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json($charges);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'daily_rate_id' => 'nullable|exists:daily_charge_rates,id',
            'person_name' => 'required|string|max:255',
            'person_dni' => 'nullable|string|max:50',
            'quantity' => 'nullable|integer|min:1',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,digital',
            'notes' => 'nullable|string|max:1000',
        ]);

        $sessionUser = $request->session()->get('user');
        $chargedById = $sessionUser['id'] ?? null;

        if (!$chargedById) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $id = DB::table('daily_charges')->insertGetId([
            'daily_rate_id' => $validated['daily_rate_id'] ?? null,
            'person_name' => $validated['person_name'],
            'person_dni' => $validated['person_dni'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'notes' => $validated['notes'] ?? null,
            'charged_by' => $chargedById,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'id' => $id,
            'message' => 'Cobro diario registrado correctamente',
        ]);
    }

    public function render($id, Request $request)
    {
        if (!Schema::hasColumn('daily_charges', 'rendered')) {
            return response()->json(['message' => 'Funcionalidad no disponible. Ejecute las migraciones primero.'], 500);
        }

        $charge = DB::table('daily_charges')->where('id', $id)->first();
        if (!$charge) {
            return response()->json(['message' => 'Cobro no encontrado'], 404);
        }

        $sessionUser = $request->session()->get('user');
        $userId = $sessionUser['id'] ?? null;

        if (!$userId) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        if ($charge->rendered) {
            return response()->json(['message' => 'El cobro ya está rendido'], 400);
        }

        DB::table('daily_charges')->where('id', $id)->update([
            'rendered' => true,
            'rendered_amount' => $charge->amount,
            'rendered_at' => now(),
            'rendered_by' => $userId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cobro rendido correctamente',
        ]);
    }

    public function unrender($id)
    {
        if (!Schema::hasColumn('daily_charges', 'rendered')) {
            return response()->json(['message' => 'Funcionalidad no disponible. Ejecute las migraciones primero.'], 500);
        }

        $charge = DB::table('daily_charges')->where('id', $id)->first();
        if (!$charge) {
            return response()->json(['message' => 'Cobro no encontrado'], 404);
        }

        if (!$charge->rendered) {
            return response()->json(['message' => 'El cobro no está rendido'], 400);
        }

        DB::table('daily_charges')->where('id', $id)->update([
            'rendered' => false,
            'rendered_amount' => null,
            'rendered_at' => null,
            'rendered_by' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rendición revertida correctamente',
        ]);
    }
}
