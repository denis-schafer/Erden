<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use App\Events\HairSalon\HairSalonAppointmentUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HairSalonAppointmentController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfWeek()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->endOfWeek()->format('Y-m-d'));

        $query = DB::table('hairsalon_appointments as a')
            ->join('users as u', 'a.operator_id', '=', 'u.id')
            ->leftJoin('hairsalon_clients as c', 'a.client_id', '=', 'c.id')
            ->select('a.*', 'u.name as operator_name', 'c.name as linked_client_name')
            ->whereDate('a.start_time', '>=', $startDate)
            ->whereDate('a.start_time', '<=', $endDate);

        if ($request->get('operator_id')) {
            $query->where('a.operator_id', $request->get('operator_id'));
        }

        $appointments = $query->orderBy('a.start_time')->get();

        $operators = DB::table('users')
            ->whereIn('role_id', [1, 6])
            ->where('username', '!=', 'admin')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'appointments' => $appointments,
            'operators' => $operators,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:200',
            'client_id' => 'nullable|exists:hairsalon_clients,id',
            'operator_id' => 'required|exists:users,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:hairsalon_services,id',
            'custom_services' => 'nullable|array',
            'custom_services.*.name' => 'required|string|max:200',
            'custom_services.*.price' => 'nullable|numeric|min:0',
            'custom_services.*.duration_min' => 'nullable|integer|min:0',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration_min' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:scheduled,in_progress,completed,cancelled',
            'color' => 'nullable|string|max:7',
        ]);

        $operatorId = session('user.id');

        $id = DB::table('hairsalon_appointments')->insertGetId([
            'client_name' => $validated['client_name'],
            'client_id' => $validated['client_id'] ?? null,
            'operator_id' => $validated['operator_id'],
            'service_ids' => isset($validated['service_ids']) ? json_encode($validated['service_ids']) : null,
            'custom_services' => isset($validated['custom_services']) ? json_encode($validated['custom_services']) : null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'] ?? null,
            'duration_min' => $validated['duration_min'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'] ?? 'scheduled',
            'color' => $validated['color'] ?? null,
            'created_by' => $operatorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $appointment = DB::table('hairsalon_appointments')->find($id);
        broadcast(new HairSalonAppointmentUpdated($appointment, 'created'));

        return response()->json(['success' => true, 'appointment' => $appointment]);
    }

    public function update(Request $request, $id)
    {
        $appointment = DB::table('hairsalon_appointments')->find($id);
        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        $validated = $request->validate([
            'client_name' => 'required|string|max:200',
            'client_id' => 'nullable|exists:hairsalon_clients,id',
            'operator_id' => 'required|exists:users,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'exists:hairsalon_services,id',
            'custom_services' => 'nullable|array',
            'custom_services.*.name' => 'required|string|max:200',
            'custom_services.*.price' => 'nullable|numeric|min:0',
            'custom_services.*.duration_min' => 'nullable|integer|min:0',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration_min' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:scheduled,in_progress,completed,cancelled',
            'color' => 'nullable|string|max:7',
        ]);

        DB::table('hairsalon_appointments')->where('id', $id)->update([
            'client_name' => $validated['client_name'],
            'client_id' => $validated['client_id'] ?? null,
            'operator_id' => $validated['operator_id'],
            'service_ids' => isset($validated['service_ids']) ? json_encode($validated['service_ids']) : null,
            'custom_services' => isset($validated['custom_services']) ? json_encode($validated['custom_services']) : null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'] ?? null,
            'duration_min' => $validated['duration_min'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'] ?? 'scheduled',
            'color' => $validated['color'] ?? null,
            'updated_at' => now(),
        ]);

        $appointment = DB::table('hairsalon_appointments')->find($id);
        broadcast(new HairSalonAppointmentUpdated($appointment, 'updated'));

        return response()->json(['success' => true, 'appointment' => $appointment]);
    }

    public function updateStatus(Request $request, $id)
    {
        $appointment = DB::table('hairsalon_appointments')->find($id);
        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:scheduled,in_progress,completed,cancelled',
        ]);

        DB::table('hairsalon_appointments')->where('id', $id)->update([
            'status' => $validated['status'],
            'updated_at' => now(),
        ]);

        $appointment = DB::table('hairsalon_appointments')->find($id);
        broadcast(new HairSalonAppointmentUpdated($appointment, 'status_changed'));

        return response()->json(['success' => true, 'appointment' => $appointment]);
    }

    public function destroy($id)
    {
        $appointment = DB::table('hairsalon_appointments')->find($id);
        if (!$appointment) {
            return response()->json(['message' => 'Turno no encontrado'], 404);
        }

        DB::table('hairsalon_appointments')->where('id', $id)->delete();
        broadcast(new HairSalonAppointmentUpdated(['id' => $id], 'deleted'));

        return response()->json(['success' => true, 'message' => 'Turno eliminado']);
    }
}
