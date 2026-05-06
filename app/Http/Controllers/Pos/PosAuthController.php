<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Controller;

class PosAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = DB::table('users')->where('username', $credentials['username'])->first();

        if (!$user || !password_verify($credentials['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        if (!$user->enable) {
            return response()->json(['message' => 'Usuario deshabilitado'], 401);
        }

        $role = DB::table('roles')->find($user->role_id);
        $companyDb = Config::get('database.connections.mysql.database');

        $token = base64_encode($user->id . ':' . $companyDb . ':' . time());

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'role_id' => $user->role_id,
                'role_name' => $role->name ?? 'unknown',
                'printer_ip' => $user->printer_ip,
                'printer_port' => $user->printer_port,
                'printer_type' => $user->printer_type,
            ],
            'company' => [
                'db' => $companyDb
            ]
        ]);
    }

    public function logout(Request $request)
    {
        return response()->json(['message' => 'Logged out']);
    }

    public function currentUser(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No authenticated'], 401);
        }

        // Check if it's a JWT token (main system token) or POS token (base64 encoded)
        if (strpos($token, '.') !== false) {
            // It's a JWT from main system - get user from main auth
            $userId = $request->user()?->id;
            if (!$userId) {
                // Try to get user from JWT claims
                try {
                    $decoded = json_decode(base64_decode(str_replace('Bearer ', '', $token)), true);
                    $userId = $decoded['sub'] ?? null;
                } catch (\Exception $e) {
                    return response()->json(['message' => 'Invalid token'], 401);
                }
            }
            
            if (!$userId) {
                return response()->json(['message' => 'User not found'], 401);
            }
            
            $user = DB::table('users')->find($userId);
            if (!$user || !($user->enable ?? true)) {
                return response()->json(['message' => 'User not found or disabled'], 401);
            }
            
            $role = DB::table('roles')->find($user->role_id);
            
            return response()->json([
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'role_id' => $user->role_id,
                'role_name' => $role->name ?? 'unknown',
                'printer_ip' => $user->printer_ip ?? '',
                'printer_port' => $user->printer_port ?? 9100,
                'printer_type' => $user->printer_type ?? 'raw',
                'enable_print' => $user->enable_print ?? false,
            ]);
        }

        // It's a POS token (base64 encoded format)
        $parts = explode(':', base64_decode($token));
        if (count($parts) < 3) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        $userId = $parts[0];
        $user = DB::table('users')->find($userId);
        
        if (!$user || !($user->enable ?? true)) {
            return response()->json(['message' => 'User not found or disabled'], 401);
        }

        $role = DB::table('roles')->find($user->role_id);

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'role_id' => $user->role_id,
            'role_name' => $role->name ?? 'unknown',
            'printer_ip' => $user->printer_ip ?? '',
            'printer_port' => $user->printer_port ?? 9100,
            'printer_type' => $user->printer_type ?? 'raw',
            'enable_print' => $user->enable_print ?? false,
        ]);
    }
}
