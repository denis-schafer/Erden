<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class PosOrderDisplayController extends Controller
{
    
    public function show(Request $request, $username, $orderId = null)
    {
        $token = $request->bearerToken();
        $user = null;
        $companyDb = $request->header('X-Company-Db');
        
        // First, ensure we're in the parent DB to get company info
        Config::set('database.connections.mysql.database', 'erden');
        DB::purge('mysql');
        DB::reconnect('mysql');
        
        // Try to get company from token (format: base64_encode(userId:companyId:timestamp))
        if ($token) {
            $decoded = base64_decode($token);
            if ($decoded && strpos($decoded, ':') !== false) {
                $parts = explode(':', $decoded);
                $companyId = $parts[1] ?? null;
                
                // Get company db name from company_id
                if ($companyId) {
                    $company = DB::table('companies')->find($companyId);
                    if ($company) {
                        $companyDb = $company->db;
                    }
                }
            }
        }
        
        // If no company from token, try header
        if (!$companyDb) {
            $companyDb = $request->header('X-Company-Db');
        }
        
        // Switch to company database to query local users table
        if ($companyDb && $companyDb !== 'erden') {
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            // Query local users table in child DB
            $localUser = DB::table('users')->where('username', $username)->whereNull('deleted_at')->first();
            
            if ($localUser) {
                // Get the role info
                $role = DB::table('roles')->find($localUser->role_id);
                
                $user = (object) [
                    'id' => $localUser->id,
                    'username' => $localUser->username,
                    'name' => $localUser->name,
                    'role_id' => $localUser->role_id,
                    'role_name' => $role->name ?? 'unknown',
                    'local_user_id' => $localUser->id,
                    'company_db' => $companyDb
                ];
            }
        }
        
        if (!$user) {
            return response()->json([
                'error' => 'No autenticado',
                'debug' => [
                    'username_requested' => $username,
                    'companyDb' => $companyDb,
                    'token' => $token ? substr($token, 0, 20) . '...' : null
                ]
            ], 401);
        }
        
        $isAdmin = false;
        if ($user->role_id) {
            $role = DB::table('roles')->where('id', $user->role_id)->first();
            $isAdmin = $role && strtolower($role->name) === 'admin';
        }
        
        // operator_id is the local user id in the child DB
        $operatorId = $user->local_user_id ?? $user->id;
        
        if ($orderId) {
            $order = DB::table('orders as o')
                ->select('o.*', 'u.name as operator_name', 's.name as status_name')
                ->leftJoin('users as u', 'o.operator_id', '=', 'u.id')
                ->leftJoin('status_orders as s', 'o.status_id', '=', 's.id')
                ->where('o.id', $orderId)
                ->where('o.operator_id', $operatorId)
                ->first();
                
            if (!$order) {
                return response()->json(['error' => 'Pedido no encontrado'], 404);
            }
        } else {
            $order = DB::table('orders as o')
                ->select('o.*', 'u.name as operator_name', 's.name as status_name')
                ->leftJoin('users as u', 'o.operator_id', '=', 'u.id')
                ->leftJoin('status_orders as s', 'o.status_id', '=', 's.id')
                ->where('o.operator_id', $operatorId)
                ->orderBy('o.created_at', 'desc')
                ->first();
                
            if (!$order) {
                return response()->json(['error' => 'No hay pedidos para este usuario'], 404);
            }
        }
        
        $order->detail = json_decode($order->detail, true);
        
        $orders = DB::table('orders as o')
            ->select('o.id', 'o.total', 'o.created_at', 's.name as status_name')
            ->leftJoin('status_orders as s', 'o.status_id', '=', 's.id')
            ->where('o.operator_id', $operatorId)
            ->orderBy('o.created_at', 'desc')
            ->limit(20)
            ->get();
        
        return response()->json([
            'order' => $order,
            'orders' => $orders,
            'username' => $username,
        ]);
    }
    
    public function latest(Request $request, $username)
    {
        return $this->show($request, $username, null);
    }
}