<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderDisplayController extends Controller
{
    public function index(Request $request, $username, $orderId = null)
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect('/login');
        }
        
        if ($user->username !== $username && !$user->hasGlobalPermission('admin')) {
            abort(403, 'No autorizado');
        }
        
        config(['database.connections.mysql.database' => $user->company_db]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        
        $companyId = $user->company_db;
        
        if ($orderId && $orderId !== 'null') {
            $order = DB::table('orders as o')
                ->select('o.*', 'u.name as operator_name', 's.name as status_name')
                ->leftJoin('users as u', 'o.operator_id', '=', 'u.id')
                ->leftJoin('status_orders as s', 'o.status_id', '=', 's.id')
                ->where('o.id', $orderId)
                ->where('o.operator_id', $user->local_user_id ?? $user->id)
                ->first();
                
            if (!$order) {
                abort(404, 'Pedido no encontrado');
            }
        } else {
            $order = DB::table('orders as o')
                ->select('o.*', 'u.name as operator_name', 's.name as status_name')
                ->leftJoin('users as u', 'o.operator_id', '=', 'u.id')
                ->leftJoin('status_orders as s', 'o.status_id', '=', 's.id')
                ->where('o.operator_id', $user->local_user_id ?? $user->id)
                ->orderBy('o.created_at', 'desc')
                ->first();
                
            if (!$order) {
                return view('order-display', [
                    'hasOrder' => false,
                    'username' => $username,
                ]);
            }
        }
        
        $order->detail = json_decode($order->detail, true);
        
        $orders = DB::table('orders as o')
            ->select('o.id', 'o.total', 'o.created_at', 's.name as status_name')
            ->leftJoin('status_orders as s', 'o.status_id', '=', 's.id')
            ->where('o.operator_id', $user->local_user_id ?? $user->id)
            ->orderBy('o.created_at', 'desc')
            ->limit(20)
            ->get();
        
        return view('order-display', [
            'hasOrder' => true,
            'order' => $order,
            'orders' => $orders,
            'username' => $username,
        ]);
    }
}