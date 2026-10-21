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
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\CopilotController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\ManualController;
use AlamiaSoft\AlamiaAccounts\Http\Controllers\Api\V1\SalesIntegrationController;
use AlamiaSoft\AlamiaAccounts\Http\Middleware\AuthenticateSalesOrSanctum;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('accounts.api.login');
Route::get('/manual', [ManualController::class, 'index']);

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
    
    // Tenant-scoped Accounting Operations (Fail-Closed Company Context)
    Route::middleware('accounts.company')->group(function () {
        // Accounts
        Route::post('/accounts/{code}/opening-balance', [AccountController::class, 'setOpeningBalance']);
        Route::apiResource('accounts', AccountController::class);
        
        // Vouchers
        // Destructive bulk ledger reset is strictly excluded from production and requires explicit feature flag.
        if (!app()->environment('production') && config('alamia-accounts.allow_destructive_reset', false)) {
            Route::post('/vouchers/clear-all', [VoucherController::class, 'clearAll']);
        }
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
        Route::get('/reports/bank-book', [ReportController::class, 'bankBook']);
        Route::get('/reports/receivables', [ReportController::class, 'receivables']);
        Route::get('/reports/payables', [ReportController::class, 'payables']);
        Route::get('/reports/balance-sheet-diagnostics', [ReportController::class, 'balanceSheetDiagnostics']);
        Route::get('/reports/ledger-integrity-audit', [ReportController::class, 'ledgerIntegrityAudit']);
        
        // Periods & Fiscal Controls
        Route::get('/periods', [PeriodController::class, 'index']);
        Route::post('/periods/{id}/close', [PeriodController::class, 'close']);
        Route::post('/periods/{id}/reopen', [PeriodController::class, 'reopen']);

        // Opening Balances
        Route::get('/opening-balances', [OpeningBalanceController::class, 'index']);
        Route::post('/opening-balances', [OpeningBalanceController::class, 'store']);

        // Audit Trail
        Route::get('/audit-trail', [AuditTrailController::class, 'index']);

        // Copilot (Taliya AI) & Alamia 360 Capabilities (Rate Limited)
        Route::middleware(['throttle:60,1'])->group(function () {
            Route::post('/copilot/chat', [CopilotController::class, 'chat']);
            Route::get('/copilot/capabilities', [CopilotController::class, 'capabilities']);
            Route::get('/copilot/situations', [CopilotController::class, 'situations']);
            Route::post('/copilot/capabilities/{capability}/execute', [CopilotController::class, 'executeCapability']);
            Route::post('/alamia-360/capabilities/{capability}/execute', [CopilotController::class, 'executeCapability']);

            // Copilot Diagnostics & Developer Observability (Privileged Administrative Access)
            Route::get('/copilot/diagnostics', [CopilotController::class, 'diagnostics']);
            Route::get('/copilot/diagnostics/export', [CopilotController::class, 'exportDiagnostics']);
            Route::get('/copilot/diagnostics/{id}', [CopilotController::class, 'diagnosticTrace']);
            Route::patch('/copilot/diagnostics/{id}/feedback', [CopilotController::class, 'updateDiagnosticFeedback']);
            Route::post('/copilot/diagnostics/{id}/promote-to-guidance', [CopilotController::class, 'promoteDiagnosticToKnowledge']);
            Route::delete('/copilot/diagnostics', [CopilotController::class, 'pruneDiagnostics']);

            // Copilot Knowledge Base (Self-Learning Guidance)
            Route::get('/copilot/knowledge', [CopilotController::class, 'knowledgeList']);
            Route::post('/copilot/knowledge', [CopilotController::class, 'knowledgeStore']);
            Route::delete('/copilot/knowledge/{id}', [CopilotController::class, 'knowledgeDelete']);
        });
    });
});

// Front-Office & Sales POS Integration API (v1) - Dual Sanctum User & Machine POS Gateway Authentication
Route::middleware([AuthenticateSalesOrSanctum::class])->group(function () {
    Route::post('/v1/sales', [SalesIntegrationController::class, 'store']);
    Route::get('/v1/sales', [SalesIntegrationController::class, 'index']);
    Route::get('/v1/sales/{id}', [SalesIntegrationController::class, 'show'])->whereNumber('id');
    Route::post('/v1/sales/{id}/approve', [SalesIntegrationController::class, 'approve'])->whereNumber('id');
    Route::post('/v1/sales/reconcile', [SalesIntegrationController::class, 'reconcileShift']);
    Route::get('/v1/sales/reconcile-shift', [SalesIntegrationController::class, 'reconcileShift']);
    Route::get('/v1/receipts/{id}/print', [SalesIntegrationController::class, 'printReceipt'])->whereNumber('id');
});

// Diagnostic and knowledge-management endpoints are intentionally not exposed publicly.
// Use the authenticated routes above and enforce company-scoped authorization.
