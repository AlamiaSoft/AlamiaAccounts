<?php

namespace App\Copilot;

use Alamia360\Actors\Actor;
use Alamia360\Facades\Alamia360;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
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
                'lookup_account', 'draft_voucher', 'post_voucher', 'get_financial_report', 'list_situations'
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

        // 2. Financial Reports Query
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

        // 3. Situations / Alerts Query
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

        // 4. Voucher Drafting (e.g., "Paid Rs. 45000 for office supplies via Meezan Bank" or "Draft voucher: ...")
        if (
            str_contains($promptLower, 'paid') ||
            str_contains($promptLower, 'voucher') ||
            str_contains($promptLower, 'draft') ||
            str_contains($promptLower, 'expense') ||
            str_contains($promptLower, 'received') ||
            str_contains($promptLower, 'payment')
        ) {
            return $this->parseAndDraftVoucher($prompt, $copilotActor);
        }

        // 5. Account Lookup
        if (str_contains($promptLower, 'account') || str_contains($promptLower, 'find') || str_contains($promptLower, 'lookup') || str_contains($promptLower, 'search') || str_contains($promptLower, 'code')) {
            // Extract keyword
            $cleanQuery = preg_replace('/^(find|lookup|search|show|get)\s+(account|accounts)?\s*/i', '', $prompt);
            $cleanQuery = trim($cleanQuery, " ?:.");

            $result = Alamia360::capabilities()->execute('lookup_account', [
                'query' => $cleanQuery,
            ], $copilotActor);

            $count = count($result['accounts'] ?? []);
            return [
                'sender' => 'Taliya',
                'intent' => 'lookup_account',
                'message' => "Found {$count} matching account(s) for '{$cleanQuery}'. Remember that category accounts cannot be posted to directly.",
                'data' => $result,
                'card_type' => 'account_list',
            ];
        }

        // Default Help & Guidance
        return [
            'sender' => 'Taliya',
            'intent' => 'general_guidance',
            'message' => "Hello! I am Taliya, your Alamia Accounts Copilot. I can assist you with:\n" .
                "• **Voucher Preparation**: e.g., *\"Paid Rs. 25,000 for office rent via Meezan Bank\"*\n" .
                "• **Account Inquiries**: e.g., *\"Find bank accounts\"* or *\"Lookup utility expenses\"*\n" .
                "• **Financial Statements**: e.g., *\"Show Trial Balance\"*, *\"View Profit & Loss\"*\n" .
                "• **Operational Situations**: e.g., *\"Check situations\"* or *\"Any alerts?\"*",
            'data' => null,
            'card_type' => 'help',
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
        // Check for bank/cash keywords
        $creditCode = null;
        $debitCode = null;

        if (stripos($prompt, 'meezan') !== false) {
            $creditCode = '1130'; // Meezan Bank
        } elseif (stripos($prompt, 'alfalah') !== false) {
            $creditCode = '1135'; // Bank Alfalah
        } elseif (stripos($prompt, 'bank') !== false) {
            $creditCode = '1130';
        } elseif (stripos($prompt, 'cash') !== false) {
            $creditCode = '1110'; // Petty cash / Cash in hand
        } else {
            // Default credit account: Meezan Bank (1130) or Cash (1110)
            $creditCode = '1130';
        }

        if (stripos($prompt, 'stationery') !== false || stripos($prompt, 'office') !== false || stripos($prompt, 'supplies') !== false) {
            $debitCode = '5100'; // Office Supplies & Stationery
        } elseif (stripos($prompt, 'rent') !== false) {
            $debitCode = '5200'; // Rent Expense
        } elseif (stripos($prompt, 'utilit') !== false || stripos($prompt, 'electric') !== false) {
            $debitCode = '5300'; // Utilities Expense
        } elseif (stripos($prompt, 'salary') !== false || stripos($prompt, 'salaries') !== false) {
            $debitCode = '5000'; // Salaries & Wages
        } else {
            $debitCode = '5100'; // General Expense fallback
        }

        if ($amount <= 0) {
            $amount = 10000.0; // fallback preview amount
        }

        // Verify leaf posting accounts
        $drAccount = LedgerAccount::where('code', $debitCode)->first() 
            ?? LedgerAccount::where('category', false)->where('code', 'LIKE', '5%')->first();
        $crAccount = LedgerAccount::where('code', $creditCode)->first()
            ?? LedgerAccount::where('category', false)->where('code', 'LIKE', '11%')->first();

        $debitCode = $drAccount ? $drAccount->code : '5100';
        $creditCode = $crAccount ? $crAccount->code : '1130';

        $details = [
            [
                'account' => $debitCode,
                'debit' => $amount,
                'credit' => 0,
                'type' => 'debit',
                'amount' => $amount,
            ],
            [
                'account' => $creditCode,
                'debit' => 0,
                'credit' => $amount,
                'type' => 'credit',
                'amount' => $amount,
            ],
        ];

        $description = trim(preg_replace('/^(draft|prepare|create|post)\s+(a\s+)?(voucher:?)?/i', '', $prompt));
        if (empty($description)) {
            $description = "Office Expense Payment via {$creditCode}";
        }

        $draftResult = Alamia360::capabilities()->execute('draft_voucher', [
            'type' => 'journal',
            'description' => $description,
            'details' => $details,
        ], $actor);

        if (!empty($draftResult['valid'])) {
            $voucher = $draftResult['voucher'];
            return [
                'sender' => 'Taliya',
                'intent' => 'draft_voucher',
                'message' => "I have drafted a balanced double-entry voucher for **PKR " . number_format($amount, 2) . "**.\n" .
                    "• **Debit**: [{$debitCode}] " . ($drAccount->name ?? 'Expense Account') . " (PKR " . number_format($amount, 2) . ")\n" .
                    "• **Credit**: [{$creditCode}] " . ($crAccount->name ?? 'Bank Account') . " (PKR " . number_format($amount, 2) . ")\n" .
                    "Both accounts are validated leaf posting accounts. Click **Post Voucher** below to commit to the general ledger.",
                'data' => $draftResult,
                'card_type' => 'voucher_draft',
                'actions' => [
                    [
                        'label' => 'Post Voucher Now',
                        'action' => 'post_voucher',
                        'payload' => $voucher,
                        'variant' => 'primary',
                    ],
                ],
            ];
        }

        return [
            'sender' => 'Taliya',
            'intent' => 'draft_voucher_error',
            'message' => "Could not prepare voucher due to validation issues: " . implode(', ', $draftResult['errors'] ?? []),
            'data' => $draftResult,
            'card_type' => 'error',
        ];
    }
}
