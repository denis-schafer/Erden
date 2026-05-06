<?php

namespace App\Http\Controllers\Pos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Events\UserDisabled;
use App\Events\UserEnabled;

class PosUserController extends Controller
{
    public function index()
    {
        $users = DB::table('users')
            ->select('users.*', 'roles.name as role_name')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->orderBy('users.name')
            ->get();
        
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|max:100',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required|exists:roles,id',
            'enable' => 'boolean',
            'printer_ip' => 'nullable|string',
            'printer_port' => 'nullable|integer',
            'printer_type' => 'nullable|string',
            'printer_width' => 'nullable|integer|in:80,50',
            'enable_print' => 'boolean',
            'mercadopago_qr_enabled' => 'boolean',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['enable'] = $validated['enable'] ?? true;
        
        $validated['printer_port'] = $validated['printer_port'] ?? 9100;
        $validated['printer_type'] = $validated['printer_type'] ?? 'raw';
        $validated['printer_width'] = $validated['printer_width'] ?? 80;
        $enablePrintValue = $request->input('enable_print', null);
        $validated['enable_print'] = in_array($enablePrintValue, ['1', 'true', true, 1], true);
        $mercadoPagoValue = $request->input('mercadopago_qr_enabled', null);
        $validated['mercadopago_qr_enabled'] = in_array($mercadoPagoValue, ['1', 'true', true, 1], true);
        
        unset($validated['mercadopago_enable_qr']); // Remove if exists from old request

        $id = DB::table('users')->insertGetId($validated + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id, 'message' => 'Usuario creado']);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,' . $id . '|max:100',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'role_id' => 'required|exists:roles,id',
            'enable' => 'boolean',
            'printer_ip' => 'nullable|string',
            'printer_port' => 'nullable|integer',
            'printer_type' => 'nullable|string',
            'printer_width' => 'nullable|integer|in:80,50',
            'enable_print' => 'boolean',
            'mercadopago_qr_enabled' => 'boolean',
        ];

        if ($request->has('password') && $request->password) {
            $rules['password'] = 'string|min:6';
        }

        $validated = $request->validate($rules);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $validated['printer_port'] = $validated['printer_port'] ?? 9100;
        $validated['printer_type'] = $validated['printer_type'] ?? 'raw';
        $validated['printer_width'] = $validated['printer_width'] ?? 80;
        
        $enablePrintValue = $request->input('enable_print', null);
        $validated['enable_print'] = in_array($enablePrintValue, ['1', 'true', true, 1], true);
        
        $mercadopagoQrValue = $request->input('mercadopago_qr_enabled', null);
        $validated['mercadopago_qr_enabled'] = in_array($mercadopagoQrValue, ['1', 'true', true, 1], true);
        
        unset($validated['mercadopago_enable_qr']); // Remove if exists from old request

        DB::table('users')->where('id', $id)->update($validated + ['updated_at' => now()]);
        
        $updatedUser = DB::table('users')->find($id);
        
        event(new \App\Events\UserSettingsUpdated((array) $updatedUser));

        return response()->json(['message' => 'Usuario actualizado']);
    }

    public function toggleStatus($id)
    {
        $user = DB::table('users')->find($id);
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $newStatus = !$user->enable;
        
        DB::table('users')->where('id', $id)->update([
            'enable' => $newStatus,
            'updated_at' => now(),
        ]);

        if (!$newStatus) {
            event(new UserDisabled($id));
            // Log: usuario deshabilitado
            \App\Services\PosLogService::writeLog(
                'usuarios',
                'user_disabled',
                'Usuario ' . $user->username . ' (ID: ' . $id . ') deshabilitado',
                auth()->id()
            );
        } else {
            event(new UserEnabled($id));
            // Log: usuario habilitado
            \App\Services\PosLogService::writeLog(
                'usuarios',
                'user_enabled',
                'Usuario ' . $user->username . ' (ID: ' . $id . ') habilitado',
                auth()->id()
            );
        }
        
        \Log::info("User {$id} status changed to: " . ($newStatus ? 'enabled' : 'disabled'));
        
        return response()->json(['message' => 'Estado actualizado']);
    }

    public function destroy($id)
    {
        DB::table('users')->where('id', $id)->delete();
        return response()->json(['message' => 'Usuario eliminado']);
    }

    public function me(Request $request)
    {
        $userId = $request->session()->get('user.id');
        
        if (!$userId) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $user = DB::table('users')
            ->select('users.*', 'roles.name as role_name')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $userId)
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($user);
    }

    public function updatePrinterConfig(Request $request, $id)
    {
        $validated = $request->validate([
            'printer_ip' => 'nullable|string',
            'printer_port' => 'nullable|integer',
            'printer_type' => 'nullable|string',
        ]);

        DB::table('users')->where('id', $id)->update($validated + ['updated_at' => now()]);

        return response()->json(['message' => 'Configuración de impresora actualizada']);
    }
}
