<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class QuotaPaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('quota_payments')
            ->join('users', 'quota_payments.partner_id', '=', 'users.id')
            ->leftJoin('users as collectors', 'quota_payments.paid_by', '=', 'collectors.id')
            ->select(
                'quota_payments.*',
                'users.name as partner_name',
                'users.dni',
                'collectors.name as paid_by_name'
            );

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.dni', 'like', "%{$search}%");
            });
        }

        if ($method = $request->get('payment_method')) {
            $query->where('quota_payments.payment_method', $method);
        }

        if ($rendered = $request->get('rendered')) {
            $query->where('quota_payments.rendered', $rendered === 'true');
        }

        $allowedSortFields = ['id', 'partner_name', 'dni', 'total_amount', 'payment_method', 'paid_by_name', 'paid_at'];
        $sortField = $request->get('sort_field');
        $sortDir = $request->get('sort_dir', 'desc');

        if ($sortField && in_array($sortField, $allowedSortFields)) {
            $fieldMap = [
                'id' => 'quota_payments.id',
                'partner_name' => 'users.name',
                'dni' => 'users.dni',
                'total_amount' => 'quota_payments.total_amount',
                'payment_method' => 'quota_payments.payment_method',
                'paid_by_name' => 'collectors.name',
                'paid_at' => 'quota_payments.paid_at',
            ];
            $query->orderBy($fieldMap[$sortField], $sortDir);
        } else {
            $query->orderBy('quota_payments.paid_at', 'desc');
        }

        $payments = $query->paginate($request->get('per_page', 10));

        foreach ($payments as $payment) {
            $payment->items = DB::table('quota_payment_items')
                ->join('quotas', 'quota_payment_items.quota_id', '=', 'quotas.id')
                ->select('quota_payment_items.*', 'quotas.installment_number', 'quotas.type')
                ->where('quota_payment_items.quota_payment_id', $payment->id)
                ->get();
        }

        return response()->json($payments);
    }

    public function render(Request $request, $id)
    {
        $payment = DB::table('quota_payments')->find($id);
        if (!$payment) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        if ($payment->rendered) {
            return response()->json(['message' => 'Este pago ya fue rendido'], 400);
        }

        $sessionUser = $request->session()->get('user');
        $userId = $sessionUser['id'] ?? null;
        $roleId = $sessionUser['role_id'] ?? null;

        // Only admin or the user who collected the payment can render it
        if ($roleId !== 1 && $payment->paid_by !== $userId) {
            return response()->json(['message' => 'Solo quien cobró puede rendir este pago'], 403);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01|max:' . $payment->total_amount,
        ]);

        $renderAmount = $validated['amount'] ?? $payment->total_amount;
        $now = now();

        DB::table('quota_payments')->where('id', $id)->update([
            'rendered' => true,
            'rendered_amount' => $renderAmount,
            'rendered_at' => $now,
            'rendered_by' => $userId,
            'updated_at' => $now,
        ]);

        $items = DB::table('quota_payment_items')->where('quota_payment_id', $id)->get();
        $quotaIds = [];
        foreach ($items as $item) {
            $quotaIds[] = $item->quota_id;
            DB::table('quotas')->where('id', $item->quota_id)->update([
                'rendered' => true,
                'rendered_at' => $now,
                'rendered_by' => $userId,
                'updated_at' => $now,
            ]);
        }

        // Broadcast via WebSocket
        try {
            $companyDb = Config::get('database.connections.mysql.database');
            event(new \App\Events\QuotaRenderedUpdated(
                (int) $id,
                $quotaIds,
                true,
                $companyDb
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('QuotaRenderedUpdated broadcast error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Pago rendido correctamente',
        ]);
    }

    public function cashierBalance(Request $request)
    {
        $balance = DB::table('quotas')
            ->select(
                'payment_method',
                DB::raw('SUM(amount) as total_collected'),
                DB::raw('SUM(CASE WHEN rendered = 1 THEN amount ELSE 0 END) as total_rendered')
            )
            ->where('status', 'paid')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $cashCollected = $balance['cash']->total_collected ?? 0;
        $cashRendered = $balance['cash']->total_rendered ?? 0;
        $digitalCollected = $balance['digital']->total_collected ?? 0;
        $digitalRendered = $balance['digital']->total_rendered ?? 0;

        $cashByCashier = DB::table('quotas')
            ->join('users', 'quotas.paid_by', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'quotas.payment_method',
                DB::raw('SUM(quotas.amount) as total_collected'),
                DB::raw('SUM(CASE WHEN quotas.rendered = 1 THEN quotas.amount ELSE 0 END) as total_rendered')
            )
            ->where('quotas.status', 'paid')
            ->where('quotas.payment_method', 'cash')
            ->groupBy('users.id', 'users.name', 'quotas.payment_method')
            ->get();

        $digitalByCashier = DB::table('quotas')
            ->join('users', 'quotas.paid_by', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                'quotas.payment_method',
                DB::raw('SUM(quotas.amount) as total_collected'),
                DB::raw('SUM(CASE WHEN quotas.rendered = 1 THEN quotas.amount ELSE 0 END) as total_rendered')
            )
            ->where('quotas.status', 'paid')
            ->where('quotas.payment_method', 'digital')
            ->groupBy('users.id', 'users.name', 'quotas.payment_method')
            ->get();

        return response()->json([
            'total' => [
                'cash_collected' => $cashCollected,
                'cash_rendered' => $cashRendered,
                'cash_pending' => $cashCollected - $cashRendered,
                'digital_collected' => $digitalCollected,
                'digital_rendered' => $digitalRendered,
                'digital_pending' => $digitalCollected - $digitalRendered,
            ],
            'by_cashier' => [
                'cash' => $cashByCashier,
                'digital' => $digitalByCashier,
            ],
        ]);
    }
}
