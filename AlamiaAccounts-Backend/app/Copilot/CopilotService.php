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
     * Process a chat prompt from the user through the capability architecture.
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

        // 1. Direct Actions (Voucher confirmation / Disambiguation item clicks)
        if (!empty($context['action'])) {
            return $this->handleDirectAction($context, $copilotActor);
        }

        // 2. Delegate to Parlant dialogue engine if sidecar is available
        $parlantClient = app(ParlantClient::class);
        $sessionId = $context['session_id'] ?? ($companyCode ?? 'default_session');
        $parlantResponse = $parlantClient->sendMessage($sessionId, $prompt, $companyCode ?? 'MAIN', $context);
        if ($parlantResponse !== null) {
            return $parlantResponse;
        }

        // 2. Extract structured conversational state from recent turns
        $contextService = app(ConversationContextService::class);
        $contextState = $contextService->extractState($context['history'] ?? []);
        $context['state'] = $contextState;

        // 3. Classify natural language into a small semantic capability request
        $classifier = app(IntentClassifierService::class);
        $semantic = $classifier->classify($prompt, $context ?? []);

        // 4. Resolve conversational references (pronouns, deictic follow-ups) using active state
        $semantic = $contextService->resolveReferences($semantic, $contextState, $prompt);

        // 5. Institutional Accounting Safety Policies & Guardrails
        if (!empty($semantic['safety_flag'])) {
            return $this->handleSafetyPolicy($semantic['safety_flag'], $semantic, $prompt);
        }

        // 6. Capability Dispatcher
        return match ($semantic['capability']) {
            'guidance.how_to' => $this->handleGuidanceHowTo($semantic, $prompt, $context ?? []),
            'general.greeting' => $this->handleGreeting($semantic, $prompt),
            'general.help' => $this->handleHelp($semantic),
            'refusal.chitchat' => $this->handleChitChatRefusal($prompt),
            'refusal.tax_advisory' => $this->handleSafetyPolicy('tax_advisory', $semantic, $prompt),
            'refusal.untracked' => $this->handleUntrackedDataRefusal($prompt),
            'alerts.list' => $this->handleAlertsList($copilotActor),
            'report.trial_balance', 'report.profit_loss', 'report.balance_sheet' => $this->handleFinancialReport($semantic['capability'], $copilotActor),
            'voucher.draft' => $this->handleVoucherDraft($semantic, $prompt, $copilotActor),
            'voucher.reverse' => $this->handleVoucherReverse($semantic),
            'voucher.correct_amount' => $this->handleVoucherCorrectAmount($semantic, $prompt, $copilotActor),
            'voucher.lookup' => $this->handleVoucherLookup($semantic, $prompt),
            'account.balance', 'account.lookup', 'account.ledger' => $this->handleAccountQuery($semantic, $prompt),
            'party.lookup' => $this->handlePartyLookup($semantic, $context ?? []),
            'party.transactions', 'transaction.search' => $this->handleTransactionSearch($semantic, $prompt),
            default => $this->handleFallbackSearch($semantic, $prompt),
        };
    }

    /**
     * Handle direct interactive actions triggered from cards.
     */
    protected function handleDirectAction(array $context, $actor): array
    {
        if ($context['action'] === 'post_voucher' && !empty($context['voucher'])) {
            $voucher = $context['voucher'];
            $result = Alamia360::capabilities()->execute('post_voucher', [
                'reference' => $voucher['reference'] ?? null,
                'description' => $voucher['description'] ?? 'Posted via Taliya Copilot',
                'details' => $voucher['details'] ?? [],
                'date' => $voucher['date'] ?? null,
            ], $actor);

            return [
                'sender' => 'Taliya',
                'intent' => 'post_voucher',
                'message' => "Journal voucher {$result['reference']} has been successfully posted into the general ledger.",
                'data' => $result,
                'card_type' => 'voucher_success',
            ];
        }

        if ($context['action'] === 'view_entity') {
            if (!empty($context['voucher'])) {
                return $this->formatVoucherBrief($context['voucher']);
            }
            if (!empty($context['account'])) {
                return $this->formatAccountBrief($context['account']);
            }
        }

        return $this->handleHelp([]);
    }

    /**
     * Enforce institutional accounting invariants and safety policies.
     */
    protected function handleSafetyPolicy(string $policy, array $semantic, string $prompt): array
    {
        $ref = $semantic['reference'] ?? '';
        if (empty($ref) && preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $rm)) {
            $ref = strtoupper($rm[0]);
        }

        if ($policy === 'tax_advisory') {
            return [
                'sender' => 'Taliya',
                'intent' => 'safety_policy_rejection',
                'message' => "🔒 **Policy Refusal (Tax & Regulatory Advisory)**: Taliya is an operational accounting execution assistant and is strictly prohibited from providing tax evasion advice, tax planning strategies, or legal interpretations.\n\nPlease consult a certified chartered accountant (CA / CPA) or licensed tax authority for tax and regulatory guidance.",
                'data' => [
                    'policy' => 'TAX_ADVISORY_PROHIBITED',
                    'query' => $prompt,
                ],
                'card_type' => 'safety_policy',
                'actions' => [
                    ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                    ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                    ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
                ]
            ];
        }

        $searchService = app(SearchService::class);
        $targetVoucher = !empty($ref) ? collect($searchService->searchVouchers($ref))->first() : null;

        if ($policy === 'mutate_narration') {
            $refDisplay = !empty($ref) ? "Voucher **{$ref}**" : "A posted voucher";
            return [
                'sender' => 'Taliya',
                'intent' => 'safety_policy_rejection',
                'message' => "🔒 **Accounting Invariant (Narration Immutability)**: {$refDisplay} is a posted accounting record. Its narration/description cannot be silently deleted or modified in place to preserve complete double-entry audit history.\n\n" .
                    "If the narration was entered incorrectly, I can help you follow the voucher correction/reversal workflow (`REV-`) or review the voucher in Daybook.",
                'data' => [
                    'reference' => $ref,
                    'action' => 'edit_narration',
                    'policy' => 'VOUCHER_DESCRIPTION_IMMUTABILITY',
                    'voucher' => $targetVoucher,
                ],
                'card_type' => 'safety_policy',
                'actions' => [
                    [
                        'label' => 'Reverse in Daybook',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'daybook', 'reference' => $ref],
                        'variant' => 'default',
                    ],
                    [
                        'label' => 'View Voucher Details',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'voucher-view', 'type' => 'voucher', 'id' => $ref, 'rawItem' => $targetVoucher],
                        'variant' => 'outline',
                    ],
                ]
            ];
        }

        if ($policy === 'mutate_ledger') {
            return [
                'sender' => 'Taliya',
                'intent' => 'safety_policy_rejection',
                'message' => "🔒 **Accounting Invariant (Ledger Immutability)**: Posted accounting entries are immutable and cannot be directly overwritten or mutated.\n\n" .
                    "To adjust balances, please post a new adjusting journal voucher (`JV-`) or reverse and re-issue the transaction.",
                'data' => [
                    'reference' => $ref,
                    'policy' => 'LEDGER_ENTRY_IMMUTABILITY',
                ],
                'card_type' => 'safety_policy',
                'actions' => [
                    [
                        'label' => '📄 Open Daybook to Reverse',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'daybook', 'reference' => $ref],
                        'variant' => 'outline',
                    ],
                ]
            ];
        }

        if ($policy === 'implausible_temporal_request' || $policy === 'FISCAL_PERIOD_PROTECTION') {
            return [
                'sender' => 'Taliya',
                'intent' => 'safety_policy_rejection',
                'message' => "🔒 **Accounting Guardrail (Temporal Invariant)**: The specified date or time expression is outside valid fiscal operating periods.\n\nTransactions and ledger records can only be queried or recorded within active or valid historical fiscal accounting periods.",
                'data' => [
                    'policy' => 'FISCAL_PERIOD_PROTECTION',
                    'query' => $prompt,
                ],
                'card_type' => 'safety_policy',
                'actions' => [
                    ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                    ['label' => '📅 Accounting Periods', 'action' => 'navigate_page', 'payload' => ['page' => 'periods']],
                ]
            ];
        }

        if ($policy === 'destructive_account') {
            return [
                'sender' => 'Taliya',
                'intent' => 'safety_policy_rejection',
                'message' => "🔒 **Accounting Guardrail (Prohibited Action)**: Chart of Accounts and general ledger accounts cannot be deleted or purged via AI Copilot.\n\n" .
                    "• Double-entry accounting rules protect accounts with posted history permanently.\n" .
                    "• Unused accounts can be safely archived or managed from the Chart of Accounts interface.",
                'data' => [
                    'policy' => 'CHART_OF_ACCOUNTS_PROTECTION',
                ],
                'card_type' => 'safety_policy',
                'actions' => [
                    [
                        'label' => '📖 Open Chart of Accounts',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'coa'],
                        'variant' => 'default',
                    ],
                ]
            ];
        }

        // destructive_voucher
        $targetRef = !empty($ref) ? $ref : 'POSTED VOUCHERS';
        return [
            'sender' => 'Taliya',
            'intent' => 'safety_policy_rejection',
            'message' => "🔒 **Accounting Invariant (GAAP/IFRS)**: Posted vouchers and ledger records cannot be deleted or purged to preserve permanent double-entry audit history.\n\n" .
                "If a voucher was posted in error, you can create a compensating **Reversal Voucher** (`REV-`) with documented audit reasons.",
            'data' => [
                'reference' => $targetRef,
                'policy' => 'HISTORICAL_LEDGER_IMMUTABILITY',
                'voucher' => $targetVoucher,
            ],
            'card_type' => 'safety_policy',
            'actions' => [
                [
                    'label' => "Reverse in Daybook",
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'daybook', 'reference' => $targetRef],
                    'variant' => 'default',
                ],
                [
                    'label' => '📖 Chart of Accounts',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'coa'],
                    'variant' => 'outline',
                ],
            ]
        ];
    }

    /**
     * Capability: general.greeting
     */
    protected function handleGreeting(array $semantic, string $prompt): array
    {
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

    /**
     * Capability: general.help
     */
    protected function handleHelp(array $semantic): array
    {
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

    /**
     * Capability: guidance.how_to
     * Educational and operational guidance for software features and ERP workflows (Tier 1 Guidance).
     */
    protected function handleGuidanceHowTo(array $semantic, string $prompt, array $context = []): array
    {
        $guidanceService = app(GuidanceKnowledgeService::class);
        $guidance = $guidanceService->getGuidance($prompt, $context);

        $activeRef = $context['state']['active_voucher']['reference'] ?? '';
        $actions = $guidance['actions'] ?? [];

        // Contextual Bridge: If user asks how to fix a voucher and active voucher is in session, offer direct staging CTA
        if (!empty($activeRef) && (str_contains(strtolower($prompt), 'fix') || str_contains(strtolower($prompt), 'correct') || str_contains(strtolower($prompt), 'amount'))) {
            array_unshift($actions, [
                'label' => "📝 Prepare Correction Draft for {$activeRef}",
                'action' => 'draft_prompt',
                'payload' => ['prompt' => "Prepare correction draft for {$activeRef}"],
                'variant' => 'default',
            ]);
        }

        $stepsText = implode("\n", $guidance['steps'] ?? []);
        $message = "### 📘 {$guidance['title']}\n\n{$guidance['summary']}\n\n{$stepsText}\n\n💡 *{$guidance['note']}*";

        return [
            'sender' => 'Taliya',
            'intent' => 'guidance_how_to',
            'message' => $message,
            'data' => [
                'topic' => $guidance['topic'] ?? 'general',
                'query' => $prompt,
                'reference' => $activeRef,
            ],
            'card_type' => 'guidance_how_to',
            'actions' => $actions,
        ];
    }

    /**
     * First-Class Refusal: Non-accounting chit-chat & general knowledge
     */
    protected function handleChitChatRefusal(string $prompt): array
    {
        return [
            'sender' => 'Taliya',
            'intent' => 'out_of_scope_refusal',
            'message' => "I am **Taliya**, an institutional accounting assistant specialized exclusively in **Alamia Accounts** double-entry bookkeeping, ledger statements, vouchers, and financial reports.\n\nI cannot answer general knowledge questions, chit-chat, or non-financial inquiries.\n\nHow can I assist you with your books today?",
            'data' => [
                'type' => 'refusal_chitchat',
                'query' => $prompt,
            ],
            'card_type' => 'out_of_scope',
            'actions' => [
                ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                ['label' => '🏦 Meezan Bank Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'What is the balance of Meezan Bank?']],
                ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
            ]
        ];
    }

    /**
     * First-Class Refusal: Untracked data attributes
     */
    protected function handleUntrackedDataRefusal(string $prompt): array
    {
        return [
            'sender' => 'Taliya',
            'intent' => 'untracked_data_refusal',
            'message' => "🔒 **Data Boundary**: The requested information is not tracked within the general ledger or chart of accounts. Taliya only accesses double-entry financial journals, accounts, fiscal periods, and subledger balances.",
            'data' => [
                'type' => 'refusal_untracked',
                'query' => $prompt,
            ],
            'card_type' => 'out_of_scope',
            'actions' => [
                ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
            ]
        ];
    }

    /**
     * Capability: alerts.list
     */
    protected function handleAlertsList($actor): array
    {
        $result = Alamia360::capabilities()->execute('list_situations', [], $actor);
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

    /**
     * Capability: report.trial_balance | report.profit_loss | report.balance_sheet
     */
    protected function handleFinancialReport(string $capability, $actor): array
    {
        $reportType = match ($capability) {
            'report.profit_loss' => 'profit-loss',
            'report.balance_sheet' => 'balance-sheet',
            default => 'trial-balance',
        };

        if ($reportType === 'trial-balance') {
            $result = Alamia360::capabilities()->execute('get_financial_report', [
                'report_type' => 'trial-balance',
            ], $actor);

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

        if ($reportType === 'profit-loss') {
            $result = Alamia360::capabilities()->execute('get_financial_report', [
                'report_type' => 'profit-loss',
            ], $actor);

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

        $result = Alamia360::capabilities()->execute('get_financial_report', [
            'report_type' => 'balance-sheet',
        ], $actor);

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

    /**
     * Capability: voucher.draft
     */
    protected function handleVoucherDraft(array $semantic, string $prompt, $actor): array
    {
        $promptTrimmed = trim($prompt);
        $promptLower = strtolower($promptTrimmed);

        // 1. Inquiry Interrogative Gating: Questions starting with why/what/who/how or ending with '?' are inquiries, NOT drafts
        $isQuestionInquiry = (bool) preg_match('/^(why|what|who|how|when|where|which|did we|was there)\b/i', $promptTrimmed) ||
            str_ends_with($promptTrimmed, '?');
        if ($isQuestionInquiry) {
            return $this->handleTransactionSearch($semantic, $prompt);
        }

        // 2. Temporal Plausibility Gating: Absurd or out-of-bounds dates trigger safety guardrail
        if (preg_match('/\b(last century|century ago|18[0-9]{2}|19[0-9]{2}|200 years ago|millennium)\b/i', $promptLower)) {
            return [
                'sender' => 'Taliya',
                'intent' => 'safety_policy_rejection',
                'message' => "🔒 **Accounting Guardrail (Temporal Invariant)**: The specified date expression is outside valid fiscal operating periods.\n\nTransactions can only be recorded within active or valid historical fiscal accounting periods.",
                'data' => ['policy' => 'FISCAL_PERIOD_PROTECTION'],
                'card_type' => 'safety_policy',
                'actions' => [
                    ['label' => '📄 View Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                    ['label' => '📅 Accounting Periods', 'action' => 'navigate_page', 'payload' => ['page' => 'periods']],
                ]
            ];
        }

        $amount = (float) ($semantic['amount'] ?? 0);
        if ($amount <= 0 && preg_match('/(?:rs\.?|pkr|\$)?\s*([0-9]+(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/i', $prompt, $amountMatch)) {
            $amount = (float) str_replace(',', '', $amountMatch[1]);
        }

        $creditCode = null;
        $debitCode = null;

        if (stripos($prompt, 'transfer') !== false && preg_match('/from\s+([a-z0-9\s]+?)\s+to\s+([a-z0-9\s]+)/i', $prompt, $tMatch)) {
            $fromStr = strtolower(trim($tMatch[1]));
            $toStr = strtolower(trim($tMatch[2]));
            $creditCode = str_contains($fromStr, 'cash') || $fromStr === '1110' ? '1110' : (str_contains($fromStr, 'alfalah') || $fromStr === '1135' ? '1135' : '1130');
            $debitCode = str_contains($toStr, 'cash') || $toStr === '1110' ? '1110' : (str_contains($toStr, 'alfalah') || $toStr === '1135' ? '1135' : '1130');
        } else {
            if (stripos($prompt, 'meezan') !== false) {
                $creditCode = '1130';
            } elseif (stripos($prompt, 'alfalah') !== false) {
                $creditCode = '1135';
            } elseif (stripos($prompt, 'bank') !== false) {
                $creditCode = '1130';
            } elseif (stripos($prompt, 'cash') !== false) {
                $creditCode = '1110';
            }

            if (stripos($prompt, 'office') !== false || stripos($prompt, 'supplies') !== false || stripos($prompt, 'stationery') !== false) {
                $debitCode = '4600';
            } elseif (stripos($prompt, 'rent') !== false) {
                $debitCode = '4400';
            } elseif (stripos($prompt, 'utilit') !== false || stripos($prompt, 'electric') !== false || stripos($prompt, 'bill') !== false) {
                $debitCode = '4500';
            } elseif (stripos($prompt, 'salar') !== false || stripos($prompt, 'wage') !== false) {
                $debitCode = '4300';
            } elseif (stripos($prompt, 'sales') !== false || stripos($prompt, 'revenue') !== false) {
                $creditCode = '3100';
                $debitCode = $debitCode ?? '1130';
            }

            if (stripos($prompt, 'received') !== false || stripos($prompt, 'customer') !== false) {
                $temp = $debitCode;
                $debitCode = $creditCode ?? '1130';
                $creditCode = $temp ?? '3100';
            }
        }

        $creditCode = $creditCode ?? '1130';
        $debitCode = $debitCode ?? '4600';

        // Resolve real account names from chart of accounts
        $searchService = app(SearchService::class);
        $debitAccounts = $searchService->searchAccounts($debitCode);
        $creditAccounts = $searchService->searchAccounts($creditCode);

        $debitName = !empty($debitAccounts) ? $debitAccounts[0]['name'] : $debitCode;
        $creditName = !empty($creditAccounts) ? $creditAccounts[0]['name'] : $creditCode;

        $makerCheckerThreshold = (float) config('copilot.maker_checker_threshold', 100000.0);
        $requiresDualConfirmation = $amount >= $makerCheckerThreshold;

        $draft = Alamia360::capabilities()->execute('draft_voucher', [
            'type' => 'journal',
            'description' => $prompt,
            'details' => [
                [
                    'account_code' => $debitCode,
                    'account_name' => $debitName,
                    'debit' => $amount,
                    'credit' => 0,
                    'memo' => $prompt,
                ],
                [
                    'account_code' => $creditCode,
                    'account_name' => $creditName,
                    'debit' => 0,
                    'credit' => $amount,
                    'memo' => $prompt,
                ],
            ],
        ], $actor);

        $draft['requires_dual_confirmation'] = $requiresDualConfirmation;
        $draft['maker_checker_threshold'] = $makerCheckerThreshold;
        if (!empty($draft['voucher'])) {
            $draft['voucher']['requires_dual_confirmation'] = $requiresDualConfirmation;
            $draft['voucher']['maker_checker_threshold'] = $makerCheckerThreshold;
        }

        $message = $requiresDualConfirmation
            ? "I have prepared a draft journal voucher for **Rs. " . number_format($amount, 2) . "**.\n\n⚠️ **Maker-Checker Policy**: Transactions of Rs. " . number_format($makerCheckerThreshold, 2) . " or higher require secondary authorization (segregation of duties) before posting. Please review the details below before submitting for approval:"
            : "I have prepared a draft journal voucher based on your request. Please review the details below before posting:";

        return [
            'sender' => 'Taliya',
            'intent' => 'draft_voucher',
            'message' => $message,
            'data' => $draft,
            'card_type' => 'voucher_draft',
        ];
    }

    /**
     * Capability: voucher.reverse
     */
    protected function handleVoucherReverse(array $semantic): array
    {
        $ref = $semantic['reference'] ?? '';
        $searchService = app(SearchService::class);
        $targetVoucher = !empty($ref) ? collect($searchService->searchVouchers($ref))->first() : null;

        return [
            'sender' => 'Taliya',
            'intent' => 'voucher_reversal_confirmation',
            'message' => "Would you like to post a compensating reversal (`REV-`) for Voucher **{$ref}**?\n\nThis will record an offset journal entry and document the audit reason in the permanent ledger history.",
            'data' => [
                'reference' => $ref,
                'action' => 'reverse_voucher',
                'voucher' => $targetVoucher,
            ],
            'card_type' => 'voucher_action',
            'actions' => [
                [
                    'label' => "Confirm Reversal for {$ref}",
                    'action' => 'reverse_voucher',
                    'payload' => ['reference' => $ref],
                    'variant' => 'default',
                ],
                [
                    'label' => 'View in Daybook',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'daybook', 'reference' => $ref],
                    'variant' => 'outline',
                ],
            ]
        ];
    }

    /**
     * Capability: voucher.correct_amount
     * Guided workflow to reverse an erroneous voucher and draft a corrected replacement entry.
     */
    protected function handleVoucherCorrectAmount(array $semantic, string $prompt, $actor): array
    {
        $ref = $semantic['reference'] ?? '';
        $amount = (float) ($semantic['amount'] ?? 0);
        $searchService = app(SearchService::class);
        $targetVoucher = !empty($ref) ? collect($searchService->searchVouchers($ref))->first() : null;

        $makerCheckerThreshold = (float) config('copilot.maker_checker_threshold', 100000.0);
        $origAmount = 0.0;
        $origLines = [];

        if (!empty($targetVoucher)) {
            $origLines = $targetVoucher['lineItems'] ?? ($targetVoucher['line_items'] ?? ($targetVoucher['details'] ?? []));
            foreach ($origLines as $l) {
                $origAmount += (float) ($l['debit'] ?? 0);
            }
            if ($origAmount <= 0) {
                $origAmount = (float) ($targetVoucher['amount'] ?? 0);
            }
        }

        $requiresDualConfirmation = ($amount >= $makerCheckerThreshold) || ($origAmount >= $makerCheckerThreshold);

        // Build replacement draft line items preserving leaf accounts
        $replacementDetails = [];
        if (!empty($origLines)) {
            foreach ($origLines as $line) {
                $dr = (float) ($line['debit'] ?? 0);
                $cr = (float) ($line['credit'] ?? 0);
                $code = $line['account_code'] ?? ($line['account'] ?? '1130');
                $name = $line['account_name'] ?? $code;

                $replacementDetails[] = [
                    'account' => $code,
                    'account_code' => $code,
                    'account_name' => $name,
                    'amount' => $amount,
                    'type' => $dr > 0 ? 'debit' : 'credit',
                    'debit' => $dr > 0 ? $amount : 0,
                    'credit' => $cr > 0 ? $amount : 0,
                ];
            }
        } else {
            // Default placeholder if no prior lines discovered
            $replacementDetails = [
                ['account' => '1200', 'account_code' => '1200', 'account_name' => 'Accounts Receivable', 'amount' => $amount, 'type' => 'debit', 'debit' => $amount, 'credit' => 0],
                ['account' => '4100', 'account_code' => '4100', 'account_name' => 'Sales Revenue', 'amount' => $amount, 'type' => 'credit', 'debit' => 0, 'credit' => $amount],
            ];
        }

        $refDisplay = !empty($ref) ? "**{$ref}**" : "the voucher";
        $origStr = $origAmount > 0 ? " (originally Rs. " . number_format($origAmount, 2) . ")" : "";
        $amountStr = "Rs. " . number_format($amount, 2);

        $makerCheckerNotice = $requiresDualConfirmation
            ? "\n\n⚠️ **Maker-Checker Policy**: Amount ({$amountStr}) meets or exceeds the Rs. " . number_format($makerCheckerThreshold, 2) . " authorization threshold and requires dual approval before posting."
            : "";

        $message = "To correct {$refDisplay}{$origStr} to **{$amountStr}**, we will execute the institutional guided correction workflow:\n\n" .
            "1. **Post Compensating Reversal** (`REV-{$ref}`) to zero out the original entry in the general ledger.\n" .
            "2. **Draft Corrected Replacement Entry** with updated amount (**{$amountStr}**)." .
            $makerCheckerNotice . "\n\n" .
            "Review the proposed correction details below and click confirm to proceed:";

        return [
            'sender' => 'Taliya',
            'intent' => 'voucher_correction_workflow',
            'message' => $message,
            'data' => [
                'reference' => $ref,
                'action' => 'correct_voucher',
                'original_voucher' => $targetVoucher,
                'original_amount' => $origAmount,
                'corrected_amount' => $amount,
                'reversal_reference' => 'REV-' . $ref,
                'replacement_draft' => [
                    'reference' => 'JV-' . Carbon::now()->format('Ymd-His'),
                    'description' => "Corrected replacement for {$ref}",
                    'amount' => $amount,
                    'details' => $replacementDetails,
                    'requires_dual_confirmation' => $requiresDualConfirmation,
                    'maker_checker_threshold' => $makerCheckerThreshold,
                ],
                'requires_dual_confirmation' => $requiresDualConfirmation,
                'maker_checker_threshold' => $makerCheckerThreshold,
            ],
            'card_type' => 'voucher_action',
            'actions' => [
                [
                    'label' => "Confirm Reversal & Draft Replacement ({$amountStr})",
                    'action' => 'execute_correction_workflow',
                    'payload' => [
                        'reference' => $ref,
                        'amount' => $amount,
                    ],
                    'variant' => 'default',
                ],
                [
                    'label' => 'View in Daybook',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'daybook', 'reference' => $ref],
                    'variant' => 'outline',
                ],
                [
                    'label' => 'View Voucher Details',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'voucher-view', 'type' => 'voucher', 'id' => $ref, 'rawItem' => $targetVoucher],
                    'variant' => 'outline',
                ],
            ]
        ];
    }


    /**
     * Capability: voucher.lookup
     */
    protected function handleVoucherLookup(array $semantic, string $prompt): array
    {
        $searchService = app(SearchService::class);
        $ref = $semantic['reference'] ?? '';
        if (empty($ref) && preg_match('/\b(ob|jv|pv|rv|cv|sv|rev)-[0-9a-z-]+\b/i', $prompt, $rm)) {
            $ref = strtoupper($rm[0]);
        }

        $vouchers = $searchService->searchVouchers($ref);
        if (!empty($vouchers)) {
            $voucher = $vouchers[0];
            $reqInfo = $semantic['requested_information'] ?? [];
            if (in_array('created_by', $reqInfo) || in_array('workforce', $reqInfo) || str_contains(strtolower($prompt), 'who worked') || str_contains(strtolower($prompt), 'who created')) {
                $vRef = $voucher['reference'] ?? $ref;
                $desc = $voucher['description'] ?? '';
                $createdBy = $voucher['created_by'] ?? 'System Administrator';
                return [
                    'sender' => 'Taliya',
                    'intent' => 'voucher_audit_brief',
                    'message' => "Voucher **{$vRef}** (*{$desc}*) was created/posted by **{$createdBy}**.\n\nTransaction Details & Line Items:",
                    'data' => $voucher,
                    'card_type' => 'voucher_brief',
                    'actions' => [
                        ['label' => "View {$vRef} in Daybook", 'action' => 'navigate_page', 'payload' => ['page' => 'daybook', 'reference' => $vRef]],
                        ['label' => '📖 Chart of Accounts', 'action' => 'navigate_page', 'payload' => ['page' => 'coa']],
                    ]
                ];
            }

            if (count($vouchers) === 1) {
                return $this->formatVoucherBrief($vouchers[0]);
            }
            return $this->formatDisambiguation($ref, $vouchers, []);
        }

        $targetRef = !empty($ref) ? $ref : $prompt;
        return [
            'sender' => 'Taliya',
            'intent' => 'voucher_not_found',
            'message' => "I couldn't find any voucher matching reference '**{$targetRef}**'.",
            'data' => ['reference' => $targetRef],
            'card_type' => 'not_found',
            'actions' => [
                ['label' => '📄 Open Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
            ]
        ];
    }

    /**
     * Capability: account.balance | account.lookup | account.ledger
     */
    protected function handleAccountQuery(array $semantic, string $prompt): array
    {
        $searchService = app(SearchService::class);
        $accQuery = $semantic['account'] ?? '';
        if (empty($accQuery)) {
            $accQuery = trim(preg_replace('/^(what is the balance of|what is the balance in|what is the balance for|what is the balance|what is in|how much is in|how much in|balance of|balance in|balance for|balance|tell me about account|tell me about|show me account|show me|details of account|details of|account)\s+(the\s+|account\s+)?/i', '', $prompt), " ?.'\"");
        }

        $accounts = $searchService->searchAccounts($accQuery);

        // Exact 4-digit code match
        if (preg_match('/\b(\d{4})\b/', $accQuery, $cm)) {
            $exactAcc = collect($accounts)->firstWhere('code', $cm[1]);
            if ($exactAcc) {
                return $this->formatAccountBrief($exactAcc);
            }
        }

        // Exact name match
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

    /**
     * Capability: party.lookup
     */
    protected function handlePartyLookup(array $semantic, array $context): array
    {
        $searchService = app(SearchService::class);
        $party = $semantic['party'] ?? '';
        $org = $semantic['organization'] ?? '';
        $targetName = !empty($party) ? $party : (!empty($org) ? $org : ($semantic['entity_value'] ?? ''));
        $targetNameClean = trim(preg_replace('/^(this|that|the|a|an)\s+/i', '', $targetName), " ?!.#\"'");

        // Deictic pronoun resolution from conversation history
        if (empty($targetNameClean) || in_array(strtolower($targetNameClean), ['he', 'she', 'him', 'her', 'it', 'them', 'this', 'that', 'this company', 'that company', 'this person'])) {
            if (!empty($context['history'])) {
                foreach (array_reverse($context['history']) as $h) {
                    $htext = $h['text'] ?? '';
                    if (preg_match('/(?:mr\.?|ms\.?|mrs\.?|dr\.?)?\s*([a-z0-9\s]+(?:ltd|pvt|inc|corp|company|ali raza|izoc))/i', $htext, $histMatch)) {
                        $targetNameClean = trim($histMatch[0]);
                        break;
                    }
                }
            }
        }

        $isOrg = ($semantic['entity_type'] ?? '') === 'organization' ||
            preg_match('/\b(ltd|limited|inc|corp|pvt|co|company|technologies|solutions|services|group|holdings|enterprises)\b/i', $targetNameClean) ||
            (ctype_upper($targetNameClean) && strlen($targetNameClean) <= 6);

        $effectiveParty = $isOrg ? '' : $targetNameClean;
        $effectiveOrg = $isOrg ? $targetNameClean : '';

        // Layer 1: Check users
        $users = \DB::table('users')
            ->where(function ($q) use ($targetNameClean) {
                $q->where('name', 'like', "%{$targetNameClean}%")
                  ->orWhere('email', 'like', "%{$targetNameClean}%");
            })
            ->get()
            ->toArray();

        // Layer 2: Search accounting transaction footprint
        $vouchers = $searchService->searchTransactionsByParty([
            'party' => $effectiveParty,
            'organization' => $effectiveOrg,
        ]);

        $activeCompany = DomainContext::get() ?: 'Active Company';

        if (!empty($users) && empty($vouchers)) {
            $u = (array) $users[0];
            $uName = $u['name'] ?? $targetNameClean;
            $uEmail = $u['email'] ?? '';
            $uRole = $u['role'] ?? 'System User / Contact';

            return [
                'sender' => 'Taliya',
                'intent' => 'entity_party_brief',
                'message' => "Here is the contact profile for **{$uName}**:\n" .
                    "• **Type**: Person / Contact\n" .
                    "• **Role**: {$uRole}\n" .
                    (!empty($uEmail) ? "• **Email**: `{$uEmail}`\n" : "") .
                    "• **Accounting Footprint**: No direct accounting transactions recorded yet.",
                'data' => [
                    'type' => 'contact',
                    'entity_type' => 'person',
                    'name' => $uName,
                    'email' => $uEmail,
                    'role' => $uRole,
                    'transactions_count' => 0,
                ],
                'card_type' => 'entity_brief',
                'actions' => [
                    [
                        'label' => "📝 Draft Payment to {$uName}",
                        'action' => 'draft_prompt',
                        'payload' => ['prompt' => "Paid Rs. 10,000 to {$uName}"],
                        'variant' => 'default',
                    ],
                    [
                        'label' => '📄 View Daybook',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'daybook'],
                        'variant' => 'outline',
                    ],
                ]
            ];
        }

        if (!empty($vouchers)) {
            $vCount = count($vouchers);
            $totalVol = 0.0;
            foreach ($vouchers as $v) {
                $rawLines = $v['lineItems'] ?? $v['line_items'] ?? $v['details'] ?? [];
                $vDr = 0.0;
                foreach ($rawLines as $l) {
                    $vDr += (float)($l['debit'] ?? 0);
                }
                $totalVol += $vDr > 0 ? $vDr : (float)($v['amount'] ?? 0);
            }

            $latestV = $vouchers[0];
            $latestRef = $latestV['reference'] ?? $latestV['number'] ?? '';
            $latestDesc = $latestV['description'] ?? '';
            $latestDate = $latestV['date'] ?? '';

            // Derive entity identity from database transaction evidence
            $hasCorporateInRecords = (bool) preg_match('/\b(pvt|ltd|limited|inc|corp|co|company|technologies|solutions|services|project)\b/i', $latestDesc);
            $isOrg = empty($users) ? ($hasCorporateInRecords || ($semantic['entity_type'] ?? '') === 'organization') : false;

            $typeLabel = $isOrg ? "Organization / Client" : "Person / Contact";
            $displayName = $targetNameClean;
            if ($isOrg && preg_match('/\b(' . preg_quote($targetNameClean, '/') . '(?:\s+pvt\s+ltd|\s+ltd|\s+limited|\s+inc)?)\b/i', $latestDesc, $canonicalMatch)) {
                $displayName = $canonicalMatch[1];
            }

            $userAffiliation = "";
            if (!empty($users)) {
                $u = (array) $users[0];
                $userAffiliation = "• **Associated Contact**: {$u['name']} (" . ($u['email'] ?? 'User') . ")\n";
            }

            return [
                'sender' => 'Taliya',
                'intent' => 'entity_party_brief',
                'message' => "**{$displayName}** appears in your accounting records as a **{$typeLabel}** involved in company [{$activeCompany}] transactions.\n\n" .
                    "• **Accounting Footprint**: Found **{$vCount}** related transaction(s) totaling **Rs. " . number_format($totalVol, 2) . "**\n" .
                    "• **Recent Voucher**: **{$latestRef}**" . ($latestDate ? " ({$latestDate})" : "") . (!empty($latestDesc) ? " — *\"{$latestDesc}\"*" : "") . "\n" .
                    $userAffiliation .
                    "\nWould you like to review related transactions or draft a new voucher?",
                'data' => [
                    'type' => 'party',
                    'entity_type' => $isOrg ? 'organization' : 'person',
                    'name' => $displayName,
                    'transactions_count' => $vCount,
                    'total_volume' => $totalVol,
                    'latest_voucher' => $latestV,
                    'vouchers' => $vouchers,
                ],
                'card_type' => 'entity_brief',
                'actions' => [
                    [
                        'label' => "📄 View Voucher {$latestRef}",
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'voucher-view', 'type' => 'voucher', 'id' => $latestRef, 'rawItem' => $latestV],
                        'variant' => 'default',
                    ],
                    [
                        'label' => "📝 Draft Voucher for {$displayName}",
                        'action' => 'draft_prompt',
                        'payload' => ['prompt' => "Paid Rs. 10,000 to {$displayName}"],
                        'variant' => 'outline',
                    ],
                    [
                        'label' => '📄 View Daybook',
                        'action' => 'navigate_page',
                        'payload' => ['page' => 'daybook'],
                        'variant' => 'outline',
                    ],
                ]
            ];
        }

        return [
            'sender' => 'Taliya',
            'intent' => 'entity_not_found',
            'message' => "I couldn't find any contact profile or accounting transaction footprint matching **{$targetNameClean}** in company [{$activeCompany}].\n\n" .
                "• You can draft a new transaction involving {$targetNameClean}\n" .
                "• Or search the Daybook and Chart of Accounts below:",
            'data' => [
                'entity' => $targetNameClean,
                'entity_type' => $isOrg ? 'organization' : 'person',
            ],
            'card_type' => 'not_found',
            'actions' => [
                [
                    'label' => "📝 Draft Voucher for {$targetNameClean}",
                    'action' => 'draft_prompt',
                    'payload' => ['prompt' => "Paid Rs. 10,000 to {$targetNameClean}"],
                    'variant' => 'default',
                ],
                [
                    'label' => '📄 View Daybook',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'daybook'],
                    'variant' => 'outline',
                ],
                [
                    'label' => '📖 Chart of Accounts',
                    'action' => 'navigate_page',
                    'payload' => ['page' => 'coa'],
                    'variant' => 'outline',
                ],
            ]
        ];
    }

    /**
     * Capability: transaction.search | party.transactions
     */
    protected function handleTransactionSearch(array $semantic, string $prompt): array
    {
        $searchService = app(SearchService::class);
        $party = $semantic['party'] ?? '';
        $org = $semantic['organization'] ?? '';
        $direction = $semantic['direction'] ?? null;
        $dateFilter = $semantic['date_filter'] ?? null;
        $requestedInfo = $semantic['requested_information'] ?? [];

        $vouchers = $searchService->searchTransactionsByParty([
            'party' => $party,
            'organization' => $org,
            'direction' => $direction,
            'date_filter' => $dateFilter,
        ]);

        $partyDisplay = !empty($party) ? trim($party) : (!empty($org) ? $org : $prompt);
        $orgSuffix = !empty($org) && stripos($partyDisplay, $org) === false ? " of **{$org}**" : "";

        if (count($vouchers) === 1) {
            $brief = $this->formatVoucherBrief($vouchers[0]);
            // If user specifically asked for "reason" / "why"
            if (in_array('reason', $requestedInfo) || stripos($prompt, 'why') !== false) {
                $v = $vouchers[0];
                $desc = $v['description'] ?? 'Standard transaction posting';
                $ref = $v['reference'] ?? 'Voucher';
                $brief['message'] = "Transaction reason for **{$ref}**: *\"{$desc}\"*.\n\n" . $brief['message'];
            }
            return $brief;
        }

        if (count($vouchers) > 1) {
            return $this->formatDisambiguation("{$partyDisplay}{$orgSuffix}", $vouchers, []);
        }

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

    /**
     * Fallback multi-entity search and disambiguation.
     */
    protected function handleFallbackSearch(array $semantic, string $prompt): array
    {
        $cleanQuery = !empty($semantic['entity_value']) ? $semantic['entity_value'] : preg_replace('/^(tell me about|what is|how much is in|how much in|who is|show me|find|lookup|search for|search|details of|details for|info about|information about)\s+(the\s+|account\s+|voucher\s+)?/i', '', $prompt);
        $cleanQuery = trim($cleanQuery, " ?:.'\"");

        if (!empty($cleanQuery)) {
            $searchService = app(SearchService::class);
            $searchResult = $searchService->globalSearch($cleanQuery);
            $vouchers = $searchResult['vouchers'] ?? [];
            $accounts = $searchResult['accounts'] ?? [];

            $users = \DB::table('users')
                ->where(function ($q) use ($cleanQuery) {
                    $q->where('name', 'like', "%{$cleanQuery}%")
                      ->orWhere('email', 'like', "%{$cleanQuery}%");
                })
                ->get()
                ->toArray();

            $totalMatches = count($vouchers) + count($accounts) + count($users);

            if (count($vouchers) === 1 && count($accounts) === 0 && count($users) === 0) {
                return $this->formatVoucherBrief($vouchers[0]);
            }

            if (count($accounts) === 1 && count($vouchers) === 0 && count($users) === 0) {
                return $this->formatAccountBrief($accounts[0]);
            }

            if ($totalMatches > 1) {
                return $this->formatDisambiguation($cleanQuery, $vouchers, $accounts, $users);
            }

            $confidenceThreshold = (float) config('copilot.confidence_threshold', 0.70);
            $confidence = (float) ($semantic['confidence'] ?? 0.50);

            if ($confidence < $confidenceThreshold) {
                return [
                    'sender' => 'Taliya',
                    'intent' => 'out_of_scope_refusal',
                    'message' => "I am **Taliya**, an institutional accounting assistant for **Alamia Accounts**.\n\nI couldn't match your request to a supported accounting operation (accounts, vouchers, ledgers, or financial reports). I can only execute defined accounting workflows in your capability catalog.\n\nHow can I help with your books today?",
                    'data' => [
                        'query' => $prompt,
                        'confidence' => $confidence,
                        'capability' => $semantic['capability'] ?? 'unknown',
                    ],
                    'card_type' => 'out_of_scope',
                    'actions' => [
                        ['label' => '📊 Trial Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'Show Trial Balance summary']],
                        ['label' => '🏦 Meezan Bank Balance', 'action' => 'draft_prompt', 'payload' => ['prompt' => 'What is the balance of Meezan Bank?']],
                        ['label' => '📄 Daybook', 'action' => 'navigate_page', 'payload' => ['page' => 'daybook']],
                    ]
                ];
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

        return $this->handleHelp($semantic);
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
}
