<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Models\OpeningBalanceBatch;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;
use Exception;

class OpeningBalanceService
{
    protected VoucherService $voucherService;
    protected PeriodService $periodService;

    public function __construct(VoucherService $voucherService, PeriodService $periodService)
    {
        $this->voucherService = $voucherService;
        $this->periodService = $periodService;
    }

    protected function getCurrentDomain(): LedgerDomain
    {
        $code = DomainContext::get();
        if (!$code) {
            $domain = LedgerDomain::first();
            if ($domain) {
                DomainContext::set($domain->code);
                return $domain;
            }
            throw new Exception('No company domain found.');
        }

        $domain = LedgerDomain::where('code', $code)->first();
        if (!$domain) {
            throw new Exception("Company domain '{$code}' not found.");
        }

        return $domain;
    }

    /**
     * Get opening balance batch status for current domain.
     */
    public function getOpeningBalanceStatus(): array
    {
        $domain = $this->getCurrentDomain();
        $batch = OpeningBalanceBatch::where('domain_uuid', $domain->domainUuid)->first();

        return [
            'is_initialized' => $batch !== null,
            'batch' => $batch,
        ];
    }

    /**
     * Post a compound, balanced Opening Balance entry.
     */
    public function postOpeningBalances(array $data): array
    {
        $domain = $this->getCurrentDomain();

        // 1. Prevent duplicate opening balance initialization
        $existingBatch = OpeningBalanceBatch::where('domain_uuid', $domain->domainUuid)->first();
        if ($existingBatch) {
            throw new Exception("Opening balances have already been established for this company (Reference: {$existingBatch->reference}). Use journal vouchers for subsequent adjustments.");
        }

        $balanceDate = $data['balance_date'] ?? date('Y-01-01');
        $entries = $data['entries'] ?? [];

        if (empty($entries) || count($entries) < 2) {
            throw new Exception("Opening balance setup requires at least two account entries.");
        }

        // 2. Validate period
        $this->periodService->validatePostingDate($domain->domainUuid, $balanceDate);

        // 3. Verify accounts belong to domain and are leaf/posting accounts
        $accountCodes = array_map(fn($e) => $e['account_code'] ?? $e['account'], $entries);
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($domain->domainUuid);

        foreach ($accountCodes as $code) {
            $globalAcc = LedgerAccount::where('code', $code)->first();
            if ($globalAcc && !in_array($globalAcc->ledgerUuid, $accountUuids)) {
                DomainLedgerAccount::firstOrCreate([
                    'domainUuid' => $domain->domainUuid,
                    'ledgerUuid' => $globalAcc->ledgerUuid,
                ]);
            }
        }
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($domain->domainUuid);

        $accounts = LedgerAccount::whereIn('code', $accountCodes)
            ->whereIn('ledgerUuid', $accountUuids)
            ->get()
            ->keyBy('code');

        if ($accounts->count() !== count(array_unique($accountCodes))) {
            throw new Exception("One or more opening balance accounts do not belong to the current company.");
        }

        foreach ($accounts as $code => $acc) {
            if ($acc->category) {
                throw new Exception("Account '{$code} - {$acc->name}' is a category folder and cannot receive opening balances. Please select a posting account.");
            }
        }

        // 4. Calculate total debits and credits
        $totalDebit = 0;
        $totalCredit = 0;
        $formattedEntries = [];

        foreach ($entries as $item) {
            $code = $item['account_code'] ?? $item['account'];
            $amt = (float)($item['amount'] ?? 0);
            if ($amt <= 0) continue;

            $type = strtolower($item['type'] ?? 'debit');
            if ($type === 'debit') {
                $totalDebit += $amt;
            } else {
                $totalCredit += $amt;
            }

            $formattedEntries[] = [
                'account_code' => $code,
                'amount' => $amt,
                'type' => $type,
                'description' => $item['description'] ?? 'Opening Balance',
            ];
        }

        $variance = round($totalDebit - $totalCredit, 2);
        $balancingAccountCode = $data['balancing_account_code'] ?? null;
        $balancingAmount = 0;

        // 5. Check equilibrium
        if (abs($variance) > 0.001) {
            if (!$balancingAccountCode) {
                $diffText = number_format(abs($variance), 2);
                throw new Exception("Opening balances do not balance! Total Debits: PKR " . number_format($totalDebit, 2) . ", Total Credits: PKR " . number_format($totalCredit, 2) . ". Variance: PKR {$diffText}. Please specify a balancing equity account or balance your entries.");
            }

            // Assign difference to balancing account
            $balancingAmount = abs($variance);
            $balancingType = $variance > 0 ? 'credit' : 'debit';

            $formattedEntries[] = [
                'account_code' => $balancingAccountCode,
                'amount' => $balancingAmount,
                'type' => $balancingType,
                'description' => "Opening Balance Reconciliation to Equity/Capital",
            ];

            if ($balancingType === 'debit') {
                $totalDebit += $balancingAmount;
            } else {
                $totalCredit += $balancingAmount;
            }
        }

        $reference = 'OB-' . date('Y', strtotime($balanceDate)) . '-001';

        // 6. Execute atomically in DB transaction
        return DB::transaction(function () use ($domain, $reference, $balanceDate, $totalDebit, $totalCredit, $balancingAccountCode, $balancingAmount, $formattedEntries) {
            // Post journal voucher
            $voucher = $this->voucherService->createJournalEntry([
                'reference' => $reference,
                'voucher_type' => 'Opening',
                'description' => 'Opening Balance Initialization',
                'date' => $balanceDate,
                'currency' => $domain->currencyDefault ?? 'PKR',
                'entries' => $formattedEntries,
            ]);

            // Save batch record
            $batch = OpeningBalanceBatch::create([
                'domain_uuid' => $domain->domainUuid,
                'reference' => $reference,
                'balance_date' => $balanceDate,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balancing_account_code' => $balancingAccountCode,
                'balancing_amount' => $balancingAmount,
                'status' => 'posted',
                'created_by' => auth()->check() ? (string)auth()->id() : 'accountant',
            ]);

            // Record audit trail
            AccountingAuditTrail::record(
                $domain->domainUuid,
                'POST_OPENING_BALANCES',
                'opening_balance',
                $reference,
                [
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'variance_allocated' => $balancingAmount,
                    'balancing_account' => $balancingAccountCode,
                    'balance_date' => $balanceDate,
                ]
            );

            return [
                'message' => 'Opening balances successfully posted and verified.',
                'reference' => $reference,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'batch' => $batch,
                'voucher' => $voucher,
            ];
        });
    }
}
