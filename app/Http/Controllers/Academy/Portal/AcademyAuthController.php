<?php

namespace App\Http\Controllers\Academy\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

class AcademyAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'dni' => 'required|string',
            'password' => 'required|string',
        ]);

        $student = DB::table('academy_students')
            ->where('dni', $credentials['dni'])
            ->first();

        if (!$student || !Hash::check($credentials['password'], $student->password)) {
            return response()->json(['message' => 'DNI o contraseña incorrectos'], 401);
        }

        if (!$student->is_active) {
            return response()->json(['message' => 'Alumno deshabilitado'], 401);
        }

        $companyDb = Config::get('database.connections.mysql.database');
        $token = base64_encode($student->id . ':' . $companyDb . ':' . time());

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $student->id,
                'dni' => $student->dni,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'email' => $student->email,
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

        $studentId = $parts[0];
        $student = DB::table('academy_students')->find($studentId);

        if (!$student || !$student->is_active) {
            return response()->json(['message' => 'Alumno no encontrado'], 401);
        }

        return response()->json([
            'id' => $student->id,
            'dni' => $student->dni,
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
        ]);
    }

    public function changePassword(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $parts = explode(':', base64_decode($token));
        $studentId = $parts[0] ?? null;

        if (!$studentId) {
            return response()->json(['message' => 'Token inválido'], 401);
        }

        $student = DB::table('academy_students')->find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Alumno no encontrado'], 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        if (!Hash::check($validated['current_password'], $student->password)) {
            return response()->json(['message' => 'Contraseña actual incorrecta'], 401);
        }

        DB::table('academy_students')
            ->where('id', $studentId)
            ->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json(['message' => 'Contraseña actualizada']);
    }

    public function logout(Request $request)
    {
        return response()->json(['message' => 'Sesión cerrada']);
    }
}
