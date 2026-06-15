<?php

namespace App\Http\Controllers\Quota;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class QuotaAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'dni' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = DB::table('users')
            ->where('dni', $credentials['dni'])
            ->where('role_id', 4)
            ->when(Schema::hasColumn('users', 'deleted_at'), fn($q) => $q->whereNull('deleted_at'))
            ->first();

        if (!$user || !password_verify($credentials['password'], $user->password)) {
            return response()->json(['message' => 'DNI o contraseña incorrectos'], 401);
        }

        if (isset($user->enable) && !$user->enable) {
            return response()->json(['message' => 'Usuario deshabilitado'], 401);
        }

        $companyDb = Config::get('database.connections.mysql.database');
        $token = base64_encode($user->id . ':' . $companyDb . ':' . time());

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'dni' => $user->dni,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'name' => $user->name,
                'phone' => $user->phone,
            ],
        ]);
    }

    public function currentUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $parts = explode(':', base64_decode($token));
        if (count($parts) < 2) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $userId = $parts[0];
        $user = DB::table('users')->find($userId);

        if (!$user || !($user->enable ?? true) || $user->role_id != 4) {
            return response()->json(['message' => 'Usuario no encontrado'], 401);
        }

        return response()->json([
            'id' => $user->id,
            'dni' => $user->dni,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $user->name,
            'phone' => $user->phone,
        ]);
    }

    public function changePassword(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $parts = explode(':', base64_decode($token));
        $userId = $parts[0] ?? null;

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $user = DB::table('users')->find($userId);
        if (!$user || !password_verify($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Contraseña actual incorrecta'], 401);
        }

        DB::table('users')->where('id', $userId)->update([
            'password' => Hash::make($validated['new_password']),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada']);
    }

    public function updateProfile(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $parts = explode(':', base64_decode($token));
        $userId = $parts[0] ?? null;

        $validated = $request->validate([
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
        ]);

        $data = [];
        if (isset($validated['phone'])) $data['phone'] = $validated['phone'];
        if (isset($validated['address'])) $data['address'] = $validated['address'];
        $data['updated_at'] = now();

        if (!empty($data)) {
            DB::table('users')->where('id', $userId)->update($data);
        }

        return response()->json(['success' => true, 'message' => 'Perfil actualizado']);
    }

    public function logout(Request $request)
    {
        return response()->json(['message' => 'Sesión cerrada']);
    }
}
