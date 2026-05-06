<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        // Don't switch database during login requests or when session is being saved
        $path = $request->path();
        
        // Skip for login, logout, check-user, session, and POS order-display endpoints
        if (in_array($path, ['login', 'logout', 'check-user', 'session'])) {
            return $next($request);
        }
        
        // Also skip if it's the order-display API endpoint
        if (strpos($path, 'pos/order-display') !== false) {
            return $next($request);
        }
        
        // First try to get from session
        $companyDb = $request->session()->get('company_db');
        
        // If session is empty, try to get from custom headers (sent from frontend localStorage)
        if (!$companyDb) {
            $companyDb = $request->header('X-Company-Db');
        }
        
        if ($companyDb && $companyDb !== 'erden') {
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
        } else {
            // Default to parent DB
            Config::set('database.connections.mysql.database', 'erden');
            DB::purge('mysql');
            DB::reconnect('mysql');
        }
        
        return $next($request);
    }
}