<?php

namespace App\Http\Controllers\HairSalon;

use App\Http\Controllers\Controller;
use App\Events\HairSalon\HairSalonUserUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class HairSalonUserController extends Controller
{
    public function index(Request $request)
    {
        $users = DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->select('users.*', 'roles.name as role_name')
            ->where('users.username', '!=', 'admin')
            ->orderBy('users.name')
            ->paginate($request->get('per_page', 50));

        return response()->json($users);
    }

    public function roles()
    {
        $roles = DB::table('roles')->whereIn('id', [1, 6])->get();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'username' => 'required|string|max:50|unique:users,username',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'enable' => 'boolean',
        ]);

        $id = DB::table('users')->insertGetId([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'enable' => $validated['enable'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        broadcast(new HairSalonUserUpdated(DB::table('users')->find($id), 'created'));

        return response()->json([
            'success' => true,
            'user' => DB::table('users')->find($id),
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = DB::table('users')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'username' => 'required|string|max:50|unique:users,username,' . $id,
            'password' => 'nullable|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'enable' => 'boolean',
        ]);

        $data = [
            'name' => $validated['name'],
            'username' => $validated['username'],
            'role_id' => $validated['role_id'],
            'enable' => $validated['enable'] ?? true,
            'updated_at' => now(),
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        DB::table('users')->where('id', $id)->update($data);

        broadcast(new HairSalonUserUpdated(DB::table('users')->find($id), 'updated'));

        return response()->json([
            'success' => true,
            'user' => DB::table('users')->find($id),
        ]);
    }

    public function toggleStatus($id)
    {
        $user = DB::table('users')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        DB::table('users')->where('id', $id)->update([
            'enable' => !$user->enable,
            'updated_at' => now(),
        ]);

        broadcast(new HairSalonUserUpdated(DB::table('users')->find($id), 'updated'));

        return response()->json([
            'success' => true,
            'user' => DB::table('users')->find($id),
        ]);
    }

    public function destroy($id)
    {
        $user = DB::table('users')->find($id);
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if ($user->username === 'admin') {
            return response()->json(['message' => 'No se puede eliminar el usuario admin'], 400);
        }

        DB::table('users')->where('id', $id)->delete();

        broadcast(new HairSalonUserUpdated(['id' => $id], 'deleted'));

        return response()->json(['success' => true, 'message' => 'Usuario eliminado']);
    }
}
