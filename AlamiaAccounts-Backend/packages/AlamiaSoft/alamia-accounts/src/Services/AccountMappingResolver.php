<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerAccount;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use Exception;

class AccountMappingResolver
{
    /**
     * Resolve the target debit account code for a business category.
     *
     * @throws Exception if account cannot be resolved or is a non-postable category
     */
    public function resolveExpenseAccount(string $category, string $domainUuid): string
    {
        $normalized = strtolower(trim($category));

        $preferredCode = match ($normalized) {
            'office_rent', 'rent' => '4100',
            'utilities', 'electricity', 'internet' => '4100',
            'salaries', 'salary', 'commissions', 'payroll' => '4200',
            'marketing', 'advertising', 'ads' => '4300',
            'bank_charges', 'bank_fee', 'gateway_fee' => '4400',
            'meals', 'entertainment', 'refreshment', 'refreshments', 'food' => '4600',
            'travel', 'fuel', 'conveyance', 'transport' => '4700',
            default => '4500', // General & Miscellaneous
        };

        return $this->assertPostableAccount($preferredCode, $domainUuid, '4500');
    }

    /**
     * Resolve the credit asset account for a payment method.
     *
     * @throws Exception if account cannot be resolved or is a non-postable category
     */
    public function resolvePaymentSourceAccount(?string $paymentMethod, string $domainUuid): string
    {
        $method = strtolower(trim((string) $paymentMethod));

        $preferredCode = match ($method) {
            'bank_transfer', 'bank', 'cheque', 'online', 'card', 'credit_card' => '1120',
            default => '1110', // Cash in hand / drawer
        };

        return $this->assertPostableAccount($preferredCode, $domainUuid, '1110');
    }

    /**
     * Verify that an account exists for the domain, is active, and is a postable leaf (category == false).
     */
    protected function assertPostableAccount(string $code, string $domainUuid, ?string $fallbackCode = null): string
    {
        $account = $this->findAccountInDomain($code, $domainUuid);

        if ($account && !$account->category && !$account->closed) {
            return $account->code;
        }

        // Try fallback if primary was invalid/category
        if ($fallbackCode && $fallbackCode !== $code) {
            $fallbackAccount = $this->findAccountInDomain($fallbackCode, $domainUuid);
            if ($fallbackAccount && !$fallbackAccount->category && !$fallbackAccount->closed) {
                return $fallbackAccount->code;
            }
        }

        throw new Exception("Account '{$code}' in domain '{$domainUuid}' is either missing, closed, or a non-postable parent category.");
    }

    /**
     * Find LedgerAccount scoped by domain via pivot table.
     */
    protected function findAccountInDomain(string $code, string $domainUuid): ?LedgerAccount
    {
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($domainUuid);

        return LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('code', $code)
            ->first();
    }
}
