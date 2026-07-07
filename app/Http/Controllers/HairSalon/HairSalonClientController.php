<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use App\Events\HairSalon\HairSalonClientUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HairSalonClientController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('hairsalon_clients');

        if (!$request->get('include_deleted')) {
            $query->whereNull('deleted_at');
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderBy('name')->paginate($request->get('per_page', 50))
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|string|email|max:200',
            'address' => 'nullable|string|max:300',
            'notes' => 'nullable|string',
        ]);

        $id = DB::table('hairsalon_clients')->insertGetId([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client = DB::table('hairsalon_clients')->find($id);
        broadcast(new HairSalonClientUpdated($client, 'created'));

        return response()->json(['success' => true, 'client' => $client]);
    }

    public function update(Request $request, $id)
    {
        $client = DB::table('hairsalon_clients')->find($id);
        if (!$client) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|string|email|max:200',
            'address' => 'nullable|string|max:300',
            'notes' => 'nullable|string',
        ]);

        DB::table('hairsalon_clients')->where('id', $id)->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'updated_at' => now(),
        ]);

        $client = DB::table('hairsalon_clients')->find($id);
        broadcast(new HairSalonClientUpdated($client, 'updated'));

        return response()->json(['success' => true, 'client' => $client]);
    }

    public function destroy($id)
    {
        $client = DB::table('hairsalon_clients')->find($id);
        if (!$client) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        DB::table('hairsalon_clients')->where('id', $id)->update(['deleted_at' => now()]);
        broadcast(new HairSalonClientUpdated((object)['id' => $id, 'deleted_at' => now()], 'deleted'));

        return response()->json(['success' => true, 'message' => 'Cliente desactivado']);
    }

    public function restore($id)
    {
        $client = DB::table('hairsalon_clients')->where('id', $id)->whereNotNull('deleted_at')->first();
        if (!$client) {
            return response()->json(['message' => 'Cliente no encontrado o no está eliminado'], 404);
        }

        DB::table('hairsalon_clients')->where('id', $id)->update(['deleted_at' => null, 'updated_at' => now()]);
        $client = DB::table('hairsalon_clients')->find($id);
        broadcast(new HairSalonClientUpdated($client, 'restored'));

        return response()->json(['success' => true, 'message' => 'Cliente recuperado', 'client' => $client]);
    }

    public function jobs($id)
    {
        $client = DB::table('hairsalon_clients')->find($id);
        if (!$client) {
            return response()->json(['message' => 'Cliente no encontrado'], 404);
        }

        $jobs = DB::table('hairsalon_jobs as j')
            ->join('users as u', 'j.operator_id', '=', 'u.id')
            ->leftJoin('hairsalon_job_services as js', 'j.id', '=', 'js.job_id')
            ->leftJoin('hairsalon_services as s', 'js.service_id', '=', 's.id')
            ->select(
                'j.id', 'j.client_id', 'j.operator_id', 'j.total',
                'j.payment_method', 'j.status', 'j.notes',
                'j.discount', 'j.created_at', 'j.updated_at',
                'u.name as operator_name',
                DB::raw('GROUP_CONCAT(s.name SEPARATOR ", ") as service_names')
            )
            ->where('j.client_id', $id)
            ->groupBy('j.id', 'j.client_id', 'j.operator_id', 'j.total',
                'j.payment_method', 'j.status', 'j.notes',
                'j.discount', 'j.created_at', 'j.updated_at', 'u.name')
            ->orderBy('j.created_at', 'desc')
            ->paginate(50);

        return response()->json($jobs);
    }
}
