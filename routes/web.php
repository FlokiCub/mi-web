<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicTrackingController;
use App\Http\Controllers\UserDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - BLANKISOL SCGI Torre de Control
|--------------------------------------------------------------------------
*/

// Public tracking portal
Route::get('/', [PublicTrackingController::class, 'index'])->name('home');
Route::get('/tracking', [PublicTrackingController::class, 'index'])->name('tracking.index');
Route::post('/tracking', [PublicTrackingController::class, 'search'])->name('tracking.search');
Route::get('/tracking/{pin}', [PublicTrackingController::class, 'show'])->name('tracking.show');
Route::get('/api/tracking/{pin}', [PublicTrackingController::class, 'apiLookup'])->name('api.tracking');

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// Standard User / Operator Area
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');
    
    // Área de Despacho Cuba - Escáner de Recepción y Cotejo de Manifiesto (Protegido por RBAC)
    Route::middleware(['role:director,despacho_puerto,aduana'])->prefix('dispatch')->name('dispatch.')->group(function () {
        Route::get('/scanner', [\App\Http\Controllers\DispatchScannerController::class, 'index'])->name('scanner');
        Route::post('/scanner', [\App\Http\Controllers\DispatchScannerController::class, 'processScan'])->name('scan.process');
        Route::post('/lookup', [\App\Http\Controllers\DispatchScannerController::class, 'lookupManifest'])->name('lookup');
        Route::post('/discrepancy', [\App\Http\Controllers\DispatchScannerController::class, 'reportDiscrepancy'])->name('discrepancy');
    });

    // Módulo de Cajas Regionales y Recaudación Multimoneda USD/CUP (Protegido por RBAC)
    Route::middleware(['role:director,cajero_regional'])->prefix('cashier')->name('cashier.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CashierController::class, 'index'])->name('index');
        Route::post('/shift/open', [\App\Http\Controllers\CashierController::class, 'openShift'])->name('shift.open');
        Route::post('/shift/{shift}/close', [\App\Http\Controllers\CashierController::class, 'closeShift'])->name('shift.close');
        Route::get('/lookup', [\App\Http\Controllers\CashierController::class, 'lookupEquipment'])->name('lookup');
        Route::post('/payment', [\App\Http\Controllers\CashierController::class, 'storePayment'])->name('payment.store');
        Route::get('/receipt/{payment}', [\App\Http\Controllers\CashierController::class, 'showReceipt'])->name('receipt');
        Route::post('/equipment/{equipment}/deliver', [\App\Http\Controllers\CashierController::class, 'deliver'])->name('deliver');
    });

    // Módulo de Aduana - Semáforos 48h/72h e Inspección (Protegido por RBAC)
    Route::middleware(['role:director,aduana'])->prefix('customs')->name('customs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Customs\CustomsController::class, 'index'])->name('index');
        Route::get('/{equipment}', [\App\Http\Controllers\Customs\CustomsController::class, 'show'])->name('show');
        Route::post('/{equipment}/start-inspection', [\App\Http\Controllers\Customs\CustomsController::class, 'startInspection'])->name('start-inspection');
        Route::post('/{equipment}/clear', [\App\Http\Controllers\Customs\CustomsController::class, 'clear'])->name('clear');
        Route::post('/{equipment}/hold', [\App\Http\Controllers\Customs\CustomsController::class, 'hold'])->name('hold');
    });

    // Módulo de Hojas de Ruta y Logística Regional (Protegido por RBAC)
    Route::middleware(['role:director,logistica_chofer,despacho_puerto'])->prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/routes', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'index'])->name('routes.index');
        Route::get('/routes/create', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'create'])->name('routes.create');
        Route::post('/routes', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'store'])->name('routes.store');
        Route::get('/routes/{route}', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'show'])->name('routes.show');
        Route::post('/routes/{route}/dispatch', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'dispatchConvoy'])->name('routes.dispatch');
        Route::post('/routes/{route}/receive', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'receiveConvoy'])->name('routes.receive');
        Route::post('/routes/{route}/items', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'addItem'])->name('routes.items.add');
        Route::delete('/routes/{route}/items/{equipment}', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'removeItem'])->name('routes.items.remove');
        Route::post('/routes/{route}/scan-receive', [\App\Http\Controllers\Logistics\LogisticsRouteController::class, 'scanReceive'])->name('routes.scan-receive');
    });
});

// Admin Panel Routes (Exclusivo Dirección General)
Route::middleware(['auth', 'role:director'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // User & Role Management (7 Perfiles)
    Route::resource('users', AdminUserController::class);
    Route::post('users/{user}/toggle-status', [AdminUserController::class, 'toggleStatus'])->name('users.toggle-status');
});
