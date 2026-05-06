<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $companyDb = $request->header('X-Company-Db');
        $isParentDb = $request->header('X-Is-Parent-Db') === 'true';
        
        // Force switch to company DB before query
        if (!$isParentDb && $companyDb && $companyDb !== 'erden') {
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            // Query local roles from company DB
            $roles = DB::table('roles')->get();
        } elseif ($isParentDb) {
            $roles = DB::connection('mysql_parent')->table('roles')->get();
        } else {
            $roles = DB::table('roles')->get();
        }
        
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        
        $validated = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:roles,slug'
        ]);

        $validated['is_global'] = $isParentDb;
        
        if ($isParentDb) {
            $id = DB::connection('mysql_parent')->table('roles')->insertGetId($validated);
        } else {
            $id = DB::table('roles')->insertGetId($validated);
        }
        
        return response()->json(['id' => $id, 'message' => 'Rol creado']);
    }

    public function update(Request $request, $id)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        
        $validated = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:roles,slug,' . $id
        ]);

        if ($isParentDb) {
            DB::connection('mysql_parent')->table('roles')->where('id', $id)->update($validated);
        } else {
            DB::table('roles')->where('id', $id)->update($validated);
        }
        
        return response()->json(['message' => 'Rol actualizado']);
    }

    public function destroy(Request $request, $id)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        
        if ($isParentDb) {
            DB::connection('mysql_parent')->table('roles')->where('id', $id)->delete();
        } else {
            DB::table('roles')->where('id', $id)->delete();
        }
        
        return response()->json(['message' => 'Rol eliminado']);
    }

    public function getPermissions($id)
    {
        $isParentDb = session('is_parent_db', false);
        
        if ($isParentDb) {
            $role = DB::connection('mysql_parent')->table('roles')->find($id);
            
            if (!$role) {
                return response()->json(['message' => 'Rol no encontrado'], 404);
            }
            
            $permissions = DB::connection('mysql_parent')->table('role_permission')
                ->where('role_id', $id)
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->select('permissions.id', 'permissions.name', 'permissions.slug', 'permissions.module', 'permissions.action')
                ->get();
        } else {
            $role = DB::table('roles')->find($id);
            
            if (!$role) {
                return response()->json(['message' => 'Rol no encontrado'], 404);
            }
            
            $permissions = DB::table('role_permission')
                ->where('role_id', $id)
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->select('permissions.id', 'permissions.name', 'permissions.slug', 'permissions.module', 'permissions.action')
                ->get();
        }
        
        return response()->json([
            'role' => $role,
            'permissions' => $permissions
        ]);
    }

    public function getAllPermissions()
    {
        $isParentDb = session('is_parent_db', false);
        
        if ($isParentDb) {
            $permissions = DB::connection('mysql_parent')->table('permissions')
                ->orderBy('module')
                ->orderBy('action')
                ->get();
        } else {
            $permissions = DB::table('permissions')
                ->orderBy('module')
                ->orderBy('action')
                ->get();
        }
        
        return response()->json($permissions);
    }

    public function updatePermissions(Request $request, $id)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        
        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|exists:permissions,id'
        ]);
        
        if ($isParentDb) {
            $role = DB::connection('mysql_parent')->table('roles')->find($id);
            
            if (!$role) {
                return response()->json(['message' => 'Rol no encontrado'], 404);
            }
            
            DB::connection('mysql_parent')->table('role_permission')->where('role_id', $id)->delete();
            
            foreach ($validated['permission_ids'] as $permissionId) {
                DB::connection('mysql_parent')->table('role_permission')->insert([
                    'role_id' => $id,
                    'permission_id' => $permissionId
                ]);
            }
        } else {
            $role = DB::table('roles')->find($id);
            
            if (!$role) {
                return response()->json(['message' => 'Rol no encontrado'], 404);
            }
            
            DB::table('role_permission')->where('role_id', $id)->delete();
            
            foreach ($validated['permission_ids'] as $permissionId) {
                DB::table('role_permission')->insert([
                    'role_id' => $id,
                    'permission_id' => $permissionId
                ]);
            }
        }
        
        return response()->json(['message' => 'Permisos actualizados']);
    }
}