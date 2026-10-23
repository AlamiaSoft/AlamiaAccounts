<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Exceptions\PostingValidationException;
use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PostingValidationService
{
    public function __construct(
        protected AccountService $accountService,
        protected PeriodService $periodService,
        protected CompanyService $companyService
    ) {}

    /**
     * Authoritative write-boundary validation for all voucher postings.
     * Enforces domain context, postable leaf accounts, contradictory payload rejection,
     * positive amounts, double-entry mathematical balance, period lock, currency,
     * company-scoped reference uniqueness, and maker-checker segregation of duties.
     *
     * @param object|string $domain LedgerDomain model or domain code string
     * @param array $data Raw voucher input data
     * @param array|null $actorContext Optional authenticated actor and correlation context
     * @return array Normalized and certified posting payload
     * @throws PostingValidationException
     */
    public function validateVoucherPosting(object|string $domain, array $data, ?array $actorContext = null): array
    {
        // 1. Resolve and verify company domain context
        if (is_string($domain)) {
            $domainObj = $this->companyService->getCompanyByCode($domain);
            if (!$domainObj) {
                throw new PostingValidationException("Company context is required for voucher operations. No active company domain set.");
            }
            $domain = $domainObj;
        }

        if (empty($domain->code) || empty($domain->domainUuid)) {
            throw new PostingValidationException("Company context is required for voucher operations. No active company domain set.");
        }

        $activeScopedDomain = DomainContext::get();
        if ($activeScopedDomain !== null && $activeScopedDomain !== '' && strcasecmp($activeScopedDomain, $domain->code) !== 0) {
            throw new PostingValidationException("Domain context mismatch: current context is '{$activeScopedDomain}' but posting requested for '{$domain->code}'.");
        }

        // 2. Validate line items presence
        $rawEntries = $data['entries'] ?? ($data['details'] ?? ($data['line_items'] ?? ($data['lineItems'] ?? [])));
        if (!is_array($rawEntries) || count($rawEntries) < 2) {
            throw new PostingValidationException('Voucher must contain at least two entries');
        }

        // 3. Normalize and validate line items
        $normalizedEntries = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $accountCodes = [];

        foreach ($rawEntries as $index => $item) {
            $lineNum = $index + 1;
            if (!is_array($item)) {
                throw new PostingValidationException("Line item #{$lineNum} is invalid: must be an associative array.");
            }

            $accountCode = trim((string)($item['account_code'] ?? ($item['account'] ?? '')));
            if ($accountCode === '') {
                throw new PostingValidationException("Line item #{$lineNum} must specify a valid account code.");
            }

            $hasDebit = array_key_exists('debit', $item) && $item['debit'] !== null && $item['debit'] !== '';
            $hasCredit = array_key_exists('credit', $item) && $item['credit'] !== null && $item['credit'] !== '';
            $hasAmount = array_key_exists('amount', $item) && $item['amount'] !== null && $item['amount'] !== '';
            $hasType = array_key_exists('type', $item) && $item['type'] !== null && $item['type'] !== '';

            $isDebitBool = $hasDebit && is_bool($item['debit']) && $item['debit'] === true;
            $isCreditBool = $hasCredit && is_bool($item['credit']) && $item['credit'] === true;

            $debitVal = ($hasDebit && is_numeric($item['debit'])) ? (float)$item['debit'] : null;
            $creditVal = ($hasCredit && is_numeric($item['credit'])) ? (float)$item['credit'] : null;
            $amtVal = ($hasAmount && is_numeric($item['amount'])) ? (float)$item['amount'] : null;
            $typeVal = $hasType ? strtolower(trim((string)$item['type'])) : null;

            // 3.1 Contradictory boolean flags
            if ($isDebitBool && $isCreditBool) {
                throw new PostingValidationException("Contradictory line payload: line item for account '{$accountCode}' cannot specify both debit and credit.");
            }

            // 3.2 Negative value rejection
            if (($debitVal !== null && $debitVal < 0) || ($creditVal !== null && $creditVal < 0) || ($amtVal !== null && $amtVal < 0)) {
                throw new PostingValidationException("Line item for account '{$accountCode}' must have positive amount; negative values are prohibited.");
            }

            // 3.3 Contradictory numeric debit and credit both > 0 on same line
            if ($debitVal !== null && $creditVal !== null && $debitVal > 0 && $creditVal > 0) {
                throw new PostingValidationException("Contradictory line payload: line item for account '{$accountCode}' cannot specify both positive debit and credit.");
            }

            // 3.4 Invalid type keyword
            if ($typeVal !== null && !in_array($typeVal, ['debit', 'credit'], true)) {
                throw new PostingValidationException("Invalid line item type '{$typeVal}' for account '{$accountCode}': must be 'debit' or 'credit'.");
            }

            // 3.5 Contradictory type vs credit/debit
            if ($typeVal === 'debit' && (($creditVal !== null && $creditVal > 0) || $isCreditBool)) {
                throw new PostingValidationException("Contradictory line payload: line for account '{$accountCode}' specifies type 'debit' but provides credit amount.");
            }
            if ($typeVal === 'credit' && (($debitVal !== null && $debitVal > 0) || $isDebitBool)) {
                throw new PostingValidationException("Contradictory line payload: line for account '{$accountCode}' specifies type 'credit' but provides debit amount.");
            }

            // 3.6 Resolve amount and side (debit or credit)
            $resolvedAmount = 0.0;
            $resolvedType = null;

            if ($debitVal !== null && $debitVal > 0) {
                $resolvedAmount = $debitVal;
                $resolvedType = 'debit';
                if ($amtVal !== null && abs($amtVal - $debitVal) > 0.0001) {
                    throw new PostingValidationException("Ambiguous line payload for account '{$accountCode}': amount ({$amtVal}) does not match debit ({$debitVal}).");
                }
            } elseif ($creditVal !== null && $creditVal > 0) {
                $resolvedAmount = $creditVal;
                $resolvedType = 'credit';
                if ($amtVal !== null && abs($amtVal - $creditVal) > 0.0001) {
                    throw new PostingValidationException("Ambiguous line payload for account '{$accountCode}': amount ({$amtVal}) does not match credit ({$creditVal}).");
                }
            } elseif ($amtVal !== null && $amtVal > 0) {
                $resolvedAmount = $amtVal;
                if ($isCreditBool || $typeVal === 'credit') {
                    $resolvedType = 'credit';
                } elseif ($isDebitBool || $typeVal === 'debit') {
                    $resolvedType = 'debit';
                } else {
                    throw new PostingValidationException("Ambiguous line payload for account '{$accountCode}': amount provided without specifying debit or credit type.");
                }
            } else {
                throw new PostingValidationException("Voucher line item amount must be greater than zero");
            }

            if ($resolvedAmount <= 0) {
                throw new PostingValidationException("Voucher line item amount must be greater than zero");
            }

            if ($resolvedType === 'debit') {
                $totalDebit += $resolvedAmount;
            } else {
                $totalCredit += $resolvedAmount;
            }

            $accountCodes[] = $accountCode;
            $normalizedEntries[] = [
                'account_code' => $accountCode,
                'amount' => $resolvedAmount,
                'type' => $resolvedType,
                'debit' => $resolvedType === 'debit' ? $resolvedAmount : 0.0,
                'credit' => $resolvedType === 'credit' ? $resolvedAmount : 0.0,
                'description' => $item['description'] ?? ($item['narration'] ?? null),
            ];
        }

        // 4. Double-entry mathematical balance invariant
        if (abs($totalDebit - $totalCredit) > 0.0001) {
            throw new PostingValidationException("Voucher is unbalanced: total debits (Rs. " . number_format($totalDebit, 2) . ") must equal total credits (Rs. " . number_format($totalCredit, 2) . ").");
        }

        // 5. Verify postable leaf accounts in domain (strictly category: false)
        $uniqueAccountCodes = array_values(array_unique($accountCodes));
        $domainAccounts = $this->accountService->getAccountsByCodesForDomain($domain->domainUuid, $uniqueAccountCodes);

        foreach ($uniqueAccountCodes as $code) {
            $account = $domainAccounts[$code] ?? null;
            if (!$account) {
                throw new PostingValidationException('One or more accounts do not belong to the current domain');
            }
            if ($account->category) {
                throw new PostingValidationException("Account {$code} is a category account and cannot receive postings");
            }
        }

        // 6. Netting check for multi-entry same-account lines (e.g. commission offsetting)
        $accountNetAmounts = [];
        foreach ($normalizedEntries as $entry) {
            $code = $entry['account_code'];
            $signed = $entry['type'] === 'credit' ? -$entry['amount'] : $entry['amount'];
            $accountNetAmounts[$code] = ($accountNetAmounts[$code] ?? 0.0) + $signed;
        }

        $activeNetted = array_filter($accountNetAmounts, fn($amt) => abs($amt) >= 0.0001);
        if (count($activeNetted) < 2) {
            throw new PostingValidationException('Voucher must contain at least two non-cancelling account entries for double-entry ledger posting');
        }

        // 7. Currency validation against domain default currency
        $domainCurrency = $domain->currencyDefault ?? config('alamia-accounts.default_currency', 'PKR');
        if (!empty($data['currency']) && strtoupper(trim((string)$data['currency'])) !== strtoupper(trim((string)$domainCurrency))) {
            throw new PostingValidationException("Currency '{$data['currency']}' does not match company default currency '{$domainCurrency}'.");
        }

        // 8. Date and fiscal period validation
        $rawDate = $data['transDate'] ?? ($data['date'] ?? Carbon::now()->toDateString());
        try {
            $transDate = Carbon::parse($rawDate);
            $transDateStr = $transDate->toDateString();
        } catch (\Throwable $e) {
            throw new PostingValidationException("Invalid posting date format: '{$rawDate}'.");
        }

        $this->periodService->validatePostingDate($domain->domainUuid, $transDateStr);

        // 9. Reference uniqueness check (company-scoped)
        $refToCheck = trim((string)($data['reference'] ?? ($data['voucher_number'] ?? '')));
        if ($refToCheck !== '') {
            if (DomainJournalEntry::referenceExistsInDomain($domain->domainUuid, $refToCheck)) {
                throw new PostingValidationException("Duplicate voucher reference '{$refToCheck}' is not allowed for this company");
            }
        }

        // 10. Maker-checker policy and segregation of duties
        $voucherType = (string)($data['voucher_type'] ?? ($data['custom_voucher_type_name'] ?? ($data['type'] ?? 'journal')));
        $lowerVoucherType = strtolower($voucherType);
        $isExemptVoucherType = in_array($lowerVoucherType, ['opening', 'reversal'], true)
            || str_starts_with(strtoupper($refToCheck), 'OB-')
            || str_starts_with(strtoupper($refToCheck), 'REV-');

        $isSystemBypass = (bool)($actorContext['system_bypass'] ?? false);
        $makerCheckerThreshold = (float) config('alamia-accounts.maker_checker_threshold', config('copilot.maker_checker_threshold', 100000.0));

        $makerId = $actorContext['maker_id'] ?? ($actorContext['user_id'] ?? ($data['maker_id'] ?? (auth()->check() ? (string)auth()->id() : null)));
        $makerName = $actorContext['user_name'] ?? ($actorContext['maker_name'] ?? (auth()->check() ? auth()->user()->name : 'Accountant / System'));

        $approval = $data['approval'] ?? ($actorContext['approval'] ?? null);
        if ($approval === null && !empty($data['reason'])) {
            $approval = [
                'reason' => $data['reason'],
                'reentered_amount' => $data['reentered_amount'] ?? null,
                'approver_id' => $data['approver_id'] ?? null,
            ];
        }

        $approverId = null;
        $approvalReason = null;
        $reenteredAmount = null;

        if (!$isExemptVoucherType && !$isSystemBypass && $totalDebit >= $makerCheckerThreshold) {
            if (empty($approval) || !is_array($approval)) {
                throw new PostingValidationException('A detailed business reason (minimum 15 characters and 3 words) is mandatory for institutional authorization.');
            }

            $reason = trim((string)($approval['reason'] ?? ''));
            $words = array_values(array_filter(preg_split('/\s+/', $reason), fn($w) => strlen($w) > 1));
            $trivialList = ['ok', 'test', 'done', 'fixed', 'asdf', 'n/a', 'reason', 'correct', 'yes', 'none', 'approved'];
            $nonTrivialWords = array_filter($words, fn($w) => !in_array(strtolower($w), $trivialList, true));
            $isTrivial = in_array(strtolower($reason), $trivialList, true) || count($nonTrivialWords) < 2;

            if (strlen($reason) < 15 || count($words) < 3 || $isTrivial) {
                throw new PostingValidationException('A detailed business reason (minimum 15 characters and 3 words) is mandatory for institutional authorization.');
            }

            $reenteredRaw = $approval['reentered_amount'] ?? null;
            if ($reenteredRaw === null || $reenteredRaw === '') {
                throw new PostingValidationException('Amount confirmation mismatch: For high-risk transactions (>= Rs. ' . number_format($makerCheckerThreshold, 2) . '), you must re-enter the exact amount (Rs. ' . number_format($totalDebit, 2) . ') to verify authorization.');
            }

            $reenteredAmount = (float) str_replace(',', '', (string)$reenteredRaw);
            if (abs($reenteredAmount - $totalDebit) > 0.01) {
                throw new PostingValidationException('Amount confirmation mismatch: For high-risk transactions (>= Rs. ' . number_format($makerCheckerThreshold, 2) . '), you must re-enter the exact amount (Rs. ' . number_format($totalDebit, 2) . ') to verify authorization.');
            }

            $approverId = $approval['approver_id'] ?? ($approval['approved_by'] ?? ($data['approver_id'] ?? ($actorContext['approver_id'] ?? null)));

            // Segregation of duties: Maker !== Approver
            if ($makerId !== null && $approverId !== null) {
                if ((string)$makerId === (string)$approverId) {
                    throw new PostingValidationException('Self-approval prohibited: maker and approver cannot be the same user for transactions requiring maker-checker approval.');
                }
            }

            $requireDistinct = (bool)($approval['require_distinct_approver'] ?? ($actorContext['require_distinct_approver'] ?? ($data['require_distinct_approver'] ?? false)));
            if ($requireDistinct && empty($approverId)) {
                throw new PostingValidationException('Maker-checker policy requires an explicit distinct approver for this transaction.');
            }

            $approvalReason = $reason;
        } elseif (!empty($approval) && is_array($approval)) {
            $approvalReason = !empty($approval['reason']) ? trim((string)$approval['reason']) : null;
            $approverId = $approval['approver_id'] ?? ($approval['approved_by'] ?? ($data['approver_id'] ?? ($actorContext['approver_id'] ?? null)));
            if (!empty($approval['reentered_amount'])) {
                $reenteredAmount = (float) str_replace(',', '', (string)$approval['reentered_amount']);
            }
            if ($makerId !== null && $approverId !== null && (string)$makerId === (string)$approverId) {
                $requireDistinct = (bool)($approval['require_distinct_approver'] ?? ($actorContext['require_distinct_approver'] ?? ($data['require_distinct_approver'] ?? false)));
                if ($requireDistinct) {
                    throw new PostingValidationException('Self-approval prohibited: maker and approver cannot be the same user for transactions requiring maker-checker approval.');
                }
            }
        }

        return [
            'domain' => $domain,
            'normalized_entries' => $normalizedEntries,
            'account_net_amounts' => $accountNetAmounts,
            'total_amount' => $totalDebit,
            'currency' => $domainCurrency,
            'trans_date' => $transDate,
            'trans_date_str' => $transDateStr,
            'reference' => $refToCheck,
            'voucher_type' => $voucherType,
            'maker_id' => $makerId ?? 'system',
            'maker_name' => $makerName,
            'approver_id' => $approverId ? (string)$approverId : null,
            'approval_reason' => $approvalReason,
            'reentered_amount' => $reenteredAmount,
            'source' => $actorContext['source'] ?? ($data['source'] ?? (request() ? (request()->is('api/copilot/*') ? 'copilot' : 'api') : 'system')),
            'correlation_id' => $actorContext['correlation_id'] ?? ($data['correlation_id'] ?? (request() ? request()->header('X-Request-Id', request()->header('X-Correlation-Id', (string)Str::uuid())) : (string)Str::uuid())),
        ];
    }
}
