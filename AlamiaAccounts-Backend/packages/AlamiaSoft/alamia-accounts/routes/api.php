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
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\UserController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\PeriodController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\OpeningBalanceController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\AuditTrailController;
use App\Http\Controllers\Api\CopilotController;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');
Route::post('/copilot/chat-public', [CopilotController::class, 'chat']);
Route::get('/copilot/capabilities-public', [CopilotController::class, 'capabilities']);
Route::post('/copilot/capabilities-public/{capability}/execute', [CopilotController::class, 'executeCapability']);
Route::post('/alamia-360/capabilities-public/{capability}/execute', [CopilotController::class, 'executeCapability']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Companies
    Route::post('/companies/{code}/switch', [CompanyController::class, 'switch']);
    Route::get('/companies/current', [CompanyController::class, 'current']);
    Route::apiResource('companies', CompanyController::class);
    
    // Users
    Route::apiResource('users', UserController::class);
    
    // Accounts
    Route::post('/accounts/{code}/opening-balance', [AccountController::class, 'setOpeningBalance']);
    Route::apiResource('accounts', AccountController::class);
    
    // Vouchers
    Route::post('/vouchers/{reference}/reverse', [VoucherController::class, 'reverse']);
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
    Route::get('/reports/receivables', [ReportController::class, 'receivables']);
    Route::get('/reports/payables', [ReportController::class, 'payables']);
    // Periods & Fiscal Controls
    Route::get('/periods', [PeriodController::class, 'index']);
    Route::post('/periods/{id}/close', [PeriodController::class, 'close']);
    Route::post('/periods/{id}/reopen', [PeriodController::class, 'reopen']);

    // Opening Balances
    Route::get('/opening-balances', [OpeningBalanceController::class, 'index']);
    Route::post('/opening-balances', [OpeningBalanceController::class, 'store']);

    // Audit Trail
    Route::get('/audit-trail', [AuditTrailController::class, 'index']);

    // Copilot (Taliya AI) & Alamia 360 Capabilities
    Route::post('/copilot/chat', [CopilotController::class, 'chat']);
    Route::get('/copilot/capabilities', [CopilotController::class, 'capabilities']);
    Route::get('/copilot/situations', [CopilotController::class, 'situations']);
    Route::post('/copilot/capabilities/{capability}/execute', [CopilotController::class, 'executeCapability']);
    Route::post('/alamia-360/capabilities/{capability}/execute', [CopilotController::class, 'executeCapability']);
});
