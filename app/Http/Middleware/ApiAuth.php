<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json(['error' => 'No autenticado'], 401);
        }
        
        // Find user by token in global_users or users table
        $user = DB::table('global_users')->where('api_token', $token)->first();
        
        if (!$user) {
            // Try to find in session token
            $user = DB::table('global_users')
                ->whereRaw("CONCAT('erden_', id) = ?", [$token])
                ->first();
        }
        
        if (!$user) {
            // Try session-based authentication
            $userId = $request->session()->get('user.id');
            if ($userId) {
                $user = DB::table('global_users')->find($userId);
            }
        }
        
        if (!$user) {
            return response()->json(['error' => 'No autenticado'], 401);
        }
        
        // Set the user in auth
        auth()->setUser($user);
        
        return $next($request);
    }
}