<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Status;
use App\Models\Module;
use App\Models\Company;
use App\Models\GlobalUser;
use App\Models\GlobalRole;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('spa');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
            'company_db' => 'nullable|string'
        ]);

        $companyDb = $request->input('company_db');

        // First check if user is a global user in erden
        $globalUser = GlobalUser::where('username', $credentials['username'])->first();
        
        if ($globalUser && password_verify($credentials['password'], $globalUser->password)) {
            // This is a global user - they must use company selector, not direct company_db login
            $role = GlobalRole::find($globalUser->role_id);
            
            // All global users (admin-global, soporte, etc.) get company selector
            $companies = Company::where('status_id', 1)->get();
            $request->session()->put('global_user_id', $globalUser->id);
            
            $companiesArray = $companies->toArray();
            $companiesArray[] = [
                'id' => 'global',
                'db' => 'erden',
                'name' => 'Administración Global',
                'is_global' => true
            ];
            
            return response()->json([
                'needs_company_selection' => true,
                'is_global_user' => true,
                'companies' => $companiesArray
            ]);
        }

        // Not a global user - check if it's a local user in the specified company DB
        if ($companyDb) {
            // Match by db OR by name (case-insensitive)
            $company = Company::where('db', $companyDb)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($companyDb)])
                ->first();
            
            if (!$company) {
                return response()->json(['message' => 'Empresa no encontrada'], 401);
            }
            
            // Check company status
            if ($company->status_id != 1) {
                return $this->getCompanyStatusMessage($company->status_id);
            }
            
            $this->switchToCompany($company->db);
                
            $user = DB::table('users')->where('username', $credentials['username'])->first();
            
            if ($user && password_verify($credentials['password'], $user->password)) {
                if (isset($user->enable) && !$user->enable) {
                    return response()->json(['message' => 'Usuario deshabilitado'], 401);
                }
                
                $role = DB::table('roles')->find($user->role_id);
                $permissions = $this->getPermissionsFromRole($role);
                
                // Check mercadopago QR enabled
                $mercadopagoEnableQr = in_array($user->mercadopago_qr_enabled ?? false, [1, true, '1', 'true'], true);
                
                $token = $this->generateToken($user, $company);
                
                // Get modules from child DB before switching back
                $companyDbForModules = $company->db;
                config(['database.connections.mysql.database' => $companyDbForModules]);
                DB::purge('mysql');
                DB::reconnect('mysql');
                
                $modules = $this->getLocalModules();
                
                // Switch back to parent DB
                config(['database.connections.mysql.database' => 'erden']);
                DB::purge('mysql');
                DB::reconnect('mysql');
                
                $request->session()->put('user', [
                    'id' => $user->id, 
                    'username' => $user->username, 
                    'name' => $user->name, 
                    'role_id' => $user->role_id,
                    'mercadopago_qr_enabled' => $mercadopagoEnableQr
                ]);
                $request->session()->put('company', ['id' => $company->id, 'name' => $company->name, 'db' => $company->db]);
                $request->session()->put('company_db', $company->db);
                $request->session()->put('permissions', $permissions);
                $request->session()->put('modules', $modules);
                $request->session()->put('is_global_admin', false);
                $request->session()->put('is_parent_db', false);
                $request->session()->put('token', $token);
                $request->session()->save();
                
                return response()->json([
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'role_id' => $user->role_id,
                        'mercadopago_qr_enabled' => $mercadopagoEnableQr
                    ],
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'db' => $company->db
                    ],
                    'is_global_admin' => false,
                    'is_parent_db' => false,
                    'permissions' => $permissions,
                    'modules' => $modules
                ]);
            }
            
            return response()->json(['message' => 'Credenciales incorrectas o empresa no válida'], 401);
        }

        return response()->json(['message' => 'Credenciales incorrectas'], 401);
    }

    public function checkUser(Request $request)
    {
        $username = $request->query('username');
        
        if (!$username) {
            return response()->json(['is_global' => false]);
        }
        
        // Check if user exists in global_users
        $globalUser = GlobalUser::where('username', $username)->first();
        
        if ($globalUser) {
            $role = GlobalRole::find($globalUser->role_id);
            $isGlobalAdmin = $role && $role->slug === 'admin-global';
            
            return response()->json([
                'is_global' => true,
                'is_global_admin' => $isGlobalAdmin
            ]);
        }
        
        return response()->json(['is_global' => false]);
    }

    public function showCompanySelector()
    {
        $globalUserId = session('global_user_id');
        if (!$globalUserId) {
            return redirect('/login');
        }
        
        $companies = Company::where('status_id', 1)->get();
        return view('spa', ['companies' => $companies]);
    }

    public function selectCompany(Request $request)
    {
        $globalUserId = session('global_user_id');
        if (!$globalUserId) {
            return response()->json(['message' => 'Sesión expirada'], 401);
        }

        $globalUser = GlobalUser::find($globalUserId);
        $role = GlobalRole::find($globalUser->role_id);
        $isGlobalAdmin = $role && $role->slug === 'admin-global';

        if ($request->company_id === 'global') {
            config(['database.connections.mysql.database' => 'erden']);
            DB::purge('mysql');
            DB::reconnect('mysql');
            
            $token = $this->generateToken($globalUser, (object)['id' => 'global', 'name' => 'Administración Global', 'db' => 'erden']);
            $modules = $this->getAllModules();
            $permissions = $this->getPermissions($globalUser);

            $request->session()->put('user', ['id' => $globalUser->id, 'username' => $globalUser->username, 'name' => $globalUser->name ?? '']);
            $request->session()->put('company', ['id' => 'global', 'name' => 'Administración Global', 'db' => 'erden']);
            $request->session()->put('company_db', 'erden');
            $request->session()->put('permissions', $permissions);
            $request->session()->put('modules', $modules);
            $request->session()->put('is_global_admin', $isGlobalAdmin);
            $request->session()->put('is_parent_db', true);
            $request->session()->put('token', $token);
            $request->session()->save();

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $globalUser->id,
                    'username' => $globalUser->username,
                    'name' => $globalUser->name ?? ''
                ],
                'company' => [
                    'id' => 'global',
                    'name' => 'Administración Global',
                    'db' => 'erden'
                ],
                'is_global_admin' => $isGlobalAdmin,
                'is_parent_db' => true,
                'permissions' => $permissions,
                'modules' => $modules
            ]);
        }

        $company = Company::find($request->company_id);
        if (!$company) {
            return response()->json(['message' => 'Empresa no encontrada'], 404);
        }

        $globalUser = GlobalUser::find($globalUserId);
        
        $this->switchToCompany($company->db);
        
        // Ensure POS permissions are seeded
        $this->ensurePosPermissions();
        
        $token = $this->generateToken($globalUser, $company);
        $modules = $this->getModules($company->id);
        
        // Get local POS permissions from child DB
        $localPermissions = $this->getLocalPermissions($globalUser->username);
        $permissions = array_merge($this->getPermissions($globalUser), $localPermissions);
        
        // Check if user has mercadopago QR enabled
        $localUser = DB::table('users')->where('username', $globalUser->username)->first();
        \Log::info("mercadopago check: username={$globalUser->username}, currentDB=" . config('database.connections.mysql.database') . ", localUser found=" . ($localUser ? "yes" : "no"));
        
        $mercadopagoEnableQr = false;
        if ($localUser) {
            $qrEnabled = $localUser->mercadopago_qr_enabled;
            \Log::info("mercadopago_qr_enabled raw: " . json_encode($qrEnabled) . " type: " . gettype($qrEnabled));
            $mercadopagoEnableQr = in_array($qrEnabled, [1, true, '1', 'true'], true);
        }
        \Log::info("mercadopago_qr_enabled final: " . ($mercadopagoEnableQr ? "true" : "false"));
        
        // Switch back to parent DB before saving session
        $this->switchToParent();
        
$request->session()->put('user', [
            'id' => $globalUser->id, 
            'username' => $globalUser->username, 
            'name' => $globalUser->name ?? '',
            'mercadopago_qr_enabled' => $mercadopagoEnableQr
        ]);
        $request->session()->put('company', ['id' => $company->id, 'name' => $company->name, 'db' => $company->db]);
        $request->session()->put('company_db', $company->db);
        $request->session()->put('permissions', $permissions);
        $request->session()->put('modules', $modules);
        $request->session()->put('is_global_admin', true);
        $request->session()->put('is_parent_db', false);
        $request->session()->save();
        $request->session()->put('token', $token);

        return response()->json([
            'token' => $token,
            'debug_qr' => [
                'mercadopagoEnableQr' => $mercadopagoEnableQr,
                'localUserFound' => !!$localUser,
                'currentDB' => config('database.connections.mysql.database'),
            ],
            'user' => [
                'id' => $globalUser->id,
                'username' => $globalUser->username,
                'name' => $globalUser->name ?? '',
                'mercadopago_qr_enabled' => $mercadopagoEnableQr
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'db' => $company->db
            ],
            'is_global_admin' => true,
            'is_parent_db' => false,
            'permissions' => $permissions,
            'modules' => $modules
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');
        
        return response()->json(['message' => 'Logged out']);
    }

    public function getSession(Request $request)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['authenticated' => false]);
        }

        $user = $request->session()->get('user');
        $company = $request->session()->get('company');
        
        if (!$user) {
            return response()->json(['authenticated' => false]);
        }

        return response()->json([
            'authenticated' => true,
            'user' => $user,
            'company' => $company,
            'permissions' => $request->session()->get('permissions', []),
            'modules' => $request->session()->get('modules', []),
            'is_global_admin' => $request->session()->get('is_global_admin', false),
            'is_parent_db' => $request->session()->get('is_parent_db', false)
        ]);
    }

    private function switchToCompany($dbName)
    {
        config(['database.connections.mysql.database' => $dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function switchToParent()
    {
        config(['database.connections.mysql.database' => 'erden']);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function generateToken($user, $company)
    {
        return base64_encode($user->id . ':' . $company->id . ':' . time());
    }

private function getPermissions($globalUser)
    {
        if (!$globalUser) return [];
        
        try {
            $permissions = DB::connection('mysql_parent')
                ->table('role_permission')
                ->where('role_id', $globalUser->role_id)
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->select('permissions.slug')
                ->get();

            $permissionsArray = $permissions->pluck('slug')->toArray();
            
            return empty($permissionsArray) ? [] : $permissionsArray;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    private function getLocalPermissions($username)
    {
        if (!$username) return [];
        
        try {
            $user = DB::table('users')->where('username', $username)->first();
            \Log::info("getLocalPermissions: user=$username, userFound=" . ($user ? "yes" : "no") . ", role_id=" . ($user->role_id ?? 'null'));
            if (!$user) return [];
            
            $permissions = DB::table('role_permission')
                ->where('role_id', $user->role_id)
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->select('permissions.slug')
                ->get();

            $perms = $permissions->pluck('slug')->toArray();
            \Log::info("getLocalPermissions: permissions=" . json_encode($perms));
            return $perms;
        } catch (\Exception $e) {
            \Log::error("getLocalPermissions error: " . $e->getMessage());
            return [];
        }
    }

    private function getModules($companyId)
    {
        // If using child DB (after switchToCompany), get modules from local DB
        $currentDb = config('database.connections.mysql.database');
        
        if ($currentDb !== 'erden') {
            return $this->getLocalModules();
        }
        
        // Otherwise use company_modules from parent DB
        $companyModules = DB::connection('mysql_parent')
            ->table('company_modules')
            ->where('company_id', $companyId)
            ->join('modules', 'company_modules.module_id', '=', 'modules.id')
            ->select('modules.*', 'company_modules.order as module_order')
            ->orderBy('company_modules.order')
            ->get();

        return $companyModules->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'route' => $item->route,
                'icon' => $item->icon,
                'is_special' => $item->is_special,
                'parent_id' => $item->parent_id,
                'order' => $item->module_order
            ];
        })->toArray();
    }
    
    private function getLocalModules()
    {
        // Check if modules table exists in child DB
        try {
            if (!Schema::hasTable('modules')) {
                file_put_contents(storage_path('logs/debug.log'), 
                    "[" . date('Y-m-d H:i:s') . "] getLocalModules: modules table doesn't exist\n", 
                    FILE_APPEND);
                return [];
            }
            
            $hasPackageColumn = Schema::hasColumn('modules', 'package');
            $modules = DB::table('modules')->orderBy('order')->get();
            
            file_put_contents(storage_path('logs/debug.log'), 
                "[" . date('Y-m-d H:i:s') . "] getLocalModules: found " . count($modules) . " modules\n", 
                FILE_APPEND);
            
            return $modules->map(function($item) use ($hasPackageColumn) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'route' => $item->route,
                    'icon' => $item->icon,
                    'is_special' => $item->is_special,
                    'parent_id' => $item->parent_id ?? null,
                    'order' => $item->order,
                    'package' => $hasPackageColumn ? ($item->package ?? 'pos') : 'pos'
                ];
            })->toArray();
        } catch (\Exception $e) {
            file_put_contents(storage_path('logs/debug.log'), 
                "[" . date('Y-m-d H:i:s') . "] getLocalModules error: " . $e->getMessage() . "\n", 
                FILE_APPEND);
            return [];
        }
    }

    private function getAllModules()
    {
        // Exclude POS modules for global admin - POS is only for child DBs
        $modules = Module::whereNotIn('route', ['pos', 'pos-admin', 'pos-caja', 'pos-products', 'pos-orders', 'pos-users', 'pos-config'])
            ->orderBy('order')
            ->get();
        
        return $modules->map(function($item) {
            return [
                'id' => $item->id,
                'name' => $item->name,
                'route' => $item->route,
                'icon' => $item->icon,
                'is_special' => $item->is_special,
                'parent_id' => $item->parent_id,
                'order' => $item->order
            ];
        })->toArray();
    }

    private function getPermissionsFromRole($role)
    {
        if (!$role) return [];
        
        try {
            $permissions = DB::table('role_permission')
                ->where('role_id', $role->id)
                ->join('permissions', 'role_permission.permission_id', '=', 'permissions.id')
                ->select('permissions.slug')
                ->pluck('slug')
                ->toArray();
            
            return empty($permissions) ? [] : $permissions;
        } catch (\Exception $e) {
            return [];
        }
    }
    
private function getCompanyStatusMessage($statusId)
    {
        // status_id: 1=active, 2=inactive, 3=debt, 4=suspended
        $messages = [
            2 => 'Cuenta bloqueada, consulte con soporte',
            3 => 'Regularice su situación',
            4 => 'Regularice su situación'
        ];
        
        $message = $messages[$statusId] ?? 'Empresa no disponible';
        return response()->json(['message' => $message], 401);
    }
    
    private function ensurePosPermissions()
    {
        try {
            $currentDb = config('database.connections.mysql.database');
            \Log::info("ensurePosPermissions called, DB: $currentDb");
            
            // Define POS permissions
            $posPermissions = [
                ['name' => 'Ver Caja', 'slug' => 'pos-caja_read', 'module' => 'pos-caja', 'action' => 'read'],
                ['name' => 'Ver Categorías', 'slug' => 'pos-categories_read', 'module' => 'pos-categories', 'action' => 'read'],
                ['name' => 'Ver Productos', 'slug' => 'pos-products_read', 'module' => 'pos-products', 'action' => 'read'],
                ['name' => 'Ver Órdenes', 'slug' => 'pos-orders_read', 'module' => 'pos-orders', 'action' => 'read'],
                ['name' => 'Crear Órdenes', 'slug' => 'pos-orders_create', 'module' => 'pos-orders', 'action' => 'create'],
                ['name' => 'Ver Usuarios POS', 'slug' => 'pos-users_read', 'module' => 'pos-users', 'action' => 'read'],
                ['name' => 'Ver Configuración', 'slug' => 'pos-config_read', 'module' => 'pos-config', 'action' => 'read'],
                ['name' => 'Ver QR', 'slug' => 'pos-qr_read', 'module' => 'pos-qr', 'action' => 'read'],
            ];
            
            // Insert POS permissions if they don't exist
            foreach ($posPermissions as $perm) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => $perm['slug']],
                    array_merge($perm, ['created_at' => now(), 'updated_at' => now()])
                );
            }
            \Log::info("POS permissions inserted");
            
            // Get cashier role
            $cashierRole = DB::table('roles')->where('name', 'cashier')->first();
            \Log::info("Cashier role found: " . ($cashierRole ? "yes, id={$cashierRole->id}" : "no"));
            
            if ($cashierRole) {
                // Clear and re-add cashier permissions
                DB::table('role_permission')->where('role_id', $cashierRole->id)->delete();
                
                $cashierPermissions = [
                    'menu_read',
                    'pos-caja_read',
                    'pos-orders_read',
                    'pos-orders_create',
                    'pos-qr_read',
                    'pos-categories_read',
                    'pos-products_read',
                    'pos-users_read',
                    'pos-config_read'
                ];
                
                foreach ($cashierPermissions as $slug) {
                    $permission = DB::table('permissions')->where('slug', $slug)->first();
                    if ($permission) {
                        DB::table('role_permission')->insert([
                            'role_id' => $cashierRole->id,
                            'permission_id' => $permission->id
                        ]);
                    }
                }
                \Log::info("Cashier permissions assigned");
            }
        } catch (\Exception $e) {
            \Log::error("ensurePosPermissions error: " . $e->getMessage());
        }
    }
}