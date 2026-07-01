<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HairSalonFinanceController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('hairsalon_cash_movements as m')
            ->join('users as u', 'm.operator_id', '=', 'u.id')
            ->select('m.*', 'u.name as operator_name');

        if ($request->get('type')) {
            $query->where('m.type', $request->get('type'));
        }

        if ($request->get('payment_method')) {
            $query->where('m.payment_method', $request->get('payment_method'));
        }

        if ($request->get('start_date')) {
            $query->whereDate('m.created_at', '>=', $request->get('start_date'));
        }

        if ($request->get('end_date')) {
            $query->whereDate('m.created_at', '<=', $request->get('end_date'));
        }

        $movements = $query->orderBy('m.created_at', 'desc')->paginate($request->get('per_page', 50));

        $movements->getCollection()->transform(function ($m) {
            $m->detail = isset($m->detail) && $m->detail ? json_decode($m->detail, true) : null;
            return $m;
        });

        return response()->json($movements);
    }

    public function show($id)
    {
        $movement = DB::table('hairsalon_cash_movements as m')
            ->join('users as u', 'm.operator_id', '=', 'u.id')
            ->select('m.*', 'u.name as operator_name')
            ->where('m.id', $id)
            ->first();

        if (!$movement) {
            return response()->json(['message' => 'Movimiento no encontrado'], 404);
        }

        $movement->detail = isset($movement->detail) && $movement->detail ? json_decode($movement->detail, true) : null;

        // If no detail snapshot but it's an income with a job_id, fetch from job
        if (!$movement->detail && $movement->type === 'income' && $movement->job_id) {
            $job = DB::table('hairsalon_jobs as j')
                ->leftJoin('hairsalon_clients as c', 'j.client_id', '=', 'c.id')
                ->select('j.notes', 'c.name as client_name')
                ->where('j.id', $movement->job_id)
                ->first();

            if ($job) {
                $movement->detail = [
                    'client_name' => $job->client_name,
                    'notes' => $job->notes,
                ];
            }
        }

        return response()->json($movement);
    }

    public function summary(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $income = DB::table('hairsalon_cash_movements')
            ->where('type', 'income')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('amount');

        $expenses = DB::table('hairsalon_cash_movements')
            ->where('type', 'expense')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->sum('amount');

        $byMethod = DB::table('hairsalon_cash_movements')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->select('payment_method', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        return response()->json([
            'income' => $income,
            'expenses' => $expenses,
            'balance' => $income - $expenses,
            'by_method' => $byMethod,
        ]);
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:200',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,transfer,mercadopago,other',
            'notes' => 'nullable|string',
        ]);

        $operatorId = session('user.id');
        $operator = DB::table('users')->find($operatorId);

        $expenseData = [
            'type' => 'expense',
            'concept' => $validated['concept'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'operator_id' => $operatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('hairsalon_cash_movements', 'detail')) {
            $expenseData['detail'] = json_encode([
                'operator_name' => $operator->name ?? 'Operador',
                'notes' => $validated['notes'] ?? null,
            ]);
        }

        $id = DB::table('hairsalon_cash_movements')->insertGetId($expenseData);

        return response()->json([
            'success' => true,
            'movement' => DB::table('hairsalon_cash_movements')->find($id),
        ]);
    }
}
