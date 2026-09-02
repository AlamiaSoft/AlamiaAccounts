<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\AuthController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\CompanyController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\AccountController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\VoucherController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\ReportController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\CustomVoucherTypeController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\SearchController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\PrintController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Companies
    Route::post('/companies/{code}/switch', [CompanyController::class, 'switch']);
    Route::get('/companies/current', [CompanyController::class, 'current']);
    Route::apiResource('companies', CompanyController::class);
    
    // Accounts
    Route::apiResource('accounts', AccountController::class);
    
    // Vouchers
    Route::apiResource('vouchers', VoucherController::class);
    
    // Custom Voucher Types
    Route::apiResource('voucher-types', CustomVoucherTypeController::class);
    Route::post('/voucher-types/{id}/validate', [CustomVoucherTypeController::class, 'validate']);
    
    // Search
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/search/vouchers', [SearchController::class, 'searchVouchers']);
    Route::get('/search/accounts', [SearchController::class, 'searchAccounts']);
    Route::get('/search/ledgers', [SearchController::class, 'searchLedgerEntries']);
    
    // Print
    Route::get('/print/voucher/{id}', [PrintController::class, 'printVoucher']);
    Route::post('/print/report', [PrintController::class, 'printReport']);
    Route::get('/print/template', [PrintController::class, 'getTemplate']);
    Route::post('/print/template', [PrintController::class, 'saveTemplate']);
    Route::post('/print/upload-logo', [PrintController::class, 'uploadLogo']);
    
    // Reports
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance']);
    Route::get('/reports/profit-loss', [ReportController::class, 'profitAndLoss']);
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('/reports/ledger', [ReportController::class, 'ledger']);
});
