<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class QuotaUserController extends Controller
{
    public function index()
    {
        $query = DB::table('users')
            ->select('users.id', 'users.username', 'users.name', 'users.role_id', 'users.enable', 'roles.name as role_name')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.role_id', '!=', 4)
            ->orderBy('users.name');

        if (Schema::hasColumn('users', 'deleted_at')) {
            $query->whereNull('users.deleted_at');
        }

        $users = $query->get();

        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'name' => 'required|string|max:255',
            'password' => 'required|string|min:4',
            'role_id' => 'required|integer|exists:roles,id',
            'enable' => 'boolean',
        ]);

        $userId = DB::table('users')->insertGetId([
            'username' => $validated['username'],
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'enable' => $validated['enable'] ?? true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'id' => $userId,
            'message' => 'Usuario creado correctamente',
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = DB::table('users')->where('id', $id)->where('role_id', '!=', 4)->first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validated = $request->validate([
            'username' => 'required|string|max:50|unique:users,username,' . $id,
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:4',
            'role_id' => 'required|integer|exists:roles,id',
            'enable' => 'boolean',
        ]);

        $data = [
            'username' => $validated['username'],
            'name' => $validated['name'],
            'role_id' => $validated['role_id'],
            'enable' => $validated['enable'] ?? true,
            'updated_at' => now(),
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        DB::table('users')->where('id', $id)->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado correctamente',
        ]);
    }

    public function destroy($id)
    {
        $user = DB::table('users')->where('id', $id)->where('role_id', '!=', 4)->first();
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        DB::table('users')->where('id', $id)->update(['deleted_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado correctamente',
        ]);
    }

    public function roles()
    {
        $roles = DB::table('roles')
            ->whereIn('name', ['admin', 'cashier', 'stats', 'limited_collector'])
            ->get();

        return response()->json($roles);
    }
}
