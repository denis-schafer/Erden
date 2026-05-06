<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // First try to get from session
        $permissions = $request->session()->get('permissions', []);
        $isGlobalAdmin = $request->session()->get('is_global_admin', false);
        
        // If session is empty, try to get from custom headers (sent from frontend localStorage)
        if (empty($permissions)) {
            $permissionsHeader = $request->header('X-Permissions');
            if ($permissionsHeader) {
                $permissions = json_decode($permissionsHeader, true) ?: [];
            }
        }
        
        if (!$isGlobalAdmin) {
            $isGlobalAdminHeader = $request->header('X-Is-Global-Admin');
            if ($isGlobalAdminHeader === 'true') {
                $isGlobalAdmin = true;
            }
        }
        
        // DEBUG: Log permission check
        file_put_contents(storage_path('logs/debug.log'), 
            "[" . date('Y-m-d H:i:s') . "] CHECK PERMISSION: url={$request->path()}, check={$permission}, permissions=" . json_encode($permissions) . ", is_global_admin=" . ($isGlobalAdmin ? 'true' : 'false') . "\n", 
            FILE_APPEND);
        
        $hasAccess = $isGlobalAdmin || in_array('*', $permissions) || in_array($permission, $permissions);
        
        if ($hasAccess) {
            return $next($request);
        }
        
        return response()->json([
            'message' => 'No tienes permiso para acceder a esta ruta',
            'debug' => [
                'check' => $permission,
                'permissions' => $permissions,
                'is_global_admin' => $isGlobalAdmin
            ]
        ], 403);
    }
}