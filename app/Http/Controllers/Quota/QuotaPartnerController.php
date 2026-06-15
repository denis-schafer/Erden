<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class QuotaPartnerController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('users')
            ->leftJoin('quota_partner_config', 'users.id', '=', 'quota_partner_config.partner_id')
            ->where('users.role_id', 4);

        if (Schema::hasColumn('users', 'deleted_at')) {
            $query->whereNull('users.deleted_at');
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('users.first_name', 'like', "%{$search}%")
                    ->orWhere('users.last_name', 'like', "%{$search}%")
                    ->orWhere('users.dni', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%");
            });
        }

        if ($request->get('status') === 'active') {
            $query->where('users.enable', true);
        } elseif ($request->get('status') === 'inactive') {
            $query->where('users.enable', false);
        }

        if ($request->get('has_debt') === 'true') {
            $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('quotas')
                    ->whereColumn('quotas.partner_id', 'users.id')
                    ->where('quotas.status', 'pending');
            });
        }

        $partners = $query->select(
            'users.*',
            'quota_partner_config.amount as custom_amount',
            'quota_partner_config.pool_fee_amount as custom_pool_fee_amount',
            'quota_partner_config.is_exempt',
            'quota_partner_config.quota_plan_id'
        )
        ->orderBy('users.last_name')
        ->orderBy('users.first_name')
        ->paginate($request->get('per_page', 50));

        $plans = DB::table('quota_plans')->pluck('name', 'id');

        $partners->getCollection()->transform(function ($partner) use ($plans) {
            $partner->quotas_count = DB::table('quotas')->where('partner_id', $partner->id)->count();
            $partner->paid_quotas = DB::table('quotas')->where('partner_id', $partner->id)->where('status', 'paid')->count();
            $partner->pending_quotas = DB::table('quotas')->where('partner_id', $partner->id)->where('status', 'pending')->count();
            $partner->total_debt = DB::table('quotas')->where('partner_id', $partner->id)->where('status', 'pending')->sum('amount');
            $partner->plan_name = $plans[$partner->quota_plan_id] ?? null;
            return $partner;
        });

        return response()->json($partners);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'dni' => 'required|string|max:20|unique:users,dni',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'enable' => 'boolean',
            'quota_plan_id' => 'nullable|exists:quota_plans,id',
        ]);

        $partnerRole = DB::table('roles')->where('name', 'partner')->first();
        if (!$partnerRole) {
            return response()->json(['message' => 'Role partner no encontrado'], 500);
        }

        $password = $validated['dni'];

        $userId = DB::table('users')->insertGetId([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'dni' => $validated['dni'],
            'username' => $validated['dni'],
            'password' => Hash::make($password),
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'role_id' => $partnerRole->id,
            'enable' => $validated['enable'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (!empty($validated['quota_plan_id'])) {
            DB::table('quota_partner_config')->updateOrInsert(
                ['partner_id' => $userId, 'quota_plan_id' => $validated['quota_plan_id']],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Socio creado correctamente',
            'partner' => [
                'id' => $userId,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'dni' => $validated['dni'],
                'default_password' => $password,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $partner = DB::table('users')->where('id', $id)->where('role_id', 4)->first();
        if (!$partner) {
            return response()->json(['message' => 'Socio no encontrado'], 404);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'dni' => 'required|string|max:20|unique:users,dni,' . $id,
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'enable' => 'boolean',
            'quota_plan_id' => 'nullable|exists:quota_plans,id',
        ]);

        $data = [
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'dni' => $validated['dni'],
            'username' => $validated['dni'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'enable' => $validated['enable'] ?? true,
            'updated_at' => now(),
        ];

        // If DNI changed, update password too (password = DNI)
        if ($partner->dni !== $validated['dni']) {
            $data['password'] = Hash::make($validated['dni']);
        }

        DB::table('users')->where('id', $id)->update($data);

        if (array_key_exists('quota_plan_id', $validated)) {
            if (!empty($validated['quota_plan_id'])) {
                DB::table('quota_partner_config')->updateOrInsert(
                    ['partner_id' => $id, 'quota_plan_id' => $validated['quota_plan_id']],
                    ['updated_at' => now(), 'created_at' => now()]
                );
            } else {
                DB::table('quota_partner_config')->where('partner_id', $id)->delete();
            }
        }

        return response()->json(['success' => true, 'message' => 'Socio actualizado']);
    }

    public function destroy($id)
    {
        $partner = DB::table('users')->where('id', $id)->where('role_id', 4)->first();
        if (!$partner) {
            return response()->json(['message' => 'Socio no encontrado'], 404);
        }

        $pendingQuotas = DB::table('quotas')->where('partner_id', $id)->where('status', 'pending')->count();
        if ($pendingQuotas > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el socio porque tiene cuotas pendientes. Deshabilítelo en su lugar.',
            ], 400);
        }

        DB::table('users')->where('id', $id)->update(['deleted_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Socio eliminado']);
    }

    public function resetPassword($id)
    {
        $partner = DB::table('users')->where('id', $id)->where('role_id', 4)->first();
        if (!$partner) {
            return response()->json(['message' => 'Socio no encontrado'], 404);
        }

        $newPassword = $partner->dni;

        DB::table('users')->where('id', $id)->update([
            'password' => Hash::make($newPassword),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Contraseña reseteada',
            'new_password' => $newPassword,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $file = $request->file('file');
        $spreadsheet = IOFactory::load($file->getPathname());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            return response()->json(['message' => 'El archivo no contiene datos'], 400);
        }

        $header = array_map('trim', $rows[0]);
        $headerLower = array_map('strtolower', $header);

        $dniIdx = array_search('dni', $headerLower);
        $nameIdx = array_search('nombre', $headerLower);
        $lastNameIdx = array_search('apellido', $headerLower);
        $phoneIdx = array_search('teléfono', $headerLower) ?? array_search('telefono', $headerLower);
        $addressIdx = array_search('dirección', $headerLower) ?? array_search('direccion', $headerLower);
        $amountIdx = array_search('importe cuota', $headerLower);
        $poolFeeIdx = array_search('dcho pileta', $headerLower) ?? array_search('derecho pileta', $headerLower);

        if ($dniIdx === false || $nameIdx === false || $lastNameIdx === false) {
            return response()->json([
                'message' => 'El archivo debe tener las columnas: DNI, Nombre, Apellido',
            ], 400);
        }

        $partnerRole = DB::table('roles')->where('name', 'partner')->first();
        if (!$partnerRole) {
            return response()->json(['message' => 'Role partner no encontrado'], 500);
        }

        $activePlan = DB::table('quota_plans')->where('is_active', true)->first();

        $imported = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rowNum = $i + 1;

            try {
                $dni = trim($row[$dniIdx] ?? '');
                $firstName = trim($row[$nameIdx] ?? '');
                $lastName = trim($row[$lastNameIdx] ?? '');

                if (empty($dni)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'DNI vacío'];
                    continue;
                }

                if (empty($firstName) || empty($lastName)) {
                    $errors[] = ['row' => $rowNum, 'message' => 'Nombre o Apellido vacío'];
                    continue;
                }

                $exists = DB::table('users')->where('dni', $dni)->exists();
                if ($exists) {
                    $errors[] = ['row' => $rowNum, 'message' => "DNI {$dni} ya existe"];
                    continue;
                }

                $userId = DB::table('users')->insertGetId([
                    'name' => "{$firstName} {$lastName}",
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'dni' => $dni,
                    'username' => $dni,
                    'password' => Hash::make($dni),
                    'phone' => $phoneIdx !== false ? ($row[$phoneIdx] ?? null) : null,
                    'address' => $addressIdx !== false ? ($row[$addressIdx] ?? null) : null,
                    'role_id' => $partnerRole->id,
                    'enable' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($activePlan && ($amountIdx !== false || $poolFeeIdx !== false)) {
                    $customAmount = $amountIdx !== false ? ($row[$amountIdx] ?? null) : null;
                    $customPoolFee = $poolFeeIdx !== false ? ($row[$poolFeeIdx] ?? null) : null;

                    if (!empty($customAmount) || !empty($customPoolFee)) {
                        DB::table('quota_partner_config')->updateOrInsert(
                            ['partner_id' => $userId, 'quota_plan_id' => $activePlan->id],
                            [
                                'amount' => !empty($customAmount) ? floatval($customAmount) : null,
                                'pool_fee_amount' => !empty($customPoolFee) ? floatval($customPoolFee) : null,
                                'updated_at' => now(),
                                'created_at' => now(),
                            ]
                        );
                    }
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $rowNum, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($rows) - 1,
        ]);
    }

    public function assignQuotas(Request $request, $id)
    {
        $partner = DB::table('users')->where('id', $id)->where('role_id', 4)->first();
        if (!$partner) {
            return response()->json(['message' => 'Socio no encontrado'], 404);
        }

        $validated = $request->validate([
            'quota_plan_id' => 'nullable|exists:quota_plans,id',
            'installments' => 'required|array',
            'installments.*' => 'integer|min:1',
            'include_pool_fees' => 'boolean',
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $planId = $validated['quota_plan_id'];
        if (empty($planId)) {
            $config = DB::table('quota_partner_config')
                ->where('partner_id', $id)
                ->first();
            if (!$config) {
                return response()->json(['message' => 'El socio no tiene un plan asignado'], 400);
            }
            $planId = $config->quota_plan_id;
        }

        $plan = DB::table('quota_plans')->find($planId);
        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        $config = DB::table('quota_partner_config')
            ->where('partner_id', $id)
            ->where('quota_plan_id', $plan->id)
            ->first();

        $amount = $config->amount ?? $plan->amount;
        $poolFeeAmount = $config->pool_fee_amount ?? $plan->pool_fee_amount;
        $poolFeeCount = $config->pool_fee_count ?? $plan->pool_fee_count;
        $year = $validated['year'];

        $created = 0;

        foreach ($validated['installments'] as $num) {
            $exists = DB::table('quotas')
                ->where('partner_id', $id)
                ->where('quota_plan_id', $plan->id)
                ->where('type', 'regular')
                ->where('installment_number', $num)
                ->whereYear('due_date', $year)
                ->exists();

            if (!$exists) {
                $dueDate = $this->calculateDueDate($plan, $num, $year);

                DB::table('quotas')->insert([
                    'partner_id' => $id,
                    'quota_plan_id' => $plan->id,
                    'type' => 'regular',
                    'installment_number' => $num,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $created++;
            }
        }

        if ($validated['include_pool_fees'] ?? false) {
            for ($i = 1; $i <= $poolFeeCount; $i++) {
                $exists = DB::table('quotas')
                    ->where('partner_id', $id)
                    ->where('quota_plan_id', $plan->id)
                    ->where('type', 'pool_fee')
                    ->where('installment_number', $i)
                    ->whereYear('due_date', $year)
                    ->exists();

                if (!$exists) {
                    $dueDate = $this->calculatePoolFeeDueDate($plan, $i, $year);

                    DB::table('quotas')->insert([
                        'partner_id' => $id,
                        'quota_plan_id' => $plan->id,
                        'type' => 'pool_fee',
                        'installment_number' => $i,
                        'amount' => $poolFeeAmount,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $created++;
                }
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'message' => "{$created} cuotas asignadas a {$partner->name} para el año {$year}",
        ]);
    }

    public function generateAll(Request $request)
    {
        $year = $request->integer('year', now()->year);

        if ($year < 2020 || $year > 2099) {
            return response()->json(['message' => 'Año inválido'], 400);
        }

        $configs = DB::table('quota_partner_config')
            ->join('quota_plans', 'quota_partner_config.quota_plan_id', '=', 'quota_plans.id')
            ->join('users', 'quota_partner_config.partner_id', '=', 'users.id')
            ->where('users.role_id', 4)
            ->where('users.enable', true)
            ->where('quota_partner_config.is_exempt', false)
            ->select('quota_partner_config.*', 'quota_plans.*', 'quota_plans.name as plan_name', 'users.name as partner_name', 'users.id as user_id');

        if (Schema::hasColumn('users', 'deleted_at')) {
            $configs->whereNull('users.deleted_at');
        }

        $configs = $configs->get();

        $generated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($configs as $config) {
            try {
                $plan = $config;
                $amount = $config->amount ?? $plan->amount;
                $poolFeeAmount = $config->pool_fee_amount ?? $plan->pool_fee_amount;
                $poolFeeCount = $config->pool_fee_count ?? $plan->pool_fee_count;

                if ($amount <= 0) continue;

                $existingRegular = DB::table('quotas')
                    ->where('partner_id', $config->user_id)
                    ->where('quota_plan_id', $config->quota_plan_id)
                    ->where('type', 'regular')
                    ->whereYear('due_date', $year)
                    ->count();

                if ($existingRegular > 0) {
                    $skipped++;
                    continue;
                }

                $monthsMap = [
                    'monthly' => 1, 'bimonthly' => 2, 'quarterly' => 3,
                    'four_monthly' => 4, 'biannual' => 6,
                ];
                $months = $monthsMap[$plan->frequency] ?? 1;

                for ($i = 1; $i <= $plan->installment_count; $i++) {
                    $month = ($i - 1) * $months + 1;
                    $y = $year;
                    if ($month > 12) {
                        $y += intdiv($month - 1, 12);
                        $month = (($month - 1) % 12) + 1;
                    }
                    $dueDate = "{$y}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";

                    DB::table('quotas')->insert([
                        'partner_id' => $config->user_id,
                        'quota_plan_id' => $config->quota_plan_id,
                        'type' => 'regular',
                        'installment_number' => $i,
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $generated++;
                }

                if ($poolFeeCount > 0) {
                    $poolMonths = [10, 11, 12, 1];
                    for ($i = 1; $i <= $poolFeeCount; $i++) {
                        $month = $poolMonths[($i - 1) % 4];
                        $y = $month >= 10 ? $year : $year + 1;
                        $dueDate = "{$y}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";

                        DB::table('quotas')->insert([
                            'partner_id' => $config->user_id,
                            'quota_plan_id' => $config->quota_plan_id,
                            'type' => 'pool_fee',
                            'installment_number' => $i,
                            'amount' => $poolFeeAmount,
                            'due_date' => $dueDate,
                            'status' => 'pending',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $generated++;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = ['partner' => $config->partner_name, 'message' => $e->getMessage()];
            }
        }

        return response()->json([
            'success' => true,
            'generated' => $generated,
            'skipped' => $skipped,
            'total' => $configs->count(),
            'year' => $year,
            'errors' => $errors,
            'message' => "{$generated} cuotas generadas, {$skipped} socios saltados (ya tenían cuotas para {$year})",
        ]);
    }

    private function calculateDueDate($plan, $installmentNumber, $year)
    {
        $monthsMap = [
            'monthly' => 1,
            'bimonthly' => 2,
            'quarterly' => 3,
            'four_monthly' => 4,
            'biannual' => 6,
        ];

        $months = $monthsMap[$plan->frequency] ?? 1;
        $month = ($installmentNumber - 1) * $months + 1;
        $y = $year;

        if ($month > 12) {
            $y += intdiv($month - 1, 12);
            $month = (($month - 1) % 12) + 1;
        }

        return "{$y}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    }

    private function calculatePoolFeeDueDate($plan, $installmentNumber, $year)
    {
        $months = [10, 11, 12, 1];
        $idx = ($installmentNumber - 1) % 4;
        $month = $months[$idx];
        $y = $month >= 10 ? $year : $year + 1;
        return "{$y}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    }
}
