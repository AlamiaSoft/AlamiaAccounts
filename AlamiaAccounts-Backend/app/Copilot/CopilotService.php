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

        $promptLower = strtolower(trim($prompt));

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

        // 3. Financial Reports Query
        if (str_contains($promptLower, 'trial balance') || str_contains($promptLower, 'tb')) {
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

        if (str_contains($promptLower, 'profit') || str_contains($promptLower, 'loss') || str_contains($promptLower, 'p&l') || str_contains($promptLower, 'income statement')) {
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

        if (str_contains($promptLower, 'balance sheet')) {
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

        // 4. Situations / Alerts Query
        if (str_contains($promptLower, 'situation') || str_contains($promptLower, 'alert') || str_contains($promptLower, 'warning') || str_contains($promptLower, 'anomal')) {
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

        // 5. Explicit Voucher Inquiries with Reference Pattern (e.g. "OB-2026-001", "JV-20260924-142714")
        if (preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $refMatch)) {
            $searchService = app(SearchService::class);
            $searchKey = $refMatch[0];
            $vouchers = $searchService->searchVouchers($searchKey);

            if (!empty($vouchers)) {
                if (count($vouchers) === 1) {
                    return $this->formatVoucherBrief($vouchers[0]);
                }
                return $this->formatDisambiguation($searchKey, $vouchers, []);
            }
        }

        // 6. Conversational Voucher Drafting (Transaction posting keywords with amounts)
        if (
            (str_contains($promptLower, 'paid') ||
             str_contains($promptLower, 'received') ||
             str_contains($promptLower, 'transfer') ||
             str_contains($promptLower, 'draft voucher')) &&
            preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $prompt)
        ) {
            return $this->parseAndDraftVoucher($prompt, $copilotActor);
        }

        // 7. General Entity Search & Account/Voucher/Contact Inquiries
        if (
            str_contains($promptLower, 'tell me about') ||
            str_contains($promptLower, 'balance of') ||
            str_contains($promptLower, 'balance in') ||
            str_contains($promptLower, 'what is') ||
            str_contains($promptLower, 'how much') ||
            str_contains($promptLower, 'search') ||
            str_contains($promptLower, 'find') ||
            str_contains($promptLower, 'lookup') ||
            str_contains($promptLower, 'account') ||
            str_contains($promptLower, 'voucher') ||
            str_contains($promptLower, 'ledger') ||
            str_contains($promptLower, 'who is')
        ) {
            // Clean inquiry prefixes
            $cleanQuery = preg_replace('/^(tell me about|what is the balance of|what is the balance in|what is the balance|what is|how much is in|how much in|balance of|who is|show me|find|lookup|search for|search|details of|details for|info about|information about)\s+(the\s+|account\s+|voucher\s+)?/i', '', $prompt);
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

                // Prioritize Exact Account Code Match (e.g. "account 1130" or query is 4 digits)
                if (preg_match('/\b(\d{4})\b/', $cleanQuery, $codeMatch) || preg_match('/\baccount\s+(\d{4})\b/i', $prompt, $codeMatch)) {
                    $exactAccount = collect($accounts)->firstWhere('code', $codeMatch[1]);
                    if ($exactAccount) {
                        return $this->formatAccountBrief($exactAccount);
                    }
                }

                // Prioritize Exact Voucher Reference Match when user explicitly asks for voucher
                if (str_contains($promptLower, 'voucher') && !empty($vouchers)) {
                    $exactVoucher = collect($vouchers)->first(function ($v) use ($cleanQuery) {
                        return stripos($v['reference'] ?? '', $cleanQuery) !== false;
                    });
                    if ($exactVoucher) {
                        return $this->formatVoucherBrief($exactVoucher);
                    }
                }

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

                // Zero matches - Fallback to lookup_account capability with partial word search
                $lookupResult = Alamia360::capabilities()->execute('lookup_account', [
                    'query' => $cleanQuery,
                ], $copilotActor);

                $foundAccounts = $lookupResult['accounts'] ?? [];
                if (count($foundAccounts) === 1) {
                    return $this->formatAccountBrief($foundAccounts[0]);
                }
                if (count($foundAccounts) > 1) {
                    return $this->formatDisambiguation($cleanQuery, [], $foundAccounts);
                }

                return [
                    'sender' => 'Taliya',
                    'intent' => 'entity_not_found',
                    'message' => "I couldn't find any vouchers or accounts matching '**{$cleanQuery}**'.\n\n" .
                        "• Try searching by exact account code (e.g., `1110`, `1130`, `5100`)\n" .
                        "• Or voucher reference (e.g., `OB-2026-001`, `JV-2026-001`)\n" .
                        "• Or ask: *\"Show Chart of Accounts\"* or *\"Show Trial Balance\"*",
                    'data' => ['query' => $cleanQuery],
                    'card_type' => 'not_found',
                ];
            }
        }

        // Default Help & Guidance
        return [
            'sender' => 'Taliya',
            'intent' => 'general_guidance',
            'message' => "Hello! I am Taliya, your Alamia Accounts Copilot. I can assist you with:\n" .
                "• **Entity Inquiries**: e.g., *\"Tell me about voucher OB-2026-001\"* or *\"What is the balance of Meezan Bank?\"*\n" .
                "• **Voucher Drafting**: e.g., *\"Paid Rs. 25,000 for office rent via Meezan Bank\"*\n" .
                "• **Account Lookups**: e.g., *\"Find bank accounts\"* or *\"Lookup utility expenses\"*\n" .
                "• **Financial Statements**: e.g., *\"Show Trial Balance\"*, *\"View Profit & Loss\"*\n" .
                "• **Operational Situations**: e.g., *\"Check situations\"* or *\"Any alerts?\"*",
            'data' => null,
            'card_type' => 'help',
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

        return [
            'sender' => 'Taliya',
            'intent' => 'entity_account_brief',
            'message' => "Here is the summary for account **[{$code}] {$name}**:\n" .
                "• **Classification**: {$type}" . ($isCategory ? " (Folder Category — Non-Posting)" : " (Leaf Posting Account)") . "\n" .
                "• **Current Balance**: {$currency} " . number_format($balance, 2) . "\n" .
                (!empty($a['parent_code']) ? "• **Parent Folder**: [{$a['parent_code']}]\n" : "") .
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
            'description' => $prompt,
            'amount' => $amount,
            'credit_account' => $creditCode,
            'debit_account' => $debitCode,
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
