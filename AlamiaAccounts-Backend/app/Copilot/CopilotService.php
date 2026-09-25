<?php

namespace App\Copilot;

use Alamia360\Actors\Actor;
use Alamia360\Facades\Alamia360;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use AlamiaSoft\AlamiaAccounts\Services\SearchService;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerAccount;
use Carbon\Carbon;

class CopilotService
{
    public function __construct()
    {
        AccountsCopilotBridge::register();
    }

    /**
     * Process a chat prompt from the user.
     */
    public function handleChat(string $prompt, ?string $companyCode = null, ?array $context = []): array
    {
        if ($companyCode) {
            DomainContext::set($companyCode);
        }

        $copilotActor = Alamia360::actors()->find('taliya_copilot')
            ?? Actor::ai('taliya_copilot', 'accounting_copilot')->withCapabilities([
                'lookup_account', 'draft_voucher', 'post_voucher', 'get_financial_report', 'list_situations', 'search_entities'
            ]);

        $promptTrimmed = trim($prompt);
        $promptLower = strtolower($promptTrimmed);

        // 1. Direct Action: Post Confirmed Voucher
        if (!empty($context['action']) && $context['action'] === 'post_voucher' && !empty($context['voucher'])) {
            $voucher = $context['voucher'];
            $result = Alamia360::capabilities()->execute('post_voucher', [
                'reference' => $voucher['reference'] ?? null,
                'description' => $voucher['description'] ?? 'Posted via Taliya Copilot',
                'details' => $voucher['details'] ?? [],
                'date' => $voucher['date'] ?? null,
            ], $copilotActor);

            return [
                'sender' => 'Taliya',
                'intent' => 'post_voucher',
                'message' => "Journal voucher {$result['reference']} has been successfully posted into the general ledger.",
                'data' => $result,
                'card_type' => 'voucher_success',
            ];
        }

        // 2. Direct Action: View Specific Entity from Disambiguation Selection
        if (!empty($context['action']) && $context['action'] === 'view_entity') {
            if (!empty($context['voucher'])) {
                return $this->formatVoucherBrief($context['voucher']);
            }
            if (!empty($context['account'])) {
                return $this->formatAccountBrief($context['account']);
            }
        }

        // 3. Classify Prompt Intent via LLM (Ollama qwen3.5:4b) with graceful fallback
        $classifier = app(IntentClassifierService::class);
        $classification = $classifier->classify($prompt, $context);
        $intent = $classification['intent'] ?? 'UNKNOWN';
        $entity = $classification['entity'] ?? '';
        $party = $classification['party'] ?? '';
        $org = $classification['organization'] ?? '';
        $targetObject = $classification['target_object'] ?? null;
        $reportType = $classification['report_type'] ?? null;

        // 4. Greetings & Conversational Welcome
        if ($intent === 'GREETING') {
            $userGreeting = trim(preg_replace('/^(hi|hello|hey|salam|assalam|good morning|good afternoon|good evening)\s*/i', '', $prompt), " !?,.");
            $namePrefix = !empty($userGreeting) ? " {$userGreeting}" : "";

            return [
                'sender' => 'Taliya',
                'intent' => 'greeting',
                'message' => "Hello{$namePrefix}! 👋 I am **Taliya**, your Alamia Accounts AI Copilot.\n\nI can help you manage your books, look up accounts, prepare vouchers, and analyze financial reports. How can I assist you today?",
                'data' => null,
                'card_type' => 'greeting',
                'actions' => [
                    ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                    ['label' => '🏦 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
                    ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                ]
            ];
        }

        // 5. Help & General Guidance
        if ($intent === 'HELP') {
            return [
                'sender' => 'Taliya',
                'intent' => 'general_guidance',
                'message' => "I am **Taliya**, your AI Accounting Copilot backed by Alamia 360.\n\nHere are some of the things you can ask me:\n" .
                    "• **Find Transactions**: *\"Transaction with Mr. Ali Raza of Izoc Ltd\"*\n" .
                    "• **Inquire Vouchers**: *\"Tell me about voucher OB-2026-001\"*\n" .
                    "• **Account Balances**: *\"What is the balance of Meezan Bank?\"* or *\"Account 1130\"*\n" .
                    "• **Drafting Vouchers**: *\"Paid Rs. 25,000 for office rent via Meezan Bank\"*\n" .
                    "• **Financial Statements**: *\"Show Trial Balance\"*, *\"View Profit & Loss\"*\n" .
                    "• **Audit & Alerts**: *\"Check situations\"* or *\"Any ledger alerts?\"*",
                'data' => null,
                'card_type' => 'help',
                'actions' => [
                    ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                    ['label' => '🏦 Meezan Bank Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'What is the balance of Meezan Bank?']],
                ]
            ];
        }

        // 6. Financial Reports Query
        if ($intent === 'INQUIRE_REPORT' || str_contains($promptLower, 'trial balance') || str_contains($promptLower, 'tb') || str_contains($promptLower, 'profit') || str_contains($promptLower, 'balance sheet')) {
            $effectiveReportType = $reportType ?: (
                (str_contains($promptLower, 'profit') || str_contains($promptLower, 'loss') || str_contains($promptLower, 'p&l')) ? 'profit-loss' :
                (str_contains($promptLower, 'balance sheet') ? 'balance-sheet' : 'trial-balance')
            );

            if ($effectiveReportType === 'trial-balance') {
                $result = Alamia360::capabilities()->execute('get_financial_report', [
                    'report_type' => 'trial-balance',
                ], $copilotActor);

                $data = $result['data'] ?? [];
                $totalDebit = $data['total_debit'] ?? $data['totals']['debit'] ?? 0;
                $totalCredit = $data['total_credit'] ?? $data['totals']['credit'] ?? 0;
                $isBalanced = ($totalDebit == $totalCredit) && ($totalDebit > 0);

                return [
                    'sender' => 'Taliya',
                    'intent' => 'report_trial_balance',
                    'message' => "Here is the Trial Balance summary as of today. " . 
                        ($isBalanced ? "The books are in balance with total debits matching credits." : "Review total balances below."),
                    'data' => [
                        'type' => 'trial-balance',
                        'total_debit' => $totalDebit,
                        'total_credit' => $totalCredit,
                        'is_balanced' => $isBalanced,
                        'accounts_count' => count($data['accounts'] ?? $data['rows'] ?? []),
                        'raw' => $data,
                    ],
                    'card_type' => 'financial_report',
                ];
            }

            if ($effectiveReportType === 'profit-loss') {
                $result = Alamia360::capabilities()->execute('get_financial_report', [
                    'report_type' => 'profit-loss',
                ], $copilotActor);

                return [
                    'sender' => 'Taliya',
                    'intent' => 'report_profit_loss',
                    'message' => "Here is the Profit & Loss statement for the current period.",
                    'data' => [
                        'type' => 'profit-loss',
                        'raw' => $result['data'] ?? [],
                    ],
                    'card_type' => 'financial_report',
                ];
            }

            if ($effectiveReportType === 'balance-sheet') {
                $result = Alamia360::capabilities()->execute('get_financial_report', [
                    'report_type' => 'balance-sheet',
                ], $copilotActor);

                return [
                    'sender' => 'Taliya',
                    'intent' => 'report_balance_sheet',
                    'message' => "Here is the Balance Sheet as of today.",
                    'data' => [
                        'type' => 'balance-sheet',
                        'raw' => $result['data'] ?? [],
                    ],
                    'card_type' => 'financial_report',
                ];
            }
        }

        // 7. Situations / Alerts Query
        if ($intent === 'LIST_SITUATIONS' || str_contains($promptLower, 'situation') || str_contains($promptLower, 'alert') || str_contains($promptLower, 'warning') || str_contains($promptLower, 'anomal')) {
            $result = Alamia360::capabilities()->execute('list_situations', [], $copilotActor);
            $count = $result['count'] ?? 0;

            return [
                'sender' => 'Taliya',
                'intent' => 'list_situations',
                'message' => $count > 0 
                    ? "Found {$count} operational situation(s) requiring attention." 
                    : "All clear! There are currently no unresolved operational situations.",
                'data' => $result,
                'card_type' => 'situations_list',
            ];
        }

        // 8. Find Transaction / Voucher by Party, Contact, or Organization
        if ($intent === 'FIND_TRANSACTION' || (!empty($party) && ($targetObject === 'voucher' || str_contains($promptLower, 'transaction') || str_contains($promptLower, 'voucher')))) {
            $searchService = app(SearchService::class);
            $vouchers = $searchService->searchTransactionsByParty([
                'party' => $party,
                'organization' => $org,
            ]);

            $partyDisplay = !empty($party) ? trim($party) : (!empty($org) ? $org : $prompt);
            $orgSuffix = !empty($org) && stripos($partyDisplay, $org) === false ? " of **{$org}**" : "";

            if (count($vouchers) === 1) {
                return $this->formatVoucherBrief($vouchers[0]);
            }

            if (count($vouchers) > 1) {
                return $this->formatDisambiguation("{$partyDisplay}{$orgSuffix}", $vouchers, []);
            }

            // Zero matching transactions found - DO NOT do blind fuzzy account lookup
            $activeCompany = DomainContext::get() ?: 'Active Company';
            return [
                'sender' => 'Taliya',
                'intent' => 'transaction_not_found',
                'message' => "I searched for transactions involving **{$partyDisplay}**{$orgSuffix} across vouchers, line item memos, and sub-ledgers, but found no matching records in company [{$activeCompany}].\n\n" .
                    "Would you like to draft a new transaction for {$partyDisplay} or search the Daybook?",
                'data' => [
                    'party' => $party,
                    'organization' => $org,
                ],
                'card_type' => 'not_found',
                'actions' => [
                    [
                        'label' => "📝 Draft Voucher for " . ($party ?: $org ?: "Party"),
                        'action' => 'draft_prompt',
                        'payload' => [
                            'prompt' => "Paid Rs. 10,000 to " . ($party ?: $org) . ($org && stripos($party, $org) === false ? " ({$org})" : "")
                        ]
                    ],
                    [
                        'label' => '📄 View Daybook',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'daybook']
                    ],
                    [
                        'label' => '📖 Chart of Accounts',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'coa']
                    ],
                ]
            ];
        }

        // 9. Explicit Voucher Inquiries with Reference Pattern (e.g. "OB-2026-001")
        if ($intent === 'INQUIRE_VOUCHER' || preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $refMatch)) {
            $searchService = app(SearchService::class);
            $searchKey = $classification['reference'] ?? ($refMatch[0] ?? $entity ?? $prompt);
            $vouchers = $searchService->searchVouchers($searchKey);

            if (!empty($vouchers)) {
                if (count($vouchers) === 1) {
                    return $this->formatVoucherBrief($vouchers[0]);
                }
                return $this->formatDisambiguation($searchKey, $vouchers, []);
            }
        }

        // 10. Explicit Account Inquiries & Balances (e.g. "What is the balance of Meezan Bank?", "Account 1130", "Cash in Hand")
        $accountCandidate = $classification['account'] ?? '';
        if (
            $intent === 'INQUIRE_ACCOUNT' ||
            (!empty($accountCandidate) && ($targetObject === 'account' || str_contains($promptLower, 'balance') || str_contains($promptLower, 'account'))) ||
            str_starts_with($promptLower, 'balance') ||
            str_contains($promptLower, 'balance of') ||
            str_contains($promptLower, 'balance in') ||
            preg_match('/^account\s+\d{4}$/i', $promptTrimmed)
        ) {
            $searchService = app(SearchService::class);
            $accQuery = !empty($accountCandidate) ? $accountCandidate : trim(preg_replace('/^(what is the balance of|what is the balance in|what is the balance for|what is the balance|what is in|how much is in|how much in|balance of|balance in|balance for|balance|tell me about account|tell me about|show me account|show me|details of account|details of|account)\s+(the\s+|account\s+)?/i', '', $prompt), " ?.'\"");

            $accounts = $searchService->searchAccounts($accQuery);

            // Check exact 4-digit code match
            if (preg_match('/\b(\d{4})\b/', $accQuery, $cm)) {
                $exactAcc = collect($accounts)->firstWhere('code', $cm[1]);
                if ($exactAcc) {
                    return $this->formatAccountBrief($exactAcc);
                }
            }

            // Check exact account name match
            $exactName = collect($accounts)->first(function ($a) use ($accQuery) {
                return strcasecmp($a['name'] ?? '', $accQuery) === 0;
            });
            if ($exactName) {
                return $this->formatAccountBrief($exactName);
            }

            if (count($accounts) === 1) {
                return $this->formatAccountBrief($accounts[0]);
            }

            if (count($accounts) > 1) {
                return $this->formatDisambiguation($accQuery, [], $accounts);
            }

            // 0 accounts found
            $activeCompany = DomainContext::get() ?: 'Active Company';
            return [
                'sender' => 'Taliya',
                'intent' => 'account_not_found',
                'message' => "I couldn't find any ledger account matching '**{$accQuery}**' in company [{$activeCompany}].\n\n" .
                    "• Try searching by exact 4-digit code (e.g., `1110`, `1130`, `5100`)\n" .
                    "• Or open the Chart of Accounts to view all available accounts:",
                'data' => ['query' => $accQuery],
                'card_type' => 'not_found',
                'actions' => [
                    ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
                    ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                ]
            ];
        }

        // 11. Conversational Voucher Drafting
        if (
            $intent === 'DRAFT_VOUCHER' ||
            ((str_contains($promptLower, 'paid') ||
              str_contains($promptLower, 'received') ||
              str_contains($promptLower, 'transfer') ||
              str_contains($promptLower, 'draft voucher')) &&
             preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $prompt))
        ) {
            return $this->parseAndDraftVoucher($prompt, $copilotActor);
        }

        // 12. General Entity Search fallback (Multi-entity disambiguation)
        $cleanQuery = !empty($entity) ? $entity : preg_replace('/^(tell me about|what is|how much is in|how much in|who is|show me|find|lookup|search for|search|details of|details for|info about|information about)\s+(the\s+|account\s+|voucher\s+)?/i', '', $prompt);
        $cleanQuery = trim($cleanQuery, " ?:.'\"");

        if (!empty($cleanQuery)) {
            $searchService = app(SearchService::class);
            $searchResult = $searchService->globalSearch($cleanQuery);
            $vouchers = $searchResult['vouchers'] ?? [];
            $accounts = $searchResult['accounts'] ?? [];

            // Search users
            $users = \DB::table('users')
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('name', 'like', "%{$cleanQuery}%")
                      ->orWhere('email', 'like', "%{$cleanQuery}%");
                })
                ->get()
                ->toArray();

            $totalMatches = count($vouchers) + count($accounts) + count($users);

            // Exactly 1 Voucher Match (and 0 accounts/users)
            if (count($vouchers) === 1 && count($accounts) === 0 && count($users) === 0) {
                return $this->formatVoucherBrief($vouchers[0]);
            }

            // Exactly 1 Account Match (and 0 vouchers/users)
            if (count($accounts) === 1 && count($vouchers) === 0 && count($users) === 0) {
                return $this->formatAccountBrief($accounts[0]);
            }

            // Multiple Matches (Disambiguation required)
            if ($totalMatches > 1) {
                return $this->formatDisambiguation($cleanQuery, $vouchers, $accounts, $users);
            }

            return [
                'sender' => 'Taliya',
                'intent' => 'entity_not_found',
                'message' => "I couldn't find any vouchers, accounts, or contacts matching '**{$cleanQuery}**'.\n\n" .
                    "• Try searching by exact account code (e.g., `1110`, `1130`, `5100`)\n" .
                    "• Or voucher reference (e.g., `OB-2026-001`, `JV-2026-001`)\n" .
                    "• Or explore accounts and ledger statements below:",
                'data' => ['query' => $cleanQuery],
                'card_type' => 'not_found',
                'actions' => [
                    ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
                    ['label' => '📄 Open Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                    ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                ]
            ];
        }

        // Default Help & Guidance
        return [
            'sender' => 'Taliya',
            'intent' => 'general_guidance',
            'message' => "Hello! I am **Taliya**, your Alamia Accounts AI Copilot. How can I help you with your double-entry accounting today?\n\n" .
                "• **Entity Inquiries**: e.g., *\"Tell me about voucher OB-2026-001\"* or *\"What is the balance of Meezan Bank?\"*\n" .
                "• **Voucher Drafting**: e.g., *\"Paid Rs. 25,000 for office rent via Meezan Bank\"*\n" .
                "• **Account Lookups**: e.g., *\"Find bank accounts\"* or *\"Lookup utility expenses\"*\n" .
                "• **Financial Statements**: e.g., *\"Show Trial Balance\"*, *\"View Profit & Loss\"*\n" .
                "• **Operational Situations**: e.g., *\"Check situations\"* or *\"Any alerts?\"*",
            'data' => null,
            'card_type' => 'help',
            'actions' => [
                ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                ['label' => '🏦 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
                ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
            ]
        ];
    }

    /**
     * Format a rich accounting brief for a Voucher.
     */
    protected function formatVoucherBrief(array $v): array
    {
        $rawLines = $v['lineItems'] ?? $v['line_items'] ?? $v['details'] ?? [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $lines = [];

        foreach ($rawLines as $l) {
            $dr = (float)($l['debit'] ?? 0);
            $cr = (float)($l['credit'] ?? 0);
            $totalDebit += $dr;
            $totalCredit += $cr;
            $lines[] = [
                'account_code' => $l['account_code'] ?? $l['account'] ?? '',
                'account_name' => $l['account_name'] ?? $l['raw_name'] ?? $l['account'] ?? '',
                'debit' => $dr,
                'credit' => $cr,
                'memo' => $l['memo'] ?? $l['description'] ?? '',
            ];
        }

        $isBalanced = abs($totalDebit - $totalCredit) < 0.01;
        $ref = $v['reference'] ?? $v['number'] ?? 'Voucher';
        $type = ucfirst($v['type'] ?? $v['voucher_type'] ?? 'Journal');
        $date = $v['date'] ?? Carbon::now()->toDateString();
        $desc = $v['description'] ?? '';

        return [
            'sender' => 'Taliya',
            'intent' => 'entity_voucher_brief',
            'message' => "Here is a summary for Voucher **{$ref}** ({$type}):\n" .
                "• **Transaction Date**: {$date}\n" .
                "• **Description**: " . (!empty($desc) ? $desc : "Standard posting") . "\n" .
                "• **Total Amount**: Rs. " . number_format($totalDebit, 2) . " (" . ($isBalanced ? "Balanced ✓" : "Unbalanced ⚠️") . ")\n" .
                "• **Posting Legs**: " . count($lines) . " account entries\n\n" .
                "What would you like to do with this voucher?",
            'data' => [
                'type' => 'voucher',
                'reference' => $ref,
                'voucher_type' => $type,
                'date' => $date,
                'description' => $desc,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_balanced' => $isBalanced,
                'line_items' => $lines,
                'raw' => $v,
            ],
            'card_type' => 'voucher_brief',
            'actions' => [
                [
                    'label' => 'View Voucher Details',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'voucher-view', 'type' => 'voucher', 'id' => $ref, 'rawItem' => $v],
                    'variant' => 'default',
                ],
                [
                    'label' => 'Print / Save PDF',
                    'action' => 'print_voucher',
                    'payload' => ['voucher' => $v],
                    'variant' => 'outline',
                ],
                [
                    'label' => 'Reverse Voucher',
                    'action' => 'reverse_voucher',
                    'payload' => ['reference' => $ref],
                    'variant' => 'outline',
                ],
            ],
        ];
    }

    /**
     * Format a rich accounting brief for an Account.
     */
    protected function formatAccountBrief(array $a): array
    {
        $code = $a['code'] ?? '';
        $name = $a['name'] ?? $code;
        $type = $a['type'] ?? (str_starts_with($code, '1') ? 'Asset' : (str_starts_with($code, '2') ? 'Liability' : 'Income'));
        $balance = (float)($a['balance'] ?? 0.0);
        $currency = $a['currency'] ?? 'PKR';
        $isCategory = (bool)($a['category'] ?? false);

        $latestVoucherText = "";
        try {
            $searchService = app(SearchService::class);
            $recentVouchers = $searchService->searchVouchers($code);
            if (!empty($recentVouchers)) {
                $latestV = $recentVouchers[0];
                $latestRef = $latestV['reference'] ?? $latestV['number'] ?? '';
                $latestDate = $latestV['date'] ?? '';
                $latestDesc = $latestV['description'] ?? '';
                if ($latestRef) {
                    $latestVoucherText = "• **Recent Activity**: Posted via **{$latestRef}**" . ($latestDate ? " ({$latestDate})" : "") . (!empty($latestDesc) ? " — {$latestDesc}" : "") . "\n";
                }
            }
        } catch (\Throwable $e) {
            // Ignore optional activity lookup failure
        }

        return [
            'sender' => 'Taliya',
            'intent' => 'entity_account_brief',
            'message' => "Here is the summary for account **[{$code}] {$name}**:\n" .
                "• **Classification**: {$type}" . ($isCategory ? " (Folder Category — Non-Posting)" : " (Leaf Posting Account)") . "\n" .
                "• **Current Balance**: {$currency} " . number_format($balance, 2) . "\n" .
                (!empty($a['parent_code']) ? "• **Parent Folder**: [{$a['parent_code']}]\n" : "") .
                $latestVoucherText .
                "\nHow would you like to proceed with this account?",
            'data' => [
                'type' => 'account',
                'code' => $code,
                'name' => $name,
                'account_type' => $type,
                'balance' => $balance,
                'currency' => $currency,
                'category' => $isCategory,
                'parent_code' => $a['parent_code'] ?? null,
                'raw' => $a,
            ],
            'card_type' => 'account_brief',
            'actions' => [
                [
                    'label' => 'View Ledger Statement',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'ledger-detail-view', 'type' => 'ledger', 'code' => $code, 'name' => $name],
                    'variant' => 'default',
                ],
                [
                    'label' => 'Draft Payment (Credit)',
                    'action' => 'draft_prompt',
                    'payload' => ['prompt' => "Paid Rs. 10,000 from {$name}"],
                    'variant' => 'outline',
                ],
                [
                    'label' => 'Draft Receipt (Debit)',
                    'action' => 'draft_prompt',
                    'payload' => ['prompt' => "Received Rs. 10,000 into {$name}"],
                    'variant' => 'outline',
                ],
            ],
        ];
    }

    /**
     * Format a disambiguation card when multiple entity matches exist.
     */
    protected function formatDisambiguation(string $query, array $vouchers, array $accounts, array $users = []): array
    {
        $options = [];

        foreach (array_slice($vouchers, 0, 3) as $v) {
            $ref = $v['reference'] ?? $v['number'] ?? 'Voucher';
            $options[] = [
                'id' => $ref,
                'type' => 'voucher',
                'label' => "Voucher {$ref}",
                'subtitle' => ($v['type'] ?? 'Journal') . ' • ' . ($v['description'] ?? 'No description') . ' • ' . ($v['date'] ?? ''),
                'raw' => $v,
                'prompt' => "Tell me about voucher {$ref}",
            ];
        }

        foreach (array_slice($accounts, 0, 4) as $a) {
            $code = $a['code'] ?? '';
            $name = $a['name'] ?? $code;
            $options[] = [
                'id' => $code,
                'type' => 'account',
                'label' => "[{$code}] {$name}",
                'subtitle' => ($a['type'] ?? 'Account') . ' • Balance: Rs. ' . number_format($a['balance'] ?? 0, 2),
                'raw' => $a,
                'prompt' => "Tell me about account {$code}",
            ];
        }

        foreach (array_slice($users, 0, 2) as $u) {
            $uArr = (array) $u;
            $name = $uArr['name'] ?? 'User';
            $options[] = [
                'id' => (string) ($uArr['id'] ?? $name),
                'type' => 'user',
                'label' => "User: {$name}",
                'subtitle' => ($uArr['email'] ?? '') . ' (' . ($uArr['role'] ?? 'User') . ')',
                'raw' => $uArr,
                'prompt' => "Tell me about user {$name}",
            ];
        }

        $totalCount = count($options);

        return [
            'sender' => 'Taliya',
            'intent' => 'entity_disambiguation',
            'message' => "I found {$totalCount} records matching '**{$query}**'. Which one would you like to explore?",
            'data' => [
                'query' => $query,
                'options' => $options,
            ],
            'card_type' => 'disambiguation',
        ];
    }

    /**
     * Parse conversational transaction request into balanced voucher draft.
     */
    protected function parseAndDraftVoucher(string $prompt, $actor): array
    {
        // Extract amount (e.g. Rs. 45000, 45,000, 45000)
        preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $prompt, $amountMatch);
        $amount = 0.0;
        if (!empty($amountMatch[1])) {
            $amount = (float) str_replace(',', '', $amountMatch[1]);
        }

        // Match accounts
        $creditCode = null;
        $debitCode = null;

        if (stripos($prompt, 'meezan') !== false) {
            $creditCode = '1130'; // Meezan Bank
        } elseif (stripos($prompt, 'alfalah') !== false) {
            $creditCode = '1135'; // Bank Alfalah
        } elseif (stripos($prompt, 'bank') !== false) {
            $creditCode = '1130';
        } elseif (stripos($prompt, 'cash') !== false) {
            $creditCode = '1110'; // Cash in Hand
        }

        if (stripos($prompt, 'office') !== false || stripos($prompt, 'supplies') !== false || stripos($prompt, 'stationery') !== false) {
            $debitCode = '4600'; // Office Supplies
        } elseif (stripos($prompt, 'rent') !== false) {
            $debitCode = '4400'; // Rent Expense
        } elseif (stripos($prompt, 'utilit') !== false || stripos($prompt, 'electric') !== false || stripos($prompt, 'bill') !== false) {
            $debitCode = '4500'; // Utilities Expense
        } elseif (stripos($prompt, 'salar') !== false || stripos($prompt, 'wage') !== false) {
            $debitCode = '4300'; // Salaries & Wages
        } elseif (stripos($prompt, 'sales') !== false || stripos($prompt, 'revenue') !== false) {
            $creditCode = '3100'; // Sales Revenue
            $debitCode = $debitCode ?? '1130';
        }

        // If received funds, flip default
        if (stripos($prompt, 'received') !== false || stripos($prompt, 'customer') !== false) {
            $temp = $debitCode;
            $debitCode = $creditCode ?? '1130';
            $creditCode = $temp ?? '3100';
        }

        // Fallback safe leaf accounts
        $creditCode = $creditCode ?? '1130';
        $debitCode = $debitCode ?? '4600';

        $draft = Alamia360::capabilities()->execute('draft_voucher', [
            'type' => 'journal',
            'description' => $prompt,
            'details' => [
                [
                    'account_code' => $debitCode,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => $prompt,
                ],
                [
                    'account_code' => $creditCode,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => $prompt,
                ],
            ],
        ], $actor);

        return [
            'sender' => 'Taliya',
            'intent' => 'draft_voucher',
            'message' => "I have prepared a draft journal voucher based on your request. Please review the details below before posting:",
            'data' => $draft,
            'card_type' => 'voucher_draft',
        ];
    }
}
