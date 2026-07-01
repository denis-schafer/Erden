<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use App\Events\HairSalon\HairSalonJobCreated;
use App\Events\HairSalon\HairSalonStockUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HairSalonCashierController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:hairsalon_clients,id',
            'services' => 'required|array|min:1',
            'services.*.id' => 'required',
            'services.*.price' => 'required|numeric|min:0',
            'deductions' => 'nullable|array',
            'deductions.*.product_id' => 'required|exists:hairsalon_products,id',
            'deductions.*.quantity' => 'required|numeric|min:0',
            'discount' => 'numeric|min:0',
            'payment_method' => 'required|string|in:cash,transfer,mercadopago,other',
            'notes' => 'nullable|string',
        ]);

        $operatorId = session('user.id');
        if (!$operatorId) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $subtotal = 0;
        $jobId = DB::table('hairsalon_jobs')->insertGetId([
            'client_id' => $validated['client_id'],
            'operator_id' => $operatorId,
            'subtotal' => 0,
            'discount' => $validated['discount'] ?? 0,
            'total' => 0,
            'payment_method' => $validated['payment_method'],
            'status' => 'completed',
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $operator = DB::table('users')->find($operatorId);
        $client = DB::table('hairsalon_clients')->find($validated['client_id']);

        $servicesSnapshot = [];
        foreach ($validated['services'] as $svcInput) {
            $price = $svcInput['price'];
            $subtotal += $price;
            $svcId = $svcInput['id'];

            if ($svcId !== 'varios') {
                DB::table('hairsalon_job_services')->insert([
                    'job_id' => $jobId,
                    'service_id' => $svcId,
                    'price' => $price,
                ]);

                $svcRow = DB::table('hairsalon_services')->find($svcId);
                $servicesSnapshot[] = [
                    'id' => $svcId,
                    'name' => $svcRow->name ?? 'Servicio #' . $svcId,
                    'price' => $price,
                ];
            } else {
                $servicesSnapshot[] = [
                    'id' => 'varios',
                    'name' => 'Varios',
                    'price' => $price,
                ];
            }
        }

        $deducted = [];
        $deductionsSnapshot = [];
        foreach ($validated['deductions'] ?? [] as $d) {
            $qty = intval($d['quantity']);
            if ($qty <= 0) continue;

            $product = DB::table('hairsalon_products')->find($d['product_id']);
            if (!$product || $product->quantity <= 0) continue;

            $deductQty = min($qty, $product->quantity);
            $deductionsSnapshot[] = [
                'id' => $d['product_id'],
                'name' => $product->name,
                'quantity' => $deductQty,
            ];

            DB::table('hairsalon_products')
                ->where('id', $d['product_id'])
                ->decrement('quantity', $deductQty);

            DB::table('hairsalon_stock_movements')->insert([
                'product_id' => $d['product_id'],
                'type' => 'out',
                'quantity' => $deductQty,
                'reason' => 'Consumido en trabajo #' . $jobId,
                'operator_id' => $operatorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $deducted[] = $d['product_id'];
        }

        $discount = $validated['discount'] ?? 0;
        $total = max(0, $subtotal - $discount);

        DB::table('hairsalon_jobs')->where('id', $jobId)->update([
            'subtotal' => $subtotal,
            'total' => $total,
        ]);

        $movementData = [
            'type' => 'income',
            'concept' => 'Cobro de trabajo #' . $jobId,
            'amount' => $total,
            'payment_method' => $validated['payment_method'],
            'job_id' => $jobId,
            'cash_register_id' => null,
            'operator_id' => $operatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('hairsalon_cash_movements', 'detail')) {
            $movementData['detail'] = json_encode([
                'client_id' => $validated['client_id'],
                'client_name' => $client->name ?? 'Cliente #' . $validated['client_id'],
                'operator_id' => $operatorId,
                'operator_name' => $operator->name ?? 'Operador',
                'services' => $servicesSnapshot,
                'deductions' => $deductionsSnapshot,
                'discount' => $discount,
                'notes' => $validated['notes'] ?? null,
                'payment_method' => $validated['payment_method'],
            ]);
        }

        DB::table('hairsalon_cash_movements')->insert($movementData);

        $job = DB::table('hairsalon_jobs')->find($jobId);

        if (!empty($deducted)) {
            broadcast(new HairSalonStockUpdated([
                'product_id' => $deducted[0],
                'type' => 'out',
            ]));
        }

        broadcast(new HairSalonJobCreated($job));

        return response()->json([
            'success' => true,
            'job' => $job,
            'deducted_products' => $deducted,
        ]);
    }

    public function show($id)
    {
        $job = DB::table('hairsalon_jobs as j')
            ->join('hairsalon_clients as c', 'j.client_id', '=', 'c.id')
            ->join('users as u', 'j.operator_id', '=', 'u.id')
            ->select('j.*', 'c.name as client_name', 'c.phone as client_phone', 'u.name as operator_name')
            ->where('j.id', $id)
            ->first();

        if (!$job) {
            return response()->json(['message' => 'Trabajo no encontrado'], 404);
        }

        $jobServices = DB::table('hairsalon_job_services as js')
            ->join('hairsalon_services as s', 'js.service_id', '=', 's.id')
            ->select('s.name', 'js.price')
            ->where('js.job_id', $id)
            ->get();

        $cashMovement = DB::table('hairsalon_cash_movements')
            ->where('job_id', $id)
            ->first();

        $deductions = [];
        if ($cashMovement && isset($cashMovement->detail) && $cashMovement->detail) {
            $detail = json_decode($cashMovement->detail, true);
            $deductions = $detail['deductions'] ?? [];
        }

        if (empty($deductions)) {
            $deductions = DB::table('hairsalon_stock_movements as sm')
                ->join('hairsalon_products as p', 'sm.product_id', '=', 'p.id')
                ->select('p.name', 'sm.quantity')
                ->where('sm.reason', 'like', "%#{$id}%")
                ->get();
        }

        return response()->json([
            'job' => $job,
            'services' => $jobServices,
            'deductions' => $deductions,
        ]);
    }

    public function currentUser()
    {
        $user = session('user');
        return response()->json($user);
    }
}
