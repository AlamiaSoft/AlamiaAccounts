<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use AlamiaSoft\AlamiaAccounts\Models\OperationalSale;
use AlamiaSoft\AlamiaAccounts\Models\OperationalSaleItem;
use AlamiaSoft\AlamiaAccounts\Models\AccountingAuditTrail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class SalesIntegrationService
{
    protected VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }



    /**
     * Process an incoming operational sale / POS checkout transaction.
     */
    public function processSale(
        array $payload,
        ?string $companyCode = null,
        ?string $userId = null,
        ?string $userName = null
    ): array {
        $companyCode = $companyCode ?: (DomainContext::get() ?: 'MAIN');
        $domain = \Abivia\Ledger\Models\LedgerDomain::where('code', $companyCode)->first();
        if (!$domain) {
            $companyService = app(CompanyService::class);
            try {
                $domain = $companyService->createCompany($companyCode, ucwords(str_replace('_', ' ', strtolower($companyCode))), [
                    'currency' => 'PKR',
                ]);
            } catch (\Throwable $e) {
                $companyCode = 'MAIN';
            }
        }
        DomainContext::set($companyCode);

        // Auto-normalize flat payload from simple POS / Frontline forms
        if (!isset($payload['line_items']) && (isset($payload['amount']) || isset($payload['customer_name']))) {
            $custName = $payload['customer_name'] ?? 'Walk-in Client';
            $custPassport = $payload['customer_passport'] ?? $payload['passport_number'] ?? null;
            $amt = (float) ($payload['amount'] ?? $payload['total_amount'] ?? 0);
            $paid = (float) ($payload['paid_amount'] ?? $payload['payment_received'] ?? 0);
            $service = $payload['service_type'] ?? 'General Service';

            $payload['customer'] = [
                'name' => $custName,
                'cnic_or_ntn' => $custPassport,
                'subledger_code' => $payload['customer_subledger'] ?? '1200',
            ];
            $payload['line_items'] = [
                [
                    'description' => $payload['description'] ?? "{$service} for {$custName}" . ($custPassport ? " (P.No: {$custPassport})" : ''),
                    'quantity' => 1,
                    'unit_price' => $amt,
                    'revenue_account_code' => $payload['revenue_account'] ?? '3100',
                ]
            ];
            $payload['payments'] = $paid > 0 ? [
                [
                    'method' => $payload['payment_method'] ?? 'cash',
                    'amount' => $paid,
                    'deposit_account_code' => $payload['payment_account'] ?? '1110',
                    'reference_note' => $payload['payment_note'] ?? "Cash received at POS counter",
                ]
            ] : [];
            $payload['workflow'] = [
                'mode' => $payload['workflow_mode'] ?? 'instant_post',
            ];
        }

        // 1. Idempotency Check
        $idempotencyKey = $payload['idempotency_key'] ?? null;
        $clientRefId = $payload['client_reference_id'] ?? null;

        if ($idempotencyKey || $clientRefId) {
            $existing = OperationalSale::with('items')
                ->where('company_code', $companyCode)
                ->where(function ($q) use ($idempotencyKey, $clientRefId) {
                    if ($idempotencyKey) {
                        $q->where('idempotency_key', $idempotencyKey);
                    }
                    if ($clientRefId) {
                        $q->orWhere('client_reference_id', $clientRefId);
                    }
                })
                ->first();

            if ($existing) {
                return $this->formatSaleResponse($existing, true);
            }
        }

        // 2. Validate & Compute Line Items
        $customer = $payload['customer'] ?? [];
        $customerName = trim($customer['name'] ?? 'Walk-in Client');
        $customerPhone = $customer['phone'] ?? null;
        $customerEmail = $customer['email'] ?? null;
        $customerCnic = $customer['cnic_or_ntn'] ?? null;
        $customerSubledger = $customer['subledger_code'] ?? '1200'; // Default Accounts Receivable

        $lineItems = $payload['line_items'] ?? [];
        if (empty($lineItems)) {
            throw new Exception("Sale must contain at least one line item.");
        }

        $grossAmount = 0.00;
        $totalTaxAmount = 0.00;
        $computedItems = [];

        foreach ($lineItems as $item) {
            $qty = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            if ($qty <= 0 || $unitPrice < 0) {
                throw new Exception("Line item quantity and unit price must be positive numbers.");
            }

            $lineTotal = round($qty * $unitPrice, 2);
            $taxRate = (float) ($item['tax_rate_percent'] ?? 0);
            $taxAmount = round($lineTotal * ($taxRate / 100), 2);

            $grossAmount += $lineTotal;
            $totalTaxAmount += $taxAmount;

            $computedItems[] = [
                'description' => trim($item['description'] ?? 'Product/Service Sale'),
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total_price' => $lineTotal,
                'revenue_account_code' => $item['revenue_account_code'] ?? '3100',
                'tax_rate_percent' => $taxRate,
                'tax_amount' => $taxAmount,
                'metadata' => $item['metadata'] ?? null,
            ];
        }

        $discountAmount = (float) ($payload['discount_amount'] ?? 0);
        $netAmount = round($grossAmount + $totalTaxAmount - $discountAmount, 2);

        // 3. Compute Payments
        $payments = $payload['payments'] ?? [];
        $paidAmount = 0.00;
        $paymentDetails = [];

        foreach ($payments as $p) {
            $pAmt = (float) ($p['amount'] ?? 0);
            if ($pAmt > 0) {
                $paidAmount += $pAmt;
                $paymentDetails[] = [
                    'method' => strtolower($p['method'] ?? 'cash'),
                    'amount' => $pAmt,
                    'deposit_account_code' => $p['deposit_account_code'] ?? ($p['method'] === 'bank' ? '1130' : '1110'),
                    'reference_note' => $p['reference_note'] ?? null,
                ];
            }
        }

        $paidAmount = round($paidAmount, 2);
        $balanceDue = round(max(0, $netAmount - $paidAmount), 2);

        $workflowMode = strtolower($payload['workflow']['mode'] ?? 'instant_post');
        $issueDate = $payload['issue_date'] ?? date('Y-m-d');
        $dueDate = $payload['due_date'] ?? $issueDate;

        return DB::transaction(function () use (
            $companyCode,
            $clientRefId,
            $idempotencyKey,
            $userId,
            $userName,
            $customerName,
            $customerPhone,
            $customerEmail,
            $customerCnic,
            $customerSubledger,
            $issueDate,
            $dueDate,
            $workflowMode,
            $grossAmount,
            $totalTaxAmount,
            $discountAmount,
            $netAmount,
            $paidAmount,
            $balanceDue,
            $computedItems,
            $paymentDetails,
            $payload
        ) {
            $salesVoucherRef = null;
            $receiptVoucherRef = null;
            $status = ($workflowMode === 'instant_post') ? 'posted' : 'pending_approval';

            // 4. Double-Entry Posting for Instant Post
            if ($workflowMode === 'instant_post') {
                $salesVoucherRef = $this->generateUniqueVoucherRef('SV', $companyCode);

                // A. Sales Voucher: Dr Accounts Receivable (1140) / Cr Revenue (4100)
                $svEntries = [
                    [
                        'account_code' => $customerSubledger,
                        'debit' => $netAmount,
                        'credit' => 0.00,
                    ],
                ];

                // Group revenue lines by account code
                $revGroups = [];
                foreach ($computedItems as $ci) {
                    $revAcc = $ci['revenue_account_code'] ?: '3100';
                    $revGroups[$revAcc] = ($revGroups[$revAcc] ?? 0.00) + $ci['total_price'];
                }

                foreach ($revGroups as $rAcc => $rTotal) {
                    $svEntries[] = [
                        'account_code' => $rAcc,
                        'debit' => 0.00,
                        'credit' => round($rTotal, 2),
                    ];
                }

                if ($totalTaxAmount > 0) {
                    $svEntries[] = [
                        'account_code' => '2200', // Sales Tax Payable
                        'debit' => 0.00,
                        'credit' => $totalTaxAmount,
                    ];
                }

                if ($discountAmount > 0) {
                    $svEntries[] = [
                        'account_code' => '4200', // Sales Discount Allowed
                        'debit' => $discountAmount,
                        'credit' => 0.00,
                    ];
                }

                $this->voucherService->createJournalEntry([
                    'reference' => $salesVoucherRef,
                    'description' => "Sales Invoice: {$customerName} (" . ($computedItems[0]['description'] ?? 'Services') . ")",
                    'txDate' => $issueDate,
                    'entries' => $svEntries,
                ], $companyCode);

                // B. Receipt Voucher (if payment received): Dr Cash/Bank / Cr Accounts Receivable
                if ($paidAmount > 0) {
                    $receiptVoucherRef = $this->generateUniqueVoucherRef('RV', $companyCode);
                    $rvEntries = [];

                    foreach ($paymentDetails as $pd) {
                        $rvEntries[] = [
                            'account_code' => $pd['deposit_account_code'],
                            'debit' => $pd['amount'],
                            'credit' => 0.00,
                        ];
                    }

                    $rvEntries[] = [
                        'account_code' => $customerSubledger,
                        'debit' => 0.00,
                        'credit' => $paidAmount,
                    ];

                    $this->voucherService->createJournalEntry([
                        'reference' => $receiptVoucherRef,
                        'description' => "Payment Received: {$customerName} against {$salesVoucherRef}",
                        'txDate' => $issueDate,
                        'entries' => $rvEntries,
                    ], $companyCode);
                }
            }

            // 5. Persist Operational Sale Record
            $sale = OperationalSale::create([
                'company_code' => $companyCode,
                'client_reference_id' => $clientRefId,
                'idempotency_key' => $idempotencyKey,
                'created_by_user_id' => $userId,
                'created_by_user_name' => $userName ?: 'Counter Staff',
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
                'customer_cnic_or_ntn' => $customerCnic,
                'customer_subledger_code' => $customerSubledger,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'status' => $status,
                'workflow_mode' => $workflowMode,
                'gross_amount' => $grossAmount,
                'tax_amount' => $totalTaxAmount,
                'discount_amount' => $discountAmount,
                'net_amount' => $netAmount,
                'paid_amount' => $paidAmount,
                'balance_due' => $balanceDue,
                'sales_voucher_ref' => $salesVoucherRef,
                'receipt_voucher_ref' => $receiptVoucherRef,
                'notes' => $payload['workflow']['notes'] ?? null,
                'metadata' => array_merge($payload['metadata'] ?? [], [
                    'payments' => $paymentDetails,
                ]),
            ]);

            foreach ($computedItems as $cItem) {
                $sale->items()->create($cItem);
            }

            // 6. Record Accounting Audit Trail
            if (class_exists(AccountingAuditTrail::class)) {
                $companyService = app(CompanyService::class);
                $company = $companyService->getCompanyByCode($companyCode);
                $domainUuid = $company ? $company->domainUuid : $companyCode;
                AccountingAuditTrail::record(
                    $domainUuid,
                    'INTEGRATION_SALE_POSTED',
                    'operational_sales',
                    (string) $sale->id,
                    [
                        'customer' => $customerName,
                        'amount' => $netAmount,
                        'sales_voucher' => $salesVoucherRef,
                        'receipt_voucher' => $receiptVoucherRef,
                    ],
                    $userId,
                    $userName
                );
            }

            return $this->formatSaleResponse($sale->load('items'));
        });
    }

    /**
     * Approve a staged / pending sale and commit its double-entry vouchers to the ledger.
     */
    public function approveSale(int $saleId, ?string $approverName = null): array
    {
        $sale = OperationalSale::with('items')->findOrFail($saleId);

        if ($sale->status === 'posted') {
            return $this->formatSaleResponse($sale);
        }

        DomainContext::set($sale->company_code);

        return DB::transaction(function () use ($sale, $approverName) {
            $salesVoucherRef = $this->generateUniqueVoucherRef('SV', $sale->company_code);
            $customerSubledger = $sale->customer_subledger_code ?: '1200';

            // Post Sales Voucher
            $svEntries = [
                [
                    'account_code' => $customerSubledger,
                    'debit' => (float) $sale->net_amount,
                    'credit' => 0.00,
                ],
            ];

            foreach ($sale->items as $item) {
                $svEntries[] = [
                    'account_code' => $item->revenue_account_code ?: '4100',
                    'debit' => 0.00,
                    'credit' => (float) $item->total_price,
                ];
            }

            if ($sale->tax_amount > 0) {
                $svEntries[] = [
                    'account_code' => '2200',
                    'debit' => 0.00,
                    'credit' => (float) $sale->tax_amount,
                ];
            }

            $this->voucherService->createJournalEntry([
                'reference' => $salesVoucherRef,
                'description' => "Sales Invoice (Approved): {$sale->customer_name}",
                'txDate' => $sale->issue_date->format('Y-m-d'),
                'entries' => $svEntries,
            ], $sale->company_code);

            // Post Receipt Voucher if paid
            $receiptVoucherRef = null;
            if ($sale->paid_amount > 0) {
                $receiptVoucherRef = $this->generateUniqueVoucherRef('RV', $sale->company_code);
                $payments = $sale->metadata['payments'] ?? [];
                $rvEntries = [];

                if (!empty($payments)) {
                    foreach ($payments as $p) {
                        $rvEntries[] = [
                            'account_code' => $p['deposit_account_code'] ?? '1110',
                            'debit' => (float) $p['amount'],
                            'credit' => 0.00,
                        ];
                    }
                } else {
                    $rvEntries[] = [
                        'account_code' => '1110',
                        'debit' => (float) $sale->paid_amount,
                        'credit' => 0.00,
                    ];
                }

                $rvEntries[] = [
                    'account_code' => $customerSubledger,
                    'debit' => 0.00,
                    'credit' => (float) $sale->paid_amount,
                ];

                $this->voucherService->createJournalEntry([
                    'reference' => $receiptVoucherRef,
                    'description' => "Payment Received: {$sale->customer_name} against {$salesVoucherRef}",
                    'txDate' => $sale->issue_date->format('Y-m-d'),
                    'entries' => $rvEntries,
                ], $sale->company_code);
            }

            $sale->update([
                'status' => 'posted',
                'sales_voucher_ref' => $salesVoucherRef,
                'receipt_voucher_ref' => $receiptVoucherRef,
            ]);

            return $this->formatSaleResponse($sale);
        });
    }

    /**
     * List sales transactions with staff scoping.
     */
    public function listSales(
        array $filters = [],
        ?string $companyCode = null,
        ?string $currentUserId = null,
        bool $isManager = false,
        int $perPage = 25
    ) {
        $companyCode = $companyCode ?: (DomainContext::get() ?: 'MAIN');
        $query = OperationalSale::with('items')
            ->where('company_code', $companyCode)
            ->orderBy('id', 'desc');

        // Staff Data Scoping: Counter staff only see their own sales unless manager requests all
        $scope = $filters['scope'] ?? 'mine';
        if (!$isManager || $scope !== 'all') {
            if ($currentUserId) {
                $query->where('created_by_user_id', $currentUserId);
            }
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $query->where('issue_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->where('issue_date', '<=', $filters['to_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('client_reference_id', 'like', "%{$search}%")
                  ->orWhere('sales_voucher_ref', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Format a standard API response for a sale.
     */
    protected function formatSaleResponse(OperationalSale $sale, bool $isIdempotentReplay = false): array
    {
        $rcpNo = 'RCP-' . str_pad((string)$sale->id, 5, '0', STR_PAD_LEFT);
        $qrPayload = "ALAMIA|{$sale->company_code}|{$sale->sales_voucher_ref}|{$sale->net_amount}|" . $sale->created_at->format('Y-m-d');

        return [
            'sale_id' => 'SALE-' . str_pad((string)$sale->id, 5, '0', STR_PAD_LEFT),
            'sale_number' => 'SALE-' . str_pad((string)$sale->id, 5, '0', STR_PAD_LEFT),
            'id' => $sale->id,
            'status' => $sale->status,
            'client_reference_id' => $sale->client_reference_id,
            'company_code' => $sale->company_code,
            'sales_voucher_ref' => $sale->sales_voucher_ref,
            'receipt_voucher_ref' => $sale->receipt_voucher_ref,
            'net_amount' => (float) $sale->net_amount,
            'paid_amount' => (float) $sale->paid_amount,
            'due_amount' => (float) $sale->balance_due,
            'balance_due' => (float) $sale->balance_due,
            'total_amount' => (float) $sale->net_amount,
            'created_by' => [
                'user_id' => $sale->created_by_user_id,
                'name' => $sale->created_by_user_name,
            ],
            'customer' => [
                'name' => $sale->customer_name,
                'phone' => $sale->customer_phone,
                'email' => $sale->customer_email,
                'cnic_or_ntn' => $sale->customer_cnic_or_ntn,
                'subledger_account' => $sale->customer_subledger_code,
            ],
            'vouchers' => [
                'sales_voucher' => $sale->sales_voucher_ref,
                'receipt_voucher' => $sale->receipt_voucher_ref,
            ],
            'totals' => [
                'gross_amount' => (float) $sale->gross_amount,
                'tax_amount' => (float) $sale->tax_amount,
                'discount_amount' => (float) $sale->discount_amount,
                'net_amount' => (float) $sale->net_amount,
                'paid_amount' => (float) $sale->paid_amount,
                'balance_due' => (float) $sale->balance_due,
            ],
            'items' => $sale->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total_price' => (float) $item->total_price,
                    'metadata' => $item->metadata,
                ];
            })->toArray(),
            'receipt' => [
                'receipt_number' => $rcpNo,
                'print_url' => "/api/v1/receipts/{$sale->id}/print",
                'pdf_url' => "/api/v1/receipts/{$sale->id}/pdf",
                'qr_payload' => $qrPayload,
            ],
            'is_idempotent_replay' => $isIdempotentReplay,
            'created_at' => $sale->created_at?->toIso8601String(),
        ];
    }

    /**
     * Compute end-of-shift cashier cash drawer reconciliation.
     */
    public function reconcileShift(
        string $companyCode,
        ?string $date = null,
        ?string $agentId = null,
        float $actualCashCount = 0.0
    ): array {
        $date = $date ?: Carbon::today()->toDateString();
        
        $query = OperationalSale::where('company_code', $companyCode)
            ->whereDate('created_at', $date);

        if ($agentId) {
            $query->where('created_by_user_id', $agentId);
        }

        $sales = $query->with('items')->get();

        $totalSalesVolume = 0.0;
        $totalCashCollected = 0.0;
        $totalBankOrCardCollected = 0.0;
        $totalCreditOrDue = 0.0;
        $stagedCount = 0;
        $stagedAmount = 0.0;
        $postedCount = 0;
        $postedCashVouchers = [];

        foreach ($sales as $sale) {
            $totalSalesVolume += (float) $sale->net_amount;
            $totalCreditOrDue += (float) $sale->balance_due;

            $paid = (float) $sale->paid_amount;
            if ($paid > 0) {
                $payAccount = $sale->payment_account_code ?: '1110';
                if ($payAccount === '1110' || str_starts_with($payAccount, '111')) {
                    $totalCashCollected += $paid;
                } else {
                    $totalBankOrCardCollected += $paid;
                }
            }

            if ($sale->status === 'staged') {
                $stagedCount++;
                $stagedAmount += (float) $sale->net_amount;
            } elseif ($sale->status === 'posted') {
                $postedCount++;
                if ($sale->receipt_voucher_ref) {
                    $postedCashVouchers[] = [
                        'sale_id' => $sale->id,
                        'client_reference_id' => $sale->client_reference_id,
                        'customer' => $sale->customer_name,
                        'voucher_ref' => $sale->receipt_voucher_ref,
                        'amount' => (float) $sale->paid_amount,
                    ];
                }
            }
        }

        $expectedCashInDrawer = $totalCashCollected;
        $discrepancy = $actualCashCount > 0 ? ($actualCashCount - $expectedCashInDrawer) : 0.0;
        $isBalanced = ($actualCashCount <= 0) || (abs($discrepancy) < 0.01);

        return [
            'company_code' => $companyCode,
            'reconciliation_date' => $date,
            'agent_id' => $agentId ?: 'ALL_CASHIERS',
            'summary' => [
                'total_transactions' => $sales->count(),
                'total_sales_volume' => $totalSalesVolume,
                'total_cash_collected' => $totalCashCollected,
                'total_bank_card_collected' => $totalBankOrCardCollected,
                'total_receivable_due' => $totalCreditOrDue,
                'staged_pending_count' => $stagedCount,
                'staged_pending_amount' => $stagedAmount,
                'posted_committed_count' => $postedCount,
            ],
            'cash_drawer' => [
                'expected_cash' => $expectedCashInDrawer,
                'actual_cash_counted' => $actualCashCount,
                'discrepancy' => $discrepancy,
                'status' => $isBalanced ? 'balanced' : ($discrepancy > 0 ? 'surplus' : 'shortage'),
            ],
            'receipt_vouchers' => $postedCashVouchers,
        ];
    }

    /**
     * Compatibility alias for shift reconciliation.
     */
    public function reconcileCashierShift(
        string $companyCode,
        ?string $agentId = null,
        float $actualCashCount = 0.0,
        ?string $date = null
    ): array {
        return $this->reconcileShift($companyCode, $date, $agentId, $actualCashCount);
    }

    /**
     * Generate a guaranteed collision-free voucher reference code.
     */
    protected function generateUniqueVoucherRef(string $prefix, string $companyCode): string
    {
        $year = date('Y');
        $random = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 4));
        $count = OperationalSale::where('company_code', $companyCode)->count() + 1;
        $num = str_pad((string)$count, 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$num}-{$random}";
    }

    /**
     * Render a clean, printable thermal receipt HTML (supports 58mm and 80mm).
     */
    public function renderThermalReceiptHtml(OperationalSale $sale, string $paperWidth = '80mm'): string
    {
        $companyService = app(\AlamiaSoft\AlamiaAccounts\Services\CompanyService::class);
        $domain = $companyService->getDomain($sale->company_code);
        $companyName = $domain ? ($domain->name ?? $sale->company_code) : $sale->company_code;
        $formattedDate = $sale->created_at ? $sale->created_at->format('d M Y, h:i A') : date('d M Y, h:i A');
        $rcpNo = 'RCP-' . str_pad((string)$sale->id, 5, '0', STR_PAD_LEFT);
        $totalAmt = (float)($sale->net_amount ?? $sale->gross_amount ?? 0.0);
        $paidAmt = (float)($sale->paid_amount ?? 0.0);
        $dueAmt = (float)($sale->balance_due ?? 0.0);
        $taxAmt = (float)($sale->tax_amount ?? 0.0);
        $grossAmt = (float)($sale->gross_amount ?? $totalAmt);

        $viewData = [
            'sale' => $sale,
            'companyName' => $companyName,
            'formattedDate' => $formattedDate,
            'rcpNo' => $rcpNo,
            'totalAmt' => $totalAmt,
            'paidAmt' => $paidAmt,
            'dueAmt' => $dueAmt,
            'taxAmt' => $taxAmt,
            'grossAmt' => $grossAmt,
            'paperWidth' => $paperWidth,
        ];

        if (view()->exists('alamia-accounts::receipts.thermal')) {
            return view('alamia-accounts::receipts.thermal', $viewData)->render();
        }

        return view('receipts.thermal', $viewData)->render();
    }
}
