<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PersonController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\CpfValidationController;
use App\Http\Controllers\Api\CnpjValidationController;
use App\Http\Controllers\Api\EmailValidationController;
use App\Http\Controllers\TrashController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/document/cpf/validate', CpfValidationController::class);
Route::post('/document/cnpj/validate', CnpjValidationController::class);
Route::post('/email/validate', EmailValidationController::class);

// Rotas protegidas por autenticação
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    Route::get('/trash', [TrashController::class, 'index']);
    Route::post('/trash/{id}/restore', [TrashController::class, 'restaurar']);
    Route::delete('/trash/{id}/destroy', [TrashController::class, 'destruirPermanente']);

    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary']);
        Route::get('/top-products', [DashboardController::class, 'topProducts']);
        Route::get('/sales-by-group', [DashboardController::class, 'salesByGroup']);
        Route::get('/daily-sales', [DashboardController::class, 'dailySales']);
        Route::get('/without-sales', [DashboardController::class, 'productsWithoutSales']);
        Route::get('/low-stock', [DashboardController::class, 'lowStock']);
        Route::get('/recent-activities', [DashboardController::class, 'recentActivities']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('/sales', [ReportController::class, 'sales']);
        Route::get('/purchases', [ReportController::class, 'purchases']);
        Route::get('/products', [ReportController::class, 'products']);
        Route::get('/people', [ReportController::class, 'people']);
    });
    
    // Rotas para gerenciamento de pessoas
    Route::apiResource('person', PersonController::class);
    Route::apiResource('groups', GroupController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('sales', SaleController::class);
    Route::apiResource('purchases', PurchaseController::class);
});