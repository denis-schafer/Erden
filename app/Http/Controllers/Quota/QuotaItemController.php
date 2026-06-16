<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class QuotaItemController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('quotas')
            ->join('users', 'quotas.partner_id', '=', 'users.id')
            ->join('quota_plans', 'quotas.quota_plan_id', '=', 'quota_plans.id')
            ->select(
                'quotas.*',
                'users.name as partner_name',
                'users.first_name',
                'users.last_name',
                'users.dni',
                'quota_plans.name as plan_name'
            );

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.dni', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%");
            });
        }

        if ($partnerId = $request->get('partner_id')) {
            $query->where('quotas.partner_id', $partnerId);
        }

        if ($planId = $request->get('plan_id')) {
            $query->where('quotas.quota_plan_id', $planId);
        }

        if ($status = $request->get('status')) {
            $query->where('quotas.status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('quotas.type', $type);
        }

        if ($rendered = $request->get('rendered')) {
            $query->where('quotas.rendered', $rendered === 'true');
        }

        $allowedSortFields = ['last_name', 'first_name', 'dni', 'plan_name', 'type', 'installment_number', 'amount', 'due_date', 'status', 'payment_method'];
        $sortField = $request->get('sort_field');
        $sortDir = $request->get('sort_dir', 'asc');

        if ($sortField && in_array($sortField, $allowedSortFields)) {
            $fieldMap = [
                'last_name' => 'users.last_name',
                'first_name' => 'users.first_name',
                'dni' => 'users.dni',
                'plan_name' => 'quota_plans.name',
                'type' => 'quotas.type',
                'installment_number' => 'quotas.installment_number',
                'amount' => 'quotas.amount',
                'due_date' => 'quotas.due_date',
                'status' => 'quotas.status',
                'payment_method' => 'quotas.payment_method',
            ];
            $query->orderBy($fieldMap[$sortField], $sortDir);
        } else {
            $query->orderBy('users.last_name')
                ->orderBy('users.first_name')
                ->orderByRaw("FIELD(quotas.type, 'regular', 'pool_fee')")
                ->orderBy('quotas.installment_number');
        }

        $items = $query->paginate($request->get('per_page', 10));

        return response()->json($items);
    }

    public function pay(Request $request)
    {
        $validated = $request->validate([
            'quota_ids' => 'required|array|min:1',
            'quota_ids.*' => 'integer|exists:quotas,id',
            'payment_method' => 'required|string|in:cash,digital',
            'rendered' => 'boolean',
        ]);

        $quotas = DB::table('quotas')
            ->whereIn('id', $validated['quota_ids'])
            ->where('status', 'pending')
            ->get();

        if ($quotas->isEmpty()) {
            return response()->json(['message' => 'Ninguna cuota seleccionada está pendiente'], 400);
        }

        $sessionUser = $request->session()->get('user');
        $paidById = $sessionUser['id'] ?? null;

        if (!$paidById) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $now = now();
        $paymentIds = [];

        // Pool fee constraint: pool fees require all regular quotas of the year to be paid
        $hasPoolFee = $quotas->contains('type', 'pool_fee');
        if ($hasPoolFee) {
            foreach ($quotas as $quota) {
                if ($quota->type !== 'pool_fee') continue;

                $year = date('Y', strtotime($quota->due_date));

                $pendingRegular = DB::table('quotas')
                    ->where('partner_id', $quota->partner_id)
                    ->where('quota_plan_id', $quota->quota_plan_id)
                    ->where('type', 'regular')
                    ->where('status', 'pending')
                    ->whereNotIn('id', $validated['quota_ids'])
                    ->whereYear('due_date', $year)
                    ->count();

                if ($pendingRegular > 0) {
                    return response()->json([
                        'message' => 'Debe pagar todas las cuotas regulares del año antes de pagar los derechos de pileta',
                    ], 400);
                }
            }
        }

        foreach ($quotas as $quota) {
            $paymentId = DB::table('quota_payments')->insertGetId([
                'partner_id' => $quota->partner_id,
                'total_amount' => $quota->amount,
                'payment_method' => $validated['payment_method'],
                'paid_by' => $paidById,
                'paid_at' => $now,
                'rendered' => $validated['rendered'] ?? false,
                'rendered_amount' => $validated['rendered'] ? $quota->amount : null,
                'rendered_at' => $validated['rendered'] ? $now : null,
                'rendered_by' => $validated['rendered'] ? $paidById : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('quotas')->where('id', $quota->id)->update([
                'status' => 'paid',
                'payment_method' => $validated['payment_method'],
                'paid_at' => $now,
                'paid_by' => $paidById,
                'rendered' => $validated['rendered'] ?? false,
                'rendered_at' => $validated['rendered'] ? $now : null,
                'rendered_by' => $validated['rendered'] ? $paidById : null,
                'updated_at' => $now,
            ]);

            DB::table('quota_payment_items')->insert([
                'quota_payment_id' => $paymentId,
                'quota_id' => $quota->id,
                'amount' => $quota->amount,
            ]);

            $paymentIds[] = $paymentId;
        }

        return response()->json([
            'success' => true,
            'message' => count($quotas) . ' cuota(s) pagada(s) correctamente',
            'payment_ids' => $paymentIds,
        ]);
    }

    public function toggleRendered(Request $request, $id)
    {
        $quota = DB::table('quotas')->find($id);
        if (!$quota || $quota->status !== 'paid') {
            return response()->json(['message' => 'La cuota debe estar pagada para rendirla'], 400);
        }

        $sessionUser = $request->session()->get('user');
        $userId = $sessionUser['id'] ?? null;
        $roleId = $sessionUser['role_id'] ?? null;

        // Only admin or the user who collected the payment can toggle rendered
        if ($roleId !== 1 && $quota->paid_by !== $userId) {
            return response()->json(['message' => 'Solo quien cobró la cuota puede rendirla'], 403);
        }

        $newRendered = !$quota->rendered;

        if ($newRendered) {
            DB::table('quotas')->where('id', $id)->update([
                'rendered' => true,
                'rendered_at' => now(),
                'rendered_by' => $userId,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('quotas')->where('id', $id)->update([
                'rendered' => false,
                'rendered_at' => null,
                'rendered_by' => null,
                'updated_at' => now(),
            ]);
        }

        // Find parent payment and recalculate its rendered status
        $paymentItem = DB::table('quota_payment_items')->where('quota_id', $id)->first();
        $paymentId = $paymentItem?->quota_payment_id;

        if ($paymentId) {
            $allQuotas = DB::table('quota_payment_items')
                ->join('quotas', 'quota_payment_items.quota_id', '=', 'quotas.id')
                ->where('quota_payment_items.quota_payment_id', $paymentId)
                ->pluck('quotas.rendered');

            $paymentRendered = $allQuotas->contains(true);

            DB::table('quota_payments')->where('id', $paymentId)->update([
                'rendered' => $paymentRendered,
                'rendered_at' => $paymentRendered ? now() : null,
                'rendered_by' => $paymentRendered ? $userId : null,
            ]);
        }

        // Broadcast via WebSocket
        try {
            $companyDb = Config::get('database.connections.mysql.database');
            event(new \App\Events\QuotaRenderedUpdated(
                $paymentId,
                [$id],
                $newRendered,
                $companyDb
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('QuotaRenderedUpdated broadcast error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'rendered' => $newRendered,
        ]);
    }

    public function myQuotas(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $parts = explode(':', base64_decode($token));
        $userId = $parts[0] ?? null;

        if (!$userId) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $quotas = DB::table('quotas')
            ->join('quota_plans', 'quotas.quota_plan_id', '=', 'quota_plans.id')
            ->select('quotas.*', 'quota_plans.name as plan_name')
            ->where('quotas.partner_id', $userId)
            ->orderByRaw("FIELD(quotas.type, 'regular', 'pool_fee')")
            ->orderBy('quotas.installment_number', 'asc')
            ->get();

        $summary = [
            'total' => $quotas->count(),
            'paid' => $quotas->where('status', 'paid')->count(),
            'pending' => $quotas->where('status', 'pending')->count(),
            'total_paid_amount' => $quotas->where('status', 'paid')->sum('amount'),
            'total_pending_amount' => $quotas->where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'summary' => $summary,
            'quotas' => $quotas,
        ]);
    }
}
