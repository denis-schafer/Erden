<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SpaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyModuleController;

// MP OAuth Callback - Debe estar al inicio para funcionar con ngrok/HTTPS
Route::get('/mp/callback', [\App\Http\Controllers\Pos\MercadoPagoController::class, 'callback']);
Route::post('/mp/exchange-code', [\App\Http\Controllers\Pos\MercadoPagoController::class, 'exchangeCode']);
Route::post('/mp/webhook', [\App\Http\Controllers\Pos\MercadoPagoController::class, 'webhook'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// POS Order Display API
Route::middleware(['web', 'setDatabase'])->group(function () {
    Route::get('/pos/users', [\App\Http\Controllers\Pos\PosUserController::class, 'index']);
    Route::get('/pos/order-display/{username}/{orderId?}', [\App\Http\Controllers\Pos\PosOrderDisplayController::class, 'show']);
    Route::post('/pos/generate-mp-qr', [\App\Http\Controllers\Pos\MercadoPagoController::class, 'generateQR']);
    Route::post('/pos/request-qr-order', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'order_id' => 'required|integer',
            'username' => 'required|string',
            'total' => 'required',
            'target_user_id' => 'required|integer',
        ]);
        
        event(new \App\Events\PosQRUpdated(
            $request->input('target_user_id'),
            $request->input('order_id'),
            $request->input('username'),
            $request->input('total')
        ));
        
        $controller = app(\App\Http\Controllers\Pos\PosOrderController::class);
        $controller->sendOrderToPoint($request->input('order_id'));
        
        return response()->json(['success' => true]);
    });
});

// Order Display - Vista pública para mostrar pedidos (requiere autenticación)
Route::get('/pedido/{username}/{orderId?}', [OrderDisplayController::class, 'index'])
    ->middleware('auth')
    ->name('order.display');

Route::get('/', [SpaController::class, 'index']);
Route::get('/login', [SpaController::class, 'index'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/check-user', [AuthController::class, 'checkUser']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/select-company', [AuthController::class, 'selectCompany']);
Route::get('/debug-session', function () {
    return response()->json([
        'permissions' => session('permissions', []),
        'is_global_admin' => session('is_global_admin', false),
        'company_db' => session('company_db'),
        'user' => session('user'),
    ]);
});

Route::middleware(['web', 'setDatabase'])->group(function () {
    Route::get('/session', [AuthController::class, 'getSession']);
    Route::put('/profile', [UserController::class, 'profile']);
    
    Route::middleware(['permission:admin-modules'])->group(function () {
        Route::resource('admin/modules', ModuleController::class)->parameters(['modules' => 'id']);
        Route::get('admin/companies', [CompanyModuleController::class, 'index']);
        Route::get('admin/companies/{id}/modules', [CompanyModuleController::class, 'show']);
        Route::put('admin/companies/{id}/modules', [CompanyModuleController::class, 'update']);
    });
    
    Route::middleware(['permission:users_read'])->group(function () {
        Route::get('/users', [UserController::class, 'index']);
    });
    Route::middleware(['permission:users_create'])->group(function () {
        Route::post('/users', [UserController::class, 'store']);
    });
    Route::middleware(['permission:users_update'])->group(function () {
        Route::put('/users/{id}', [UserController::class, 'update']);
    });
    Route::middleware(['permission:users_delete'])->group(function () {
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });
    
    Route::middleware(['permission:roles_read'])->group(function () {
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/{id}/permissions', [RoleController::class, 'getPermissions']);
        Route::get('/permissions', [RoleController::class, 'getAllPermissions']);
    });
    Route::middleware(['permission:roles_create'])->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
    });
    Route::middleware(['permission:roles_update'])->group(function () {
        Route::put('/roles/{id}', [RoleController::class, 'update']);
        Route::put('/roles/{id}/permissions', [RoleController::class, 'updatePermissions']);
    });
    Route::middleware(['permission:roles_delete'])->group(function () {
        Route::delete('/roles/{id}', [RoleController::class, 'destroy']);
    });

    Route::middleware(['permission:companies_read'])->group(function () {
        Route::get('/companies', [CompanyController::class, 'index']);
    });
    Route::middleware(['permission:companies_create'])->group(function () {
        Route::post('/companies', [CompanyController::class, 'store']);
    });
    Route::middleware(['permission:companies_update'])->group(function () {
        Route::put('/companies/{id}', [CompanyController::class, 'update']);
    });
    Route::middleware(['permission:companies_delete'])->group(function () {
        Route::delete('/companies/{id}', [CompanyController::class, 'destroy']);
    });
    Route::get('/companies/statuses', [CompanyController::class, 'getStatuses']);

    // POS Routes (accessed from child DB)
    Route::prefix('pos')->group(function () {
        Route::post('/login', [App\Http\Controllers\Pos\PosAuthController::class, 'login']);
        Route::post('/logout', [App\Http\Controllers\Pos\PosAuthController::class, 'logout']);
        Route::get('/user/current', [App\Http\Controllers\Pos\PosAuthController::class, 'currentUser']);
        
        Route::get('/users/me', [App\Http\Controllers\Pos\PosUserController::class, 'me']);
        
        Route::get('/categories', [App\Http\Controllers\Pos\PosCategoryController::class, 'index']);
        Route::post('/categories', [App\Http\Controllers\Pos\PosCategoryController::class, 'store']);
        Route::put('/categories/{id}', [App\Http\Controllers\Pos\PosCategoryController::class, 'update']);
        Route::delete('/categories/{id}', [App\Http\Controllers\Pos\PosCategoryController::class, 'destroy']);
        Route::post('/categories/{id}/toggle-status', [App\Http\Controllers\Pos\PosCategoryController::class, 'toggleStatus']);
        
        Route::get('/products', [App\Http\Controllers\Pos\PosProductController::class, 'index']);
        Route::get('/category/{id}/products', [App\Http\Controllers\Pos\PosProductController::class, 'byCategory']);
        Route::post('/products', [App\Http\Controllers\Pos\PosProductController::class, 'store']);
        Route::put('/products/{id}', [App\Http\Controllers\Pos\PosProductController::class, 'update']);
        Route::put('/products/{id}/status', [App\Http\Controllers\Pos\PosProductController::class, 'updateStatus']);
        Route::post('/products/{id}/toggle-status', [App\Http\Controllers\Pos\PosProductController::class, 'toggleStatus']);
        Route::post('/products/reorder', [App\Http\Controllers\Pos\PosProductController::class, 'reorder']);
        Route::delete('/products/{id}', [App\Http\Controllers\Pos\PosProductController::class, 'destroy']);
        
        Route::get('/orders', [App\Http\Controllers\Pos\PosOrderController::class, 'index']);
        Route::post('/orders', [App\Http\Controllers\Pos\PosOrderController::class, 'store']);
        Route::get('/orders/{id}', [App\Http\Controllers\Pos\PosOrderController::class, 'show']);
        Route::post('/orders/{id}/cancel', [App\Http\Controllers\Pos\PosOrderController::class, 'cancel']);
        Route::delete('/orders/{id}', [App\Http\Controllers\Pos\PosOrderController::class, 'destroy']);
        Route::post('/orders/{id}/toggle-paid', [App\Http\Controllers\Pos\PosOrderController::class, 'togglePaid']);
        Route::post('/orders/{id}/reprint', [App\Http\Controllers\Pos\PosOrderController::class, 'reprint']);
        Route::get('/orders/{id}/payment-status', [App\Http\Controllers\Pos\PosOrderController::class, 'checkPaymentStatus']);
        Route::post('/orders/check-pending-payments', [App\Http\Controllers\Pos\PosOrderController::class, 'checkPendingPayments']);
        
        Route::get('/users', [App\Http\Controllers\Pos\PosUserController::class, 'index']);
        Route::post('/users', [App\Http\Controllers\Pos\PosUserController::class, 'store']);
        Route::put('/users/{id}', [App\Http\Controllers\Pos\PosUserController::class, 'update']);
        Route::post('/users/{id}/toggle-status', [App\Http\Controllers\Pos\PosUserController::class, 'toggleStatus']);
        Route::delete('/users/{id}', [App\Http\Controllers\Pos\PosUserController::class, 'destroy']);
        Route::put('/users/{id}/printer-config', [App\Http\Controllers\Pos\PosUserController::class, 'updatePrinterConfig']);

        Route::get('/terminals', [App\Http\Controllers\Pos\PosUserController::class, 'listTerminals']);
        Route::post('/terminals/{id}/set-mode', [App\Http\Controllers\Pos\PosUserController::class, 'setTerminalMode']);
        
        Route::get('/configs', [App\Http\Controllers\Pos\PosConfigController::class, 'index']);
        Route::put('/configs/{id}', [App\Http\Controllers\Pos\PosConfigController::class, 'update']);

        // Test mode
        Route::get('/test-mode/status', [App\Http\Controllers\Pos\PosTestModeController::class, 'status']);
        Route::post('/test-mode/enable', [App\Http\Controllers\Pos\PosTestModeController::class, 'enable']);
        Route::post('/test-mode/disable', [App\Http\Controllers\Pos\PosTestModeController::class, 'disable']);
        
        Route::get('/roles', [App\Http\Controllers\Pos\PosRoleController::class, 'index']);
        
        Route::get('/dashboard/stats', [App\Http\Controllers\Pos\PosDashboardController::class, 'stats']);
        Route::get('/dashboard/by-status', [App\Http\Controllers\Pos\PosDashboardController::class, 'byStatus']);
        Route::get('/dashboard/top-products', [App\Http\Controllers\Pos\PosDashboardController::class, 'topProducts']);
        Route::get('/dashboard/sales-trend', [App\Http\Controllers\Pos\PosDashboardController::class, 'salesTrend']);
        Route::get('/dashboard/cashiers', [App\Http\Controllers\Pos\PosDashboardController::class, 'cashiers']);

        // Statistics routes
        Route::middleware(['permission:pos-statistics_read'])->group(function () {
            Route::get('/statistics/summary', [App\Http\Controllers\Pos\PosStatisticsController::class, 'summary']);
            Route::get('/statistics/sales-by-period', [App\Http\Controllers\Pos\PosStatisticsController::class, 'salesByPeriod']);
            Route::get('/statistics/top-products', [App\Http\Controllers\Pos\PosStatisticsController::class, 'topProducts']);
            Route::get('/statistics/products-by-interval', [App\Http\Controllers\Pos\PosStatisticsController::class, 'productsByInterval']);
            Route::get('/statistics/export', [App\Http\Controllers\Pos\PosStatisticsController::class, 'export'])->middleware('permission:pos-statistics_export');
        });

        // Log routes (solo admin)
        Route::middleware(['permission:pos-log_read'])->group(function () {
            Route::get('/log', [App\Http\Controllers\Pos\PosLogController::class, 'index']);
            Route::get('/log/entries', [App\Http\Controllers\Pos\PosLogController::class, 'getLogs']);
    });

    // Print Agent API (autenticado por API key, no por sesión)
    Route::prefix('print-jobs')->middleware('printAgentAuth')->group(function () {
        Route::get('/pending', [App\Http\Controllers\Pos\PrintJobController::class, 'pending']);
        Route::post('/{id}/ack', [App\Http\Controllers\Pos\PrintJobController::class, 'ack']);
    });

    // Webhook Agent API (autenticado por API key, no por sesión)
    Route::prefix('webhooks-jobs')->middleware('printAgentAuth')->group(function () {
        Route::get('/pending', [App\Http\Controllers\Pos\WebhookJobController::class, 'pending']);
        Route::post('/{id}/ack', [App\Http\Controllers\Pos\WebhookJobController::class, 'ack']);
    });

    // Print Agent info (autenticado por sesión normal)
    Route::prefix('print-agent')->group(function () {
        Route::get('/info', [App\Http\Controllers\Pos\PosConfigController::class, 'printAgentInfo']);
        Route::post('/regenerate', [App\Http\Controllers\Pos\PosConfigController::class, 'regeneratePrintAgentKey']);
        Route::get('/download', [App\Http\Controllers\Pos\PosConfigController::class, 'downloadAgent']);
    });

    // Sync Settings (autenticado por sesión normal)
    Route::prefix('sync-settings')->group(function () {
        Route::get('/', [App\Http\Controllers\Pos\PosConfigController::class, 'syncSettings']);
        Route::put('/', [App\Http\Controllers\Pos\PosConfigController::class, 'updateSyncSettings']);
    });

    // Sync Backfill (autenticado por sesión normal)
    Route::prefix('sync')->group(function () {
        Route::post('/backfill', [App\Http\Controllers\Pos\SyncController::class, 'backfill']);
        Route::get('/backfill-status', [App\Http\Controllers\Pos\SyncController::class, 'backfillStatus']);
    });

    // Webhook Code (autenticado por sesión normal)
    Route::prefix('webhook-code')->group(function () {
        Route::get('/', [App\Http\Controllers\Pos\PosConfigController::class, 'webhookCode']);
        Route::put('/', [App\Http\Controllers\Pos\PosConfigController::class, 'updateWebhookCode']);
    });
});

// Sync Push API (autenticado por API key, fuera del grupo SPA para evitar CSRF)
Route::prefix('pos')->middleware('printAgentAuth')->group(function () {
    Route::post('/sync/push', [App\Http\Controllers\Pos\SyncController::class, 'push']);
});

// MP OAuth Callback - Standalone (sin middleware, sin autenticación)
    // Esta ruta debe estar fuera del grupo SPA para ngrok (HTTP)
    Route::get('/mp/callback', [\App\Http\Controllers\Pos\MercadoPagoController::class, 'callback']);
    Route::post('/mp/exchange-code', [\App\Http\Controllers\Pos\MercadoPagoController::class, 'exchangeCode']);
});

// Global Config Routes
Route::get('/configs', [App\Http\Controllers\ConfigController::class, 'index']);
Route::put('/configs/{id}', [App\Http\Controllers\ConfigController::class, 'update']);
Route::get('/configs/target/{target}', [App\Http\Controllers\ConfigController::class, 'getByTarget']);

Route::post('/refresh-session', function () {
    session()->put('last_activity', now());
    return response()->json(['success' => true, 'expires_at' => now()->addMinutes(config('session.lifetime'))]);
})->middleware('auth');

// ==================== Quota Admin Routes ====================
Route::middleware(['web', 'setDatabase'])->prefix('quota')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Quota\QuotaDashboardController::class, 'index'])->middleware('permission:quota-dashboard_read');

    Route::get('/partners', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'index'])->middleware('permission:quota-partners_read');
    Route::post('/partners', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'store'])->middleware('permission:quota-partners_create');
    Route::post('/partners/generate-all', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'generateAll'])->middleware('permission:quota-plans_generate');
    Route::put('/partners/{id}', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'update'])->middleware('permission:quota-partners_update');
    Route::delete('/partners/{id}', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'destroy'])->middleware('permission:quota-partners_delete');
    Route::post('/partners/{id}/reset-password', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'resetPassword'])->middleware('permission:quota-partners_reset_password');
    Route::post('/partners/import', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'import'])->middleware('permission:quota-partners_create');
    Route::post('/partners/{id}/assign-quotas', [\App\Http\Controllers\Quota\QuotaPartnerController::class, 'assignQuotas'])->middleware('permission:quota-items_pay');

    Route::get('/plans', [\App\Http\Controllers\Quota\QuotaPlanController::class, 'index'])->middleware('permission:quota-plans_read');
    Route::post('/plans', [\App\Http\Controllers\Quota\QuotaPlanController::class, 'store'])->middleware('permission:quota-plans_create');
    Route::put('/plans/{id}', [\App\Http\Controllers\Quota\QuotaPlanController::class, 'update'])->middleware('permission:quota-plans_update');
    Route::delete('/plans/{id}', [\App\Http\Controllers\Quota\QuotaPlanController::class, 'destroy'])->middleware('permission:quota-plans_delete');
    Route::post('/plans/{id}/generate', [\App\Http\Controllers\Quota\QuotaPlanController::class, 'generate'])->middleware('permission:quota-plans_generate');
    Route::get('/plans/generate-status', [\App\Http\Controllers\Quota\QuotaPlanController::class, 'generateStatus']);

    Route::get('/items', [\App\Http\Controllers\Quota\QuotaItemController::class, 'index'])->middleware('permission:quota-items_read');
    Route::post('/items/pay', [\App\Http\Controllers\Quota\QuotaItemController::class, 'pay'])->middleware('permission:quota-items_pay');
    Route::post('/items/{id}/toggle-rendered', [\App\Http\Controllers\Quota\QuotaItemController::class, 'toggleRendered'])->middleware('permission:quota-items_rendered');

    Route::get('/daily-rates', [\App\Http\Controllers\Quota\QuotaDailyRateController::class, 'index'])->middleware('permission:quota-daily_read|quota-plans_read');
    Route::post('/daily-rates', [\App\Http\Controllers\Quota\QuotaDailyRateController::class, 'store'])->middleware('permission:quota-plans_create');
    Route::put('/daily-rates/{id}', [\App\Http\Controllers\Quota\QuotaDailyRateController::class, 'update'])->middleware('permission:quota-plans_update');
    Route::delete('/daily-rates/{id}', [\App\Http\Controllers\Quota\QuotaDailyRateController::class, 'destroy'])->middleware('permission:quota-plans_delete');

    Route::get('/daily-charges', [\App\Http\Controllers\Quota\QuotaDailyChargeController::class, 'index'])->middleware('permission:quota-daily_read');
    Route::post('/daily-charges', [\App\Http\Controllers\Quota\QuotaDailyChargeController::class, 'store'])->middleware('permission:quota-daily_create');
    Route::get('/daily-summary', [\App\Http\Controllers\Quota\QuotaDailySummaryController::class, 'index'])->middleware('permission:quota-daily_read');
    Route::post('/daily-charges/{id}/render', [\App\Http\Controllers\Quota\QuotaDailyChargeController::class, 'render'])->middleware('permission:quota-daily_create');
    Route::post('/daily-charges/{id}/unrender', [\App\Http\Controllers\Quota\QuotaDailyChargeController::class, 'unrender'])->middleware('permission:quota-daily_create');

    Route::get('/payments', [\App\Http\Controllers\Quota\QuotaPaymentController::class, 'index'])->middleware('permission:quota-payments_read');
    Route::post('/payments/{id}/render', [\App\Http\Controllers\Quota\QuotaPaymentController::class, 'render'])->middleware('permission:quota-payments_rendered');
    Route::get('/payments/cashier-balance', [\App\Http\Controllers\Quota\QuotaPaymentController::class, 'cashierBalance'])->middleware('permission:quota-payments_read');

    Route::get('/config', [\App\Http\Controllers\Quota\QuotaConfigController::class, 'index'])->middleware('permission:quota-config_read');
    Route::put('/config/{id}', [\App\Http\Controllers\Quota\QuotaConfigController::class, 'update'])->middleware('permission:quota-config_update');
    Route::get('/config/mp-oauth-url', [\App\Http\Controllers\Quota\QuotaConfigController::class, 'getMpOAuthUrl'])->middleware('permission:quota-config_update');
    Route::get('/config/cashiers', [\App\Http\Controllers\Quota\QuotaConfigController::class, 'cashiers'])->middleware('permission:quota-config_read');
    Route::post('/config/upload', [\App\Http\Controllers\Quota\QuotaConfigController::class, 'upload'])->middleware('permission:quota-config_update');
    Route::get('/config/mp-client-id', [\App\Http\Controllers\Quota\QuotaConfigController::class, 'getMpClientId']);

    Route::middleware(['permission:quota-users_read'])->group(function () {
        Route::get('/users', [\App\Http\Controllers\Quota\QuotaUserController::class, 'index']);
        Route::get('/users/roles', [\App\Http\Controllers\Quota\QuotaUserController::class, 'roles']);
    });
    Route::post('/users', [\App\Http\Controllers\Quota\QuotaUserController::class, 'store'])->middleware('permission:quota-users_create');
    Route::put('/users/{id}', [\App\Http\Controllers\Quota\QuotaUserController::class, 'update'])->middleware('permission:quota-users_update');
    Route::delete('/users/{id}', [\App\Http\Controllers\Quota\QuotaUserController::class, 'destroy'])->middleware('permission:quota-users_delete');

    Route::get('/statistics/summary', [\App\Http\Controllers\Quota\QuotaStatisticsController::class, 'summary'])->middleware('permission:quota-statistics_read');
    Route::get('/statistics/export', [\App\Http\Controllers\Quota\QuotaStatisticsController::class, 'export'])->middleware('permission:quota-statistics_export');
    Route::get('/statistics/cashier-balance', [\App\Http\Controllers\Quota\QuotaStatisticsController::class, 'cashierBalance'])->middleware('permission:quota-statistics_read');

    Route::get('/companies', function () {
        $companyDb = session('company_db');
        $companies = DB::connection('mysql_parent')
            ->table('companies')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('company_modules')
                    ->join('modules', 'company_modules.module_id', '=', 'modules.id')
                    ->whereColumn('company_modules.company_id', 'companies.id')
                    ->where('modules.package', 'quota_admin');
            })
            ->get(['id', 'db', 'name']);
        return response()->json($companies);
    });
});

// MP OAuth & Webhook for Quota (standalone, outside SPA group)
Route::get('/quota/mp/callback', [\App\Http\Controllers\Quota\QuotaMercadoPagoController::class, 'callback']);

// Partner Portal public lookup
Route::get('/asociados/lookup-company', [\App\Http\Controllers\Quota\QuotaAuthController::class, 'lookupCompany']);

// Portal branding config (public)
Route::get('/portal/config', [\App\Http\Controllers\Quota\QuotaConfigController::class, 'portalConfig']);

// Partner Portal API Routes (token-based auth, within setDatabase for company context)
Route::middleware(['web', 'setDatabase'])->prefix('asociados')->group(function () {
    Route::post('/login', [\App\Http\Controllers\Quota\QuotaAuthController::class, 'login']);
    Route::get('/user/current', [\App\Http\Controllers\Quota\QuotaAuthController::class, 'currentUser']);
    Route::post('/change-password', [\App\Http\Controllers\Quota\QuotaAuthController::class, 'changePassword']);
    Route::put('/profile', [\App\Http\Controllers\Quota\QuotaAuthController::class, 'updateProfile']);
    Route::post('/logout', [\App\Http\Controllers\Quota\QuotaAuthController::class, 'logout']);
    Route::get('/quotas', [\App\Http\Controllers\Quota\QuotaItemController::class, 'myQuotas']);
    Route::post('/mp/create-preference', [\App\Http\Controllers\Quota\QuotaMercadoPagoController::class, 'createPreference']);
});

// OAuth public routes
Route::get('/oauth/lookup', [\App\Http\Controllers\OAuthController::class, 'lookup']);
Route::get('/oauth/authorize', [\App\Http\Controllers\OAuthController::class, 'authorizeUrl']);
Route::post('/oauth/assign', [\App\Http\Controllers\OAuthController::class, 'assign']);

// Portal SPA catch-all (must be after lookup-company)
Route::get('/asociados/{name?}/{dni?}', [SpaController::class, 'index']);

// OAuth SPA catch-all
Route::get('/oauth', [SpaController::class, 'index']);