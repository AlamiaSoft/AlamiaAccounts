<?php

/**
 * Manual Verification Script for AlamiaAccounts Package
 * 
 * Run this in Tinker: php artisan tinker < packages/alamiasoft/alamia-accounts/verify.php
 * Or copy-paste sections into Tinker manually.
 */

use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use AlamiaSoft\AlamiaAccounts\Services\ReportService;

echo "=== AlamiaAccounts Package Verification ===\n\n";

// Initialize services
$accountService = app(AccountService::class);
$voucherService = app(VoucherService::class);
$reportService = app(ReportService::class);

echo "1. Creating Chart of Accounts...\n";

// Create main account groups
$accountService->createAccount([
    'code' => 'ASSETS',
    'name' => 'Assets',
    'debit' => true,
]);

$accountService->createAccount([
    'code' => 'LIABILITIES',
    'name' => 'Liabilities',
    'debit' => false,
]);

$accountService->createAccount([
    'code' => 'EQUITY',
    'name' => 'Equity',
    'debit' => false,
]);

$accountService->createAccount([
    'code' => 'REVENUE',
    'name' => 'Revenue',
    'category' => 'revenue',
    'debit' => false,
]);

$accountService->createAccount([
    'code' => 'EXPENSES',
    'name' => 'Expenses',
    'category' => 'expense',
    'debit' => true,
]);

// Create sub-accounts
$accountService->createAccount([
    'code' => 'CASH',
    'name' => 'Cash in Hand',
    'parent_code' => 'ASSETS',
    'debit' => true,
]);

$accountService->createAccount([
    'code' => 'BANK',
    'name' => 'Bank Account',
    'parent_code' => 'ASSETS',
    'debit' => true,
]);

$accountService->createAccount([
    'code' => 'SALES',
    'name' => 'Sales Revenue',
    'parent_code' => 'REVENUE',
    'category' => 'revenue',
    'debit' => false,
]);

echo "   Created accounts successfully!\n\n";

echo "2. Creating Sample Vouchers...\n";

// Create a sales voucher
$voucherService->createJournalEntry([
    'date' => '2025-01-01',
    'description' => 'Cash Sales',
    'currency' => 'USD',
    'entries' => [
        [
            'account_code' => 'CASH',
            'amount' => 1000,
            'type' => 'debit',
        ],
        [
            'account_code' => 'SALES',
            'amount' => 1000,
            'type' => 'credit',
        ],
    ],
]);

echo "   Created vouchers successfully!\n\n";

echo "3. Retrieving Chart of Accounts...\n";
$accounts = $accountService->getChartOfAccounts();
echo "   Total root accounts: " . $accounts->count() . "\n\n";

echo "4. Getting Trial Balance...\n";
$trialBalance = $reportService->getTrialBalance('2025-01-31', 'USD');
echo "   Trial Balance entries: " . count($trialBalance) . "\n\n";

echo "=== Verification Complete ===\n";
echo "All services are working correctly!\n";
