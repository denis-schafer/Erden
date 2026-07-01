<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use App\Models\GlobalUser;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $companyDb = $request->header('X-Company-Db');
        $isParentDb = $request->header('X-Is-Parent-Db') === 'true';
        
        if (!$isParentDb && $companyDb && $companyDb !== 'erden') {
            Config::set('database.connections.mysql.database', $companyDb);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $users = DB::table('users')
                ->select('users.id', 'users.username', 'users.name', 'users.role_id', 'roles.name as role_name')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->whereNull('users.deleted_at')
                ->get();
        } elseif ($isParentDb) {
            $users = DB::connection('mysql_parent')->table('global_users')
                ->select('global_users.id', 'global_users.username', 'global_users.name', 'global_users.role_id', 'roles.name as role_name')
                ->leftJoin('roles', 'global_users.role_id', '=', 'roles.id')
                ->get();
        } else {
            $users = DB::table('users')
                ->select('users.id', 'users.username', 'users.name', 'users.role_id', 'roles.name as role_name')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->whereNull('users.deleted_at')
                ->get();
        }
        
        return response()->json($users);
    }

    public function store(Request $request)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        
        if ($isParentDb) {
            return $this->storeGlobalUser($request);
        } else {
            return $this->storeCompanyUser($request);
        }
    }

    private function storeGlobalUser(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|unique:global_users,username',
            'name' => 'nullable',
            'password' => 'required',
            'role_id' => 'required|exists:roles,id'
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['company_id'] = null;
        $validated['is_global'] = true;
        
        $id = DB::connection('mysql_parent')->table('global_users')->insertGetId($validated);
        
        return response()->json(['id' => $id, 'message' => 'Usuario global creado']);
    }

    private function storeCompanyUser(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users,username',
            'name' => 'nullable',
            'password' => 'required',
            'role_id' => 'required|exists:roles,id'
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['sync_id'] = Str::uuid()->toString();
        
        $id = DB::table('users')->insertGetId($validated);

        $user = DB::table('users')->find($id);
        $this->queueSync('users', 'created', $user);
        
        return response()->json(['id' => $id, 'message' => 'Usuario creado']);
    }

    public function update(Request $request, $id)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        
        if ($isParentDb) {
            return $this->updateGlobalUser($request, $id);
        } else {
            return $this->updateCompanyUser($request, $id);
        }
    }

    private function updateGlobalUser(Request $request, $id)
    {
        $validated = $request->validate([
            'username' => 'required|unique:global_users,username,' . $id,
            'name' => 'nullable',
            'password' => 'nullable',
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        DB::connection('mysql_parent')->table('global_users')->where('id', $id)->update($validated);
        
        return response()->json(['message' => 'Usuario global actualizado']);
    }

    private function updateCompanyUser(Request $request, $id)
    {
        $validated = $request->validate([
            'username' => 'required|unique:users,username,' . $id,
            'name' => 'nullable',
            'password' => 'nullable',
            'role_id' => 'required|exists:roles,id'
        ]);

        if ($validated['password']) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        DB::table('users')->where('id', $id)->update($validated);

        $user = DB::table('users')->find($id);
        $this->queueSync('users', 'updated', $user);
        
        return response()->json(['message' => 'Usuario actualizado']);
    }

    public function destroy(Request $request, $id)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        
        if ($isParentDb) {
            DB::connection('mysql_parent')->table('global_users')->where('id', $id)->delete();
            return response()->json(['message' => 'Usuario global eliminado']);
        } else {
            $now = now();
            $user = DB::table('users')->find($id);
            DB::table('users')->where('id', $id)->update(['deleted_at' => $now, 'updated_at' => $now]);
            if ($user) {
                $user->deleted_at = $now;
                $user->updated_at = $now;
                $this->queueSync('users', 'deleted', $user);
            }
            return response()->json(['message' => 'Usuario eliminado']);
        }
    }

    public function profile(Request $request)
    {
        $isParentDb = $request->session()->get('is_parent_db', false);
        $userId = $request->session()->get('user.id');
        
        if (!$userId) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
        
        $validated = $request->validate([
            'name' => 'nullable'
        ]);
        
        $password = $request->input('password');
        $passwordConfirmation = $request->input('password_confirmation');
        
        if ($isParentDb) {
            $updateData = ['name' => $validated['name'] ?? ''];
            
            if ($password) {
                if ($password !== $passwordConfirmation) {
                    return response()->json(['message' => 'Las contraseñas no coinciden'], 422);
                }
                if (strlen($password) < 6) {
                    return response()->json(['message' => 'La contraseña debe tener al menos 6 caracteres'], 422);
                }
                $updateData['password'] = Hash::make($password);
            }
            
            DB::connection('mysql_parent')->table('global_users')->where('id', $userId)->update($updateData);
            return response()->json(['message' => 'Perfil actualizado']);
        } else {
            $updateData = ['name' => $validated['name'] ?? ''];
            
            if ($password) {
                if ($password !== $passwordConfirmation) {
                    return response()->json(['message' => 'Las contraseñas no coinciden'], 422);
                }
                if (strlen($password) < 6) {
                    return response()->json(['message' => 'La contraseña debe tener al menos 6 caracteres'], 422);
                }
                $updateData['password'] = Hash::make($password);
            }
            
            DB::table('users')->where('id', $userId)->update($updateData);
            return response()->json(['message' => 'Perfil actualizado']);
        }
    }

    public function reorderModules(Request $request)
    {
        $userId = session('user.id');
        if (!$userId) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*.route' => 'required|string|max:255',
            'modules.*.order' => 'required|integer|min:0',
        ]);

        DB::table('user_module_orders')->where('user_id', $userId)->delete();

        foreach ($validated['modules'] as $module) {
            DB::table('user_module_orders')->insert([
                'user_id' => $userId,
                'module_route' => $module['route'],
                'sort_order' => $module['order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Update session with new order
        $sessionModules = $request->session()->get('modules', []);
        $orderMap = [];
        foreach ($validated['modules'] as $m) {
            $orderMap[$m['route']] = $m['order'];
        }
        usort($sessionModules, function ($a, $b) use ($orderMap) {
            return ($orderMap[$a['route'] ?? ''] ?? 999) - ($orderMap[$b['route'] ?? ''] ?? 999);
        });
        $request->session()->put('modules', $sessionModules);

        return response()->json(['success' => true]);
    }
}