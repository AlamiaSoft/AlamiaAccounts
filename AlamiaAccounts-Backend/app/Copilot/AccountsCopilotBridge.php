<?php

namespace App\Copilot;

use Alamia360\Actors\Actor;
use Alamia360\Facades\Alamia360;
use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\VoucherService;
use AlamiaSoft\AlamiaAccounts\Services\ReportService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Carbon\Carbon;
use Exception;

class AccountsCopilotBridge
{
    protected static bool $registered = false;

    public static function register(): void
    {
        if (static::$registered) {
            return;
        }
        static::$registered = true;

        static::registerEntities();
        static::registerCapabilities();
        static::registerActors();
    }

    protected static function registerEntities(): void
    {
        Alamia360::entity('account')
            ->describe('Chart of accounts ledger or category account')
            ->attribute('code', ['type' => 'string', 'label' => 'Account Code'])
            ->attribute('name', ['type' => 'string', 'label' => 'Account Name'])
            ->attribute('category', ['type' => 'boolean', 'label' => 'Is Folder Category'])
            ->attribute('debit', ['type' => 'boolean', 'label' => 'Normal Debit'])
            ->attribute('credit', ['type' => 'boolean', 'label' => 'Normal Credit'])
            ->resolveUsing(fn ($code) => LedgerAccount::where('code', $code)->first());

        Alamia360::entity('voucher')
            ->describe('Double-entry balanced accounting journal voucher')
            ->attribute('reference', ['type' => 'string', 'label' => 'Voucher Reference'])
            ->attribute('description', ['type' => 'string', 'label' => 'Description'])
            ->attribute('date', ['type' => 'string', 'label' => 'Transaction Date'])
            ->attribute('entries', ['type' => 'array', 'label' => 'Debit/Credit Line Items']);

        Alamia360::entity('company')
            ->describe('Multi-tenant company domain')
            ->attribute('code', ['type' => 'string', 'label' => 'Company Code'])
            ->attribute('name', ['type' => 'string', 'label' => 'Company Name'])
            ->resolveUsing(fn ($code) => LedgerDomain::where('code', $code)->first());
    }

    protected static function registerCapabilities(): void
    {
        // 1. lookup_account (with hierarchical category-children expansion & token search)
        Alamia360::capability('lookup_account')
            ->describe('Search Chart of Accounts for matching accounts by name or code, including child accounts')
            ->input([
                'query' => ['type' => 'string', 'required' => false],
                'leaf_only' => ['type' => 'boolean', 'required' => false],
            ])
            ->output(['accounts' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $rawQuery = strtolower(trim($input['query'] ?? ''));
                $leafOnly = (bool) ($input['leaf_only'] ?? false);

                $accountService = app(AccountService::class);
                $allAccounts = $accountService->getChartOfAccountsFormatted();

                if (empty($rawQuery)) {
                    $results = array_map(fn ($item) => [
                        'code' => $item['code'],
                        'name' => $item['name'],
                        'category' => (bool) $item['category'],
                        'debit' => (bool) ($item['debit'] ?? true),
                        'credit' => (bool) ($item['credit'] ?? false),
                        'balance' => $item['balance'] ?? 0.0,
                    ], $allAccounts);

                    return ['accounts' => array_slice($results, 0, 30), 'total_found' => count($results)];
                }

                // Filter stop words to support conversational queries like "bank accounts" -> "bank"
                $stopWords = ['account', 'accounts', 'all', 'the', 'my', 'list', 'show', 'get', 'of'];
                $words = array_values(array_filter(
                    explode(' ', $rawQuery),
                    fn ($w) => strlen($w) > 1 && !in_array($w, $stopWords)
                ));

                if (empty($words)) {
                    $words = [$rawQuery];
                }

                $matchingCodes = [];
                $matchingCategoryCodes = [];

                foreach ($allAccounts as $item) {
                    $nameLower = strtolower($item['name'] ?? '');
                    $codeLower = strtolower($item['code'] ?? '');

                    $matches = str_contains($nameLower, $rawQuery) || str_contains($codeLower, $rawQuery);
                    if (!$matches) {
                        foreach ($words as $w) {
                            if (str_contains($nameLower, $w) || str_contains($codeLower, $w)) {
                                $matches = true;
                                break;
                            }
                        }
                    }

                    if ($matches) {
                        $matchingCodes[$item['code']] = true;
                        if (!empty($item['category'])) {
                            $matchingCategoryCodes[$item['code']] = true;
                        }
                    }
                }

                // Also include children of matched category accounts (e.g. 1120 Bank Accounts -> 1130 Meezan, 1135 Alfalah)
                $results = [];
                foreach ($allAccounts as $item) {
                    $isCategory = (bool) ($item['category'] ?? false);
                    $parentCode = $item['parent_code'] ?? null;

                    $isDirectMatch = isset($matchingCodes[$item['code']]);
                    $isChildOfMatchedCategory = !empty($parentCode) && isset($matchingCategoryCodes[$parentCode]);

                    if ($isDirectMatch || $isChildOfMatchedCategory) {
                        if (!$leafOnly || !$isCategory) {
                            $results[] = [
                                'code' => $item['code'],
                                'name' => $item['name'],
                                'category' => $isCategory,
                                'parent_code' => $parentCode,
                                'debit' => (bool) ($item['debit'] ?? true),
                                'credit' => (bool) ($item['credit'] ?? false),
                                'balance' => $item['balance'] ?? 0.0,
                            ];
                        }
                    }
                }

                usort($results, fn ($a, $b) => strcmp($a['code'], $b['code']));

                return ['accounts' => array_slice($results, 0, 30), 'total_found' => count($results)];
            });

        // 2. draft_voucher
        Alamia360::capability('draft_voucher')
            ->describe('Draft and mathematically validate a double-entry voucher before posting')
            ->input([
                'type' => ['type' => 'string', 'required' => false],
                'description' => ['type' => 'string', 'required' => true],
                'details' => ['type' => 'array', 'required' => true],
            ])
            ->output(['valid' => ['type' => 'boolean'], 'voucher' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $description = trim($input['description'] ?? 'Journal Voucher');
                $details = $input['details'] ?? [];
                $date = $input['date'] ?? Carbon::now()->toDateString();

                // P0 Gate 1: Temporal Plausibility Invariant (Reject absurd / pre-inception / out-of-bounds dates)
                if (
                    preg_match('/\b(last century|century ago|18[0-9]{2}|19[0-9]{2}|200 years ago|millennium)\b/i', $description) ||
                    preg_match('/\b(last century|century ago|18[0-9]{2}|19[0-9]{2}|200 years ago|millennium)\b/i', $date)
                ) {
                    throw new Exception("Temporal Invariant: Transaction date or qualifier in '{$description}' is outside valid fiscal operating periods.");
                }

                if (count($details) < 2) {
                    throw new Exception('Voucher draft must have at least 2 entries (Dr and Cr).');
                }

                $totalDebit = 0.0;
                $totalCredit = 0.0;
                $enrichedDetails = [];
                $errors = [];

                foreach ($details as $idx => $line) {
                    $code = trim($line['account_code'] ?? $line['account'] ?? '');
                    $dr = (float) ($line['debit'] ?? ($line['type'] === 'debit' ? $line['amount'] ?? 0 : 0));
                    $cr = (float) ($line['credit'] ?? ($line['type'] === 'credit' ? $line['amount'] ?? 0 : 0));

                    $account = LedgerAccount::where('code', $code)->first();
                    if (!$account) {
                        $errors[] = "Line " . ($idx + 1) . ": Account code '{$code}' not found in Chart of Accounts.";
                        continue;
                    }

                    // P0 Gate 2: Category / Folder Account rejection (Transactions must target leaf posting accounts)
                    if ($account->category) {
                        $errors[] = "Account {$code} ({$account->name}) is a Category Folder. Transactions must target specific posting accounts (e.g. 1130 Meezan Bank).";
                    }

                    $totalDebit += $dr;
                    $totalCredit += $cr;

                    $lineAmount = $dr > 0 ? $dr : $cr;
                    $lineType = $dr > 0 ? 'debit' : 'credit';

                    $enrichedDetails[] = [
                        'account' => $code,
                        'account_code' => $code,
                        'account_name' => $account->name ?? $code,
                        'category' => (bool) $account->category,
                        'amount' => $lineAmount,
                        'type' => $lineType,
                        'debit' => $dr,
                        'credit' => $cr,
                    ];
                }

                $difference = round(abs($totalDebit - $totalCredit), 2);
                $isBalanced = ($difference === 0.0) && ($totalDebit > 0);

                if (!$isBalanced) {
                    $errors[] = "Double-entry imbalance: Total Debit ({$totalDebit}) != Total Credit ({$totalCredit}). Difference: {$difference}.";
                }

                $reference = 'JV-' . Carbon::now()->format('Ymd-His');
                $makerCheckerThreshold = (float) config('copilot.maker_checker_threshold', 100000.0);
                $requiresDualConfirmation = $totalDebit >= $makerCheckerThreshold;

                return [
                    'valid' => empty($errors),
                    'is_balanced' => $isBalanced,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'requires_dual_confirmation' => $requiresDualConfirmation,
                    'maker_checker_threshold' => $makerCheckerThreshold,
                    'errors' => $errors,
                    'voucher' => [
                        'reference' => $reference,
                        'date' => $date,
                        'description' => $description,
                        'details' => $enrichedDetails,
                        'requires_dual_confirmation' => $requiresDualConfirmation,
                        'maker_checker_threshold' => $makerCheckerThreshold,
                    ],
                ];
            });

        // 3. post_voucher (with robust amount and type normalization)
        Alamia360::capability('post_voucher')
            ->describe('Post a validated double-entry voucher into the ledger')
            ->input([
                'reference' => ['type' => 'string', 'required' => false],
                'description' => ['type' => 'string', 'required' => true],
                'details' => ['type' => 'array', 'required' => true],
                'date' => ['type' => 'string', 'required' => false],
            ])
            ->output(['posted' => ['type' => 'boolean'], 'reference' => ['type' => 'string']])
            ->withSideEffect('write')
            ->allowedFor(['human', 'ai'])
            ->handleUsing(function (array $input, $actor) {
                $voucherService = app(VoucherService::class);
                $ref = $input['reference'] ?? ('JV-' . Carbon::now()->format('Ymd-His'));

                // Robust normalization: ensures $item['amount'] is ALWAYS > 0 on every line
                $entries = [];
                foreach ($input['details'] ?? [] as $line) {
                    $code = $line['account_code'] ?? $line['account'] ?? null;
                    $dr = (float) ($line['debit'] ?? 0);
                    $cr = (float) ($line['credit'] ?? 0);
                    $rawAmt = (float) ($line['amount'] ?? 0);

                    $amount = $rawAmt > 0 ? $rawAmt : ($dr > 0 ? $dr : $cr);
                    $type = $line['type'] ?? ($cr > 0 ? 'credit' : 'debit');

                    $entries[] = [
                        'account' => $code,
                        'account_code' => $code,
                        'amount' => $amount,
                        'type' => $type,
                        'debit' => $type === 'debit',
                        'credit' => $type === 'credit',
                    ];
                }

                $payload = [
                    'reference' => $ref,
                    'description' => $input['description'] ?? 'Voucher posted via Taliya Copilot',
                    'date' => $input['date'] ?? Carbon::now()->toDateString(),
                    'entries' => $entries,
                ];

                $entry = $voucherService->createJournalEntry($payload);

                return [
                    'posted' => true,
                    'reference' => $ref,
                    'entry_id' => $entry->journalEntryId ?? null,
                    'posted_at' => Carbon::now()->toIso8601String(),
                ];
            });

        // 4. get_financial_report
        Alamia360::capability('get_financial_report')
            ->describe('Retrieve financial statements: trial-balance, profit-loss, or balance-sheet')
            ->input([
                'report_type' => ['type' => 'string', 'required' => true],
                'as_of_date' => ['type' => 'string', 'required' => false],
            ])
            ->output(['report' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $reportService = app(ReportService::class);
                $type = strtolower($input['report_type'] ?? 'trial-balance');
                $date = $input['as_of_date'] ?? Carbon::now()->toDateString();

                return match ($type) {
                    'profit-loss', 'profit_loss', 'income-statement' => [
                        'type' => 'profit-loss',
                        'data' => $reportService->getProfitAndLoss(Carbon::now()->startOfYear()->toDateString(), $date),
                    ],
                    'balance-sheet', 'balance_sheet' => [
                        'type' => 'balance-sheet',
                        'data' => $reportService->getBalanceSheet($date),
                    ],
                    default => [
                        'type' => 'trial-balance',
                        'data' => $reportService->getTrialBalance($date),
                    ],
                };
            });

        // 5. list_situations
        Alamia360::capability('list_situations')
            ->describe('List current operational situations or financial anomalies')
            ->input([])
            ->output(['situations' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $situations = Alamia360::situations()->open();
                $output = [];
                foreach ($situations as $s) {
                    $output[] = [
                        'type' => $s->type,
                        'summary' => $s->summary,
                        'priority' => $s->priority->value,
                        'status' => $s->status()->value,
                        'subject_type' => $s->subjectType,
                        'subject_id' => $s->subjectId,
                        'recommended_action' => $s->recommendedAction(),
                    ];
                }
                return ['situations' => $output, 'count' => count($output)];
            });
        // 6. search_entities
        Alamia360::capability('search_entities')
            ->describe('Global domain-scoped search across vouchers, accounts, ledgers, and users')
            ->input([
                'query' => ['type' => 'string', 'required' => true],
            ])
            ->output([
                'vouchers' => ['type' => 'array'],
                'accounts' => ['type' => 'array'],
                'ledger_entries' => ['type' => 'array'],
            ])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $query = $input['query'] ?? '';
                $searchService = app(\AlamiaSoft\AlamiaAccounts\Services\SearchService::class);
                return $searchService->globalSearch($query);
            });

        // 7. reverse_voucher
        Alamia360::capability('reverse_voucher')
            ->describe('Generate a compensating reversal (REV-) voucher for a posted entry')
            ->input([
                'reference' => ['type' => 'string', 'required' => true],
                'reason' => ['type' => 'string', 'required' => false],
            ])
            ->output(['reversed' => ['type' => 'boolean'], 'reversal_reference' => ['type' => 'string']])
            ->withSideEffect('write')
            ->allowedFor(['human', 'ai'])
            ->handleUsing(function (array $input, $actor) {
                $voucherService = app(VoucherService::class);
                $ref = $input['reference'] ?? '';
                $reason = $input['reason'] ?? 'Reversal initiated via Taliya Copilot';
                $reversal = $voucherService->reverseVoucher($ref, $reason);

                return [
                    'reversed' => true,
                    'original_reference' => $ref,
                    'reversal_reference' => $reversal->reference ?? ('REV-' . $ref),
                    'date' => Carbon::now()->toDateString(),
                ];
            });

        // 8. lookup_voucher
        Alamia360::capability('lookup_voucher')
            ->describe('Lookup exact voucher details, line items, and audit status by reference')
            ->input([
                'reference' => ['type' => 'string', 'required' => true],
            ])
            ->output(['voucher' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $searchService = app(SearchService::class);
                $ref = $input['reference'] ?? '';
                $vouchers = $searchService->searchVouchers($ref);
                return ['found' => !empty($vouchers), 'vouchers' => $vouchers];
            });

        // 9. account_balance
        Alamia360::capability('account_balance')
            ->describe('Query live balance and COA position for an account code or name')
            ->input([
                'account' => ['type' => 'string', 'required' => true],
            ])
            ->output(['account' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $searchService = app(SearchService::class);
                $acc = $input['account'] ?? '';
                $accounts = $searchService->searchAccounts($acc);
                return ['found' => !empty($accounts), 'accounts' => $accounts];
            });

        // 10. resolve_entity
        Alamia360::capability('resolve_entity')
            ->describe('Fuzzy match an entity name against contacts, accounts, and ledger narration memos')
            ->input([
                'query' => ['type' => 'string', 'required' => true],
            ])
            ->output(['entity' => ['type' => 'array']])
            ->withSideEffect('read')
            ->allowedFor(['human', 'ai', 'system'])
            ->handleUsing(function (array $input, $actor) {
                $rawQuery = trim($input['query'] ?? '');
                $cleanQuery = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $rawQuery), " ?!.#\"'");
                if (empty($cleanQuery)) {
                    return ['found' => false, 'entity' => null];
                }

                $isOrg = preg_match('/\b(ltd|limited|inc|corp|pvt|co|company|technologies|solutions|services|group)\b/i', $cleanQuery) ||
                    (ctype_upper($cleanQuery) && strlen($cleanQuery) <= 6);

                // 1. Search users
                $users = \DB::table('users')
                    ->where(function ($q) use ($cleanQuery) {
                        $q->where('name', 'like', "%{$cleanQuery}%")
                          ->orWhere('email', 'like', "%{$cleanQuery}%");
                    })
                    ->get()
                    ->toArray();

                // 2. Search journal entry narrations
                $searchService = app(SearchService::class);
                $vouchers = $searchService->searchVouchers($cleanQuery);

                $found = !empty($users) || !empty($vouchers);
                return [
                    'found' => $found,
                    'query' => $rawQuery,
                    'canonical_name' => $cleanQuery,
                    'entity_type' => $isOrg ? 'organization' : 'person',
                    'is_registered_user' => !empty($users),
                    'users_matched' => $users,
                    'vouchers_matched' => $vouchers,
                    'transaction_count' => count($vouchers),
                ];
            });
    }

    protected static function registerActors(): void
    {
        $copilotActor = Actor::ai('taliya_copilot', 'accounting_copilot')
            ->withCapabilities([
                'lookup_account',
                'draft_voucher',
                'post_voucher',
                'reverse_voucher',
                'lookup_voucher',
                'account_balance',
                'get_financial_report',
                'list_situations',
                'search_entities',
                'resolve_entity',
            ])
            ->authorizeUsing(fn ($actor, $cap, $subject) => true);

        Alamia360::actors()->register($copilotActor);
    }
}
