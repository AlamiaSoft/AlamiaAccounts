<?php

namespace AlamiaSoft\AlamiaAccounts\Database\Seeders;

use Illuminate\Database\Seeder;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Messages\Create;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Messages\Name;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;
use Carbon\Carbon;

class LedgerInitializationSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Initializing Ledger with Chart of Accounts...');

        // Check if ledger already initialized
        if (LedgerAccount::where('code', '')->exists()) {
            $this->command->warn('⚠️ Ledger already initialized, skipping.');
            return;
        }

        // Step 1: Initialize the ledger root using Create message
        $this->initializeLedgerRoot();

        // Step 2: Create domain
        $this->createDomain();

        // Step 3: Seed Chart of Accounts using AccountService
        $this->seedChartOfAccounts();

        $this->command->info('🎉 Ledger initialization completed!');
    }

    protected function initializeLedgerRoot(): void
    {
        // Create the ledger root account
        // This is required before any accounts can be added
        $create = new Create();
        $create->names[] = Name::fromArray(['name' => 'Main Company Ledger']);
        $create->currencies[] = Currency::fromArray(['code' => 'PKR', 'decimals' => 2]);
        $create->currencies[] = Currency::fromArray(['code' => 'USD', 'decimals' => 2]);
        $create->transDate = Carbon::now();

        $controller = new LedgerAccountController();
        $controller->create($create);

        $this->command->info('✅ Ledger root created.');
    }

    protected function createDomain(): void
    {
        if (\Illuminate\Support\Facades\DB::table('ledger_domains')->count() === 0) {
            $domainUuid = (string) \Illuminate\Support\Str::uuid();
            \Illuminate\Support\Facades\DB::table('ledger_domains')->insert([
                'domainUuid' => $domainUuid,
                'code' => 'MAIN',
                'currencyDefault' => 'PKR',
                'subJournals' => false,
                'extra' => json_encode([
                    'name' => 'Main Company',
                    'industry' => 'General',
                    'type' => 'company',
                    'level' => 0
                ]),
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
            ]);
            
            $this->command->info('✅ Domain created.');
            
            // Set domain context for account creation
            DomainContext::set('MAIN');
        }
    }

    protected function seedChartOfAccounts(): void
    {
        $accountService = new AccountService();
        
        $accounts = [
            // Root Categories
            ['code' => '1000', 'name' => 'Assets', 'category' => true, 'debit' => true],
            ['code' => '2000', 'name' => 'Liabilities', 'category' => true, 'credit' => true],
            ['code' => '3000', 'name' => 'Equity', 'category' => true, 'credit' => true],
            ['code' => '4000', 'name' => 'Expenses', 'category' => true, 'debit' => true],
            ['code' => '5000', 'name' => 'Revenue', 'category' => true, 'credit' => true],

            // Assets Sub-accounts
            ['code' => '1100', 'name' => 'Current Assets', 'category' => true, 'debit' => true, 'parent_code' => '1000'],
            ['code' => '1110', 'name' => 'Cash', 'debit' => true, 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'Bank Accounts', 'category' => true, 'debit' => true, 'parent_code' => '1100'],
            ['code' => '1200', 'name' => 'Accounts Receivable', 'debit' => true, 'parent_code' => '1100'],
            ['code' => '1300', 'name' => 'Inventory', 'debit' => true, 'parent_code' => '1100'],

            // Liabilities Sub-accounts
            ['code' => '2100', 'name' => 'Accounts Payable', 'credit' => true, 'parent_code' => '2000'],
            ['code' => '2200', 'name' => 'GST Payable', 'credit' => true, 'parent_code' => '2000'],
            ['code' => '2300', 'name' => 'Sales Tax Payable', 'credit' => true, 'parent_code' => '2000'],

            // Revenue Sub-accounts
            ['code' => '3100', 'name' => 'Sales Revenue', 'credit' => true, 'parent_code' => '5000'],
            ['code' => '3200', 'name' => 'Service Revenue', 'credit' => true, 'parent_code' => '5000'],
            ['code' => '3300', 'name' => 'Other Income', 'credit' => true, 'parent_code' => '5000'],

            // Expenses Sub-accounts
            ['code' => '4100', 'name' => 'Cost of Goods Sold', 'debit' => true, 'parent_code' => '4000'],
            ['code' => '4200', 'name' => 'Operating Expenses', 'debit' => true, 'parent_code' => '4000'],
            ['code' => '4300', 'name' => 'Salaries & Wages', 'debit' => true, 'parent_code' => '4000'],
            ['code' => '4400', 'name' => 'Rent Expense', 'debit' => true, 'parent_code' => '4000'],
            ['code' => '4500', 'name' => 'Utilities', 'debit' => true, 'parent_code' => '4000'],
            ['code' => '4600', 'name' => 'Office Supplies', 'debit' => true, 'parent_code' => '4000'],

            // Equity Sub-accounts
            ['code' => '5100', 'name' => "Owner's Capital", 'credit' => true, 'parent_code' => '3000'],
            ['code' => '5200', 'name' => 'Retained Earnings', 'credit' => true, 'parent_code' => '3000'],
            ['code' => '5300', 'name' => 'Drawings', 'debit' => true, 'parent_code' => '3000'],
        ];

        foreach ($accounts as $accountData) {
            try {
                // Use AccountService which properly sets domainUuid
                $account = $accountService->createAccount($accountData, 'MAIN');
                $this->command->info("✓ Created account {$accountData['code']} - {$accountData['name']}");
            } catch (\Exception $e) {
                $this->command->error("✗ Failed to create account {$accountData['code']}: " . $e->getMessage());
            }
        }

        $this->command->info('✅ Chart of Accounts seeded.');
    }
}
