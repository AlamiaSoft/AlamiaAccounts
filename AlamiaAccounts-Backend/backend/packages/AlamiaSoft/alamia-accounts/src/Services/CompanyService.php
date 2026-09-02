<?php

namespace AlamiaSoft\AlamiaAccounts\Services;

use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Http\Controllers\LedgerDomainController;
use Abivia\Ledger\Messages\Domain;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Exception;

class CompanyService
{
    protected LedgerDomainController $domainController;

    public function __construct()
    {
        $this->domainController = new LedgerDomainController();
    }

    /**
     * Create a new company (separate legal entity).
     * Use this for managing multiple client companies or separate business entities.
     * 
     * @param string $code Unique company code (e.g., 'CLIENT_A', 'ACME_CORP')
     * @param string $name Company name
     * @param array $config ['currency' => 'USD', etc.]
     * @return LedgerDomain
     */
    public function createCompany(string $code, string $name, array $config = []): LedgerDomain
    {
        return $this->createDomain($code, $name, 'company', null, $config);
    }

    /**
     * Create a department within a company.
     * 
     * @param string $companyCode Parent company code
     * @param string $code Department code (e.g., 'SALES', 'MARKETING')
     * @param string $name Department name
     * @param array $config
     * @return LedgerDomain
     */
    public function createDepartment(string $companyCode, string $code, string $name, array $config = []): LedgerDomain
    {
        $fullCode = $companyCode . '_' . $code;
        return $this->createDomain($fullCode, $name, 'department', $companyCode, $config);
    }

    /**
     * Create a branch/location within a company.
     * 
     * @param string $companyCode Parent company code
     * @param string $code Branch code (e.g., 'HEAD_OFFICE', 'NORTH_BRANCH')
     * @param string $name Branch name
     * @param array $config
     * @return LedgerDomain
     */
    public function createBranch(string $companyCode, string $code, string $name, array $config = []): LedgerDomain
    {
        $fullCode = $companyCode . '_' . $code;
        return $this->createDomain($fullCode, $name, 'branch', $companyCode, $config);
    }

    /**
     * Generic domain creation (advanced use).
     * 
     * @param string $code Unique domain code
     * @param string $name Domain name
     * @param string $type 'company', 'department', or 'branch'
     * @param string|null $parentCode Parent domain code
     * @param array $config
     * @return LedgerDomain
     */
    public function createDomain(
        string $code, 
        string $name, 
        string $type = 'company', 
        ?string $parentCode = null, 
        array $config = []
    ): LedgerDomain {
        // Validate parent exists if specified
        if ($parentCode && !$this->getDomain($parentCode)) {
            throw new Exception("Parent domain {$parentCode} not found");
        }

        $message = new Domain();
        $message->code = $code;
        $message->name = $name;
        
        if (isset($config['currency'])) {
            $message->currency = $config['currency'];
        }
        
        // Store metadata about domain type and hierarchy
        $message->extra = json_encode([
            'type' => $type,
            'parent_code' => $parentCode,
            'level' => $parentCode ? 1 : 0,
        ]);
        
        $response = $this->domainController->add($message);
        
        if (!$response->success) {
            throw new Exception($response->errors[0] ?? 'Failed to create domain');
        }
        
        return $response->domain;
    }

    /**
     * List all companies (top-level domains).
     */
    public function listCompanies(): Collection
    {
        return LedgerDomain::all()->filter(function($domain) {
            $extra = json_decode($domain->extra ?? '{}', true);
            return ($extra['type'] ?? 'company') === 'company';
        });
    }

    /**
     * List departments within a company.
     */
    public function listDepartments(string $companyCode): Collection
    {
        return LedgerDomain::all()->filter(function($domain) use ($companyCode) {
            $extra = json_decode($domain->extra ?? '{}', true);
            return ($extra['type'] ?? null) === 'department' 
                && ($extra['parent_code'] ?? null) === $companyCode;
        });
    }

    /**
     * List branches within a company.
     */
    public function listBranches(string $companyCode): Collection
    {
        return LedgerDomain::all()->filter(function($domain) use ($companyCode) {
            $extra = json_decode($domain->extra ?? '{}', true);
            return ($extra['type'] ?? null) === 'branch' 
                && ($extra['parent_code'] ?? null) === $companyCode;
        });
    }

    /**
     * Get organization tree for a company.
     */
    public function getOrganizationTree(string $companyCode): array
    {
        $company = $this->getDomain($companyCode);
        
        if (!$company) {
            throw new Exception("Company {$companyCode} not found");
        }

        return [
            'company' => $company,
            'departments' => $this->listDepartments($companyCode)->values()->toArray(),
            'branches' => $this->listBranches($companyCode)->values()->toArray(),
        ];
    }

    /**
     * Get a domain by code.
     */
    public function getDomain(string $code): ?LedgerDomain
    {
        return LedgerDomain::where('code', $code)->first();
    }

    /**
     * List all domains.
     */
    public function listDomains(): Collection
    {
        return LedgerDomain::all();
    }

    /**
     * Update domain information.
     */
    public function updateDomain(string $code, array $data): bool
    {
        $domain = $this->getDomain($code);
        
        if (!$domain) {
            throw new Exception("Domain {$code} not found");
        }
        
        if (isset($data['name'])) {
            $domain->name = $data['name'];
        }
        
        return $domain->save();
    }

    /**
     * Delete a domain.
     * WARNING: This will delete all accounts and transactions in the domain!
     */
    public function deleteDomain(string $code): bool
    {
        $domain = $this->getDomain($code);
        
        if (!$domain) {
            throw new Exception("Domain {$code} not found");
        }
        
        if ($code === 'MAIN') {
            throw new Exception("Cannot delete the MAIN domain");
        }
        
        return $domain->delete();
    }

    /**
     * Get company information.
     */
    public function getCompanyInfo(string $domainCode): array
    {
        $domain = $this->getDomain($domainCode);
        
        if (!$domain) {
            throw new Exception("Domain {$domainCode} not found");
        }
        
        $extra = json_decode($domain->extra ?? '{}', true);
        
        return [
            'code' => $domain->code,
            'name' => $domain->name,
            'type' => $extra['type'] ?? 'company',
            'parent_code' => $extra['parent_code'] ?? null,
            'currency' => $domain->currency ?? config('alamia-accounts.default_currency', 'USD'),
            'created_at' => $domain->created_at,
        ];
    }

    /**
     * Set the active domain for the current session.
     */
    public function switchActiveDomain(string $code): void
    {
        $domain = $this->getDomain($code);
        
        if (!$domain) {
            throw new Exception("Domain {$code} not found");
        }
        
        DomainContext::set($code);
    }

    /**
     * Get the currently active domain.
     */
    public function getActiveDomain(): string
    {
        return DomainContext::get();
    }
}
