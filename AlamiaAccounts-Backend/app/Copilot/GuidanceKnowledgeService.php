<?php

namespace App\Copilot;

class GuidanceKnowledgeService
{
    /**
     * Match a procedural query to standard ERP operational guidance.
     */
    public function getGuidance(string $query, ?array $context = []): array
    {
        $q = strtolower(trim($query));

        // 1. Voucher Correction & Amount Adjustment Workflow
        if (
            preg_match('/\b(fix|correct|change|modify|wrong)\s+(?:a\s+)?(?:voucher|amount|entry|transaction|payment)\b/i', $q) ||
            str_contains($q, 'how do i fix') ||
            str_contains($q, 'how to fix') ||
            str_contains($q, 'how to correct') ||
            str_contains($q, 'how to reverse')
        ) {
            $activeRef = $context['state']['active_voucher']['reference'] ?? '';
            $actions = [
                ['label' => '📄 Open Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
            ];

            if (!empty($activeRef)) {
                $actions[] = ['label' => "👁️ View {$activeRef}", 'action' => 'navigate_page', 'payload' => ['page' => 'voucher-view', 'id' => $activeRef]];
            }

            return [
                'topic' => 'voucher_correction',
                'title' => 'Voucher Correction & Reversal Workflow',
                'summary' => "Under institutional double-entry standards (GAAP/IFRS), posted accounting vouchers are immutable and cannot be edited in place. To correct a posted voucher:",
                'steps' => [
                    "1. **Post Compensating Reversal** (`REV-`): Zeroes out the erroneous entry in the permanent ledger.",
                    "2. **Draft Replacement Entry**: Post a new voucher with the correct amount, accounts, and documentation.",
                ],
                'note' => "You can reverse any posted voucher directly from the **Daybook** screen or ask Taliya to prepare the draft.",
                'actions' => $actions,
            ];
        }

        // 2. Chart of Accounts & New Account Creation
        if (
            preg_match('/\b(add|create|new|setup)\s+(?:a\s+)?(?:bank|cash|expense|income|asset|liability|ledger)?\s*account\b/i', $q) ||
            str_contains($q, 'chart of accounts') ||
            str_contains($q, 'how to add account')
        ) {
            return [
                'topic' => 'account_creation',
                'title' => 'Adding Accounts in Chart of Accounts',
                'summary' => "Alamia Accounts uses a structured 4-digit hierarchical Chart of Accounts:",
                'steps' => [
                    "1. Navigate to **Chart of Accounts** (`COA`).",
                    "2. Select the appropriate Category Folder (e.g., `1120 Bank Accounts` or `6100 Operating Expenses`).",
                    "3. Click **Add Account**, enter the unique 4-digit code (e.g., `1135`), name, and currency.",
                    "4. Save to activate the account for ledger postings.",
                ],
                'note' => "Transactions can only be posted to leaf accounts (not category folders).",
                'actions' => [
                    ['label' => '📖 Open Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa'], 'variant' => 'default'],
                    ['label' => '📊 View Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                ],
            ];
        }

        // 3. Fiscal Periods & Period Locking
        if (
            preg_match('/\b(lock|close|reopen|unlock)\s+(?:a\s+)?(?:period|month|fiscal\s+year)\b/i', $q) ||
            str_contains($q, 'accounting period') ||
            str_contains($q, 'how to close period')
        ) {
            return [
                'topic' => 'period_locking',
                'title' => 'Accounting Periods & Period Lock Management',
                'summary' => "Fiscal periods protect historical financial integrity against retroactive postings:",
                'steps' => [
                    "1. Navigate to **Accounting Periods** in the sidebar.",
                    "2. Locate the desired fiscal month and click **Close Period** to lock postings.",
                    "3. Once closed, ordinary voucher entries dated within that period are blocked.",
                    "4. Reopening a closed period requires documenting a business audit reason.",
                ],
                'note' => "All lock and reopen events are logged in the permanent accounting audit trail.",
                'actions' => [
                    ['label' => '📅 Accounting Periods', 'action' => 'navigate_page', 'payload' => ['page' => 'periods'], 'variant' => 'default'],
                    ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                ],
            ];
        }

        // 4. Opening Balances Setup
        if (
            str_contains($q, 'opening balance') ||
            str_contains($q, 'initial balance') ||
            str_contains($q, 'ob-')
        ) {
            return [
                'topic' => 'opening_balance',
                'title' => 'Compound Opening Balance Position',
                'summary' => "Opening balances establish initial financial positions when onboarding a tenant:",
                'steps' => [
                    "1. Navigate to **Opening Balances** in Settings or Daybook.",
                    "2. Enter opening debit/credit positions across Balance Sheet accounts.",
                    "3. Ensure the batch strictly balances: `Total Debits === Total Credits`.",
                    "4. Allocate any imbalance difference to Capital (`5100`) or Retained Earnings (`5200`).",
                    "5. Post the batch as `OB-YYYY-001`.",
                ],
                'note' => "Only one compound opening balance batch is permitted per tenant domain.",
                'actions' => [
                    ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook'], 'variant' => 'default'],
                    ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
                ],
            ];
        }

        // Default General ERP Guidance
        return [
            'topic' => 'general_erp_guidance',
            'title' => 'Alamia Accounts Operational Guidance',
            'summary' => "Alamia Accounts is an institutional double-entry ERP system:",
            'steps' => [
                "• **Daybook**: View, filter, and reverse chronological journal vouchers.",
                "• **Chart of Accounts**: Manage assets, liabilities, equity, revenue, and expenses.",
                "• **Financial Reports**: Generate Trial Balance, Profit & Loss, and Balance Sheet.",
                "• **AI Copilot (Taliya)**: Inquire accounts, search transactions, and stage draft vouchers with human-in-the-loop review.",
            ],
            'note' => "Ask Taliya specific procedural questions anytime (e.g. *\"How do I reverse a voucher?\"* or *\"How to add a bank account?\"*).",
            'actions' => [
                ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
            ],
        ];
    }
}
