<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Name;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use AlamiaSoft\AlamiaAccounts\Models\DomainLedgerAccount;
use Illuminate\Support\Collection;
use Exception;

class AccountService
{
    protected LedgerAccountController $accountController;

    public function __construct()
    {
        $this->accountController = new LedgerAccountController();
    }

    /**
     * Get the current domain based on DomainContext.
     */
    protected function getCurrentDomain(): LedgerDomain
    {
        $code = DomainContext::get();
        
        if (!$code) {
            // Fallback to first domain if no context set
            $domain = LedgerDomain::first();
            if ($domain) {
                DomainContext::set($domain->code);
                return $domain;
            }
            throw new Exception('No domain found. Please create a company first.');
        }
        
        $domain = LedgerDomain::where('code', $code)->first();
        
        if (!$domain) {
            throw new Exception("Domain with code {$code} not found");
        }
        
        return $domain;
    }

    /**
     * Create a new Account (Group or Ledger).
     * 
     * CRITICAL: This method wraps Abivia's account creation and associates it with a domain
     * via the domain_ledger_accounts pivot table (NO modifications to Abivia schema).
     * 
     * @param array $data ['code', 'name', 'parent_code' (optional), 'category' (optional), 'debit' (bool)]
     * @param string|null $domainCode Optional domain code. If not provided, uses current domain from DomainContext.
     * @return LedgerAccount
     * @throws Exception
     */
    public function createAccount(array $data, ?string $domainCode = null): LedgerAccount
    {
        // Get the domain
        if ($domainCode) {
            $domain = LedgerDomain::where('code', $domainCode)->firstOrFail();
        } else {
            $domain = $this->getCurrentDomain();
        }

        // Build the Account message
        $message = new Account();
        $message->code = $data['code'];
        
        // Create proper Name objects
        $message->names = [Name::fromArray(['name' => $data['name'], 'language' => 'en'])];
        
        if (isset($data['category'])) {
            $message->category = $data['category'];
        }
        
        // Set debit/credit flags
        if (isset($data['debit'])) {
            $message->debit = $data['debit'];
        }
        if (isset($data['credit'])) {
            $message->credit = $data['credit'];
        }
        
        // Set parent if provided
        if (isset($data['parent_code'])) {
            $message->parent = new EntityRef($data['parent_code']);
        }
        
        // Create account via Abivia controller (for validation and business logic)
        $ledgerAccount = $this->accountController->add($message);
        
        // CRITICAL: Associate account with domain via pivot table
        // This is our custom extension - Abivia doesn't do this
        DomainLedgerAccount::create([
            'domainUuid' => $domain->domainUuid,
            'ledgerUuid' => $ledgerAccount->ledgerUuid,
        ]);
        
        return $ledgerAccount;
    }

    /**
     * Get chart of accounts for the current domain.
     * Uses pivot table to filter accounts by domain.
     */
    public function getChartOfAccounts(?string $parentCode = null): Collection
    {
        $currentDomain = $this->getCurrentDomain();
        
        // Get account UUIDs for this domain via pivot table
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);
        
        // Query accounts that belong to this domain
        $query = LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('code', '!=', ''); // Exclude root account
        
        if ($parentCode) {
            $parent = LedgerAccount::where('code', $parentCode)
                ->whereIn('ledgerUuid', $accountUuids)
                ->first();
            if ($parent) {
                $query->where('parentUuid', $parent->ledgerUuid);
            }
        } else {
            $root = LedgerAccount::where('code', '')->first();
            if ($root) {
                $query->where(function ($q) use ($root) {
                    $q->whereNull('parentUuid')
                      ->orWhere('parentUuid', $root->ledgerUuid);
                });
            } else {
                $query->whereNull('parentUuid');
            }
        }
        
        return $query->with('names')->orderBy('code')->get();
    }

    /**
     * Get a single account by code for the current domain.
     * Uses pivot table to ensure account belongs to domain.
     */
    public function getAccountByCode(string $code): ?LedgerAccount
    {
        $currentDomain = $this->getCurrentDomain();
        
        // Get account UUIDs for this domain
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);
        
        return LedgerAccount::where('code', $code)
            ->whereIn('ledgerUuid', $accountUuids)
            ->with('names')
            ->first();
    }

    /**
     * Get all accounts (including hierarchy) for the current domain.
     */
    public function getAllAccounts(): Collection
    {
        $currentDomain = $this->getCurrentDomain();
        
        // Get account UUIDs for this domain
        $accountUuids = DomainLedgerAccount::getAccountUuidsForDomain($currentDomain->domainUuid);
        
        return LedgerAccount::whereIn('ledgerUuid', $accountUuids)
            ->where('code', '!=', '') // Exclude root account
            ->with('names')
            ->orderBy('code')
            ->get();
    }

    /**
     * Update an account.
     */
    public function updateAccount(string $code, string $name, ?string $parentCode = null, ?string $category = null): LedgerAccount
    {
        $account = $this->getAccountByCode($code);
        if (!$account) {
            throw new Exception("Account {$code} not found in current domain");
        }

        $message = new Account();
        $message->code = $code;
        $message->revision = $account->revisionHash;
        $message->names = [Name::fromArray(['name' => $name, 'language' => 'en'])];
        if ($parentCode !== null) {
            $message->parent = new EntityRef($parentCode);
        }
        if ($category !== null) {
            $message->category = ($category === 'true' || $category === true || $category === 1);
        }

        return $this->accountController->update($message);
    }

    /**
     * Delete an account.
     */
    public function deleteAccount(string $code): bool
    {
        $account = $this->getAccountByCode($code);
        if (!$account) {
            throw new Exception("Account {$code} not found in current domain");
        }

        $currentDomain = $this->getCurrentDomain();

        // Delete from pivot
        DomainLedgerAccount::where('domainUuid', $currentDomain->domainUuid)
            ->where('ledgerUuid', $account->ledgerUuid)
            ->delete();

        // Delete via Abivia
        $message = new Account();
        $message->code = $code;
        $message->revision = $account->revisionHash;
        $this->accountController->delete($message);

        return true;
    }

    /**
     * Get single account formatted for API.
     */
    public function getAccount(string $code): ?array
    {
        $account = $this->getAccountByCode($code);
        if (!$account) {
            return null;
        }

        return [
            'uuid' => $account->ledgerUuid,
            'code' => $account->code,
            'name' => $account->names->first()->name ?? $account->code,
            'category' => (bool)$account->category,
            'debit' => (bool)$account->debit,
            'credit' => (bool)$account->credit,
            'parent_uuid' => $account->parentUuid,
        ];
    }
}
