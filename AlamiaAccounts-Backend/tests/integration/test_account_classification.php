<?php

require_once __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\ReportService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Enums\AccountClass;
use Abivia\Ledger\Models\LedgerAccount;

echo "========================================================================" . PHP_EOL;
echo " TEST: FORMAL ACCOUNT CLASSIFICATION & DOUBLE-ENTRY NORMS" . PHP_EOL;
echo "========================================================================" . PHP_EOL;

DomainContext::set('KAMAL_EXPRESS');
$accountService = app(AccountService::class);
$reportService = app(ReportService::class);

$allAccounts = $accountService->getAllAccounts()->keyBy('ledgerUuid');

// 1. Verify Assets
$cash = $accountService->getAccountByCode('1110');
assert($cash !== null, 'Cash account 1110 must exist');
$cashClass = $accountService->getAccountClassEnum($cash, $allAccounts);
assert($cashClass === AccountClass::ASSET, '1110 Cash must be classified as ASSET');
assert($cashClass->isBalanceSheet() === true, 'Asset must belong to Balance Sheet');
assert($cashClass->isProfitAndLoss() === false, 'Asset must not belong to P&L');
assert($cashClass->isDebitNormal() === true, 'Asset must be Debit Normal');
echo " [PASS] 1110 Cash classified as ASSET (Balance Sheet, Normal: Debit)" . PHP_EOL;

// 2. Verify Liabilities
$ap = $accountService->getAccountByCode('2100');
assert($ap !== null, 'AP account 2100 must exist');
$apClass = $accountService->getAccountClassEnum($ap, $allAccounts);
assert($apClass === AccountClass::LIABILITY, '2100 AP must be classified as LIABILITY');
assert($apClass->isBalanceSheet() === true, 'Liability must belong to Balance Sheet');
assert($apClass->isProfitAndLoss() === false, 'Liability must not belong to P&L');
assert($apClass->isCreditNormal() === true, 'Liability must be Credit Normal');
echo " [PASS] 2100 Accounts Payable classified as LIABILITY (Balance Sheet, Normal: Credit)" . PHP_EOL;

// 3. Verify Revenue / Income
$rev = $accountService->getAccountByCode('3100');
assert($rev !== null, 'Sales Revenue account 3100 must exist');
$revClass = $accountService->getAccountClassEnum($rev, $allAccounts);
assert($revClass === AccountClass::REVENUE, '3100 Sales Revenue must be classified as REVENUE');
assert($revClass->isProfitAndLoss() === true, 'Revenue must belong to P&L');
assert($revClass->isBalanceSheet() === false, 'Revenue must not belong to Balance Sheet');
assert($revClass->isCreditNormal() === true, 'Revenue must be Credit Normal');
echo " [PASS] 3100 Sales Revenue classified as REVENUE (P&L, Normal: Credit)" . PHP_EOL;

// 4. Verify Expenses
$cogs = $accountService->getAccountByCode('4100');
assert($cogs !== null, 'COGS account 4100 must exist');
$cogsClass = $accountService->getAccountClassEnum($cogs, $allAccounts);
assert($cogsClass === AccountClass::EXPENSE, '4100 COGS must be classified as EXPENSE');
assert($cogsClass->isProfitAndLoss() === true, 'Expense must belong to P&L');
assert($cogsClass->isBalanceSheet() === false, 'Expense must not belong to Balance Sheet');
assert($cogsClass->isDebitNormal() === true, 'Expense must be Debit Normal');
echo " [PASS] 4100 COGS classified as EXPENSE (P&L, Normal: Debit)" . PHP_EOL;

// 5. Verify Equity
$capital = $accountService->getAccountByCode('5100');
assert($capital !== null, 'Capital account 5100 must exist');
$capClass = $accountService->getAccountClassEnum($capital, $allAccounts);
assert($capClass === AccountClass::EQUITY, '5100 Capital must be classified as EQUITY');
assert($capClass->isBalanceSheet() === true, 'Equity must belong to Balance Sheet');
assert($capClass->isProfitAndLoss() === false, 'Equity must not belong to P&L');
assert($capClass->isCreditNormal() === true, 'Equity must be Credit Normal');
echo " [PASS] 5100 Capital classified as EQUITY (Balance Sheet, Normal: Credit)" . PHP_EOL;

// 6. Verify ReportService delegates to AccountClass seamlessly
assert($reportService->getAccountClassEnum($rev, $allAccounts) === AccountClass::REVENUE);
assert($reportService->getAccountClass($rev, $allAccounts) === 'revenue');
assert($reportService->getAccountClassEnum($cogs, $allAccounts) === AccountClass::EXPENSE);
assert($reportService->getAccountClass($cogs, $allAccounts) === 'expense');
echo " [PASS] ReportService delegates cleanly without heuristic string matching" . PHP_EOL;

echo "========================================================================" . PHP_EOL;
echo " ALL TESTS PASSED (6/6)" . PHP_EOL;
echo "========================================================================" . PHP_EOL;
