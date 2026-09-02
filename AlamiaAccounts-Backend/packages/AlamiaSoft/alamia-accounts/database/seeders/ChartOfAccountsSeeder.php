<?php
namespace AlamiaSoft\AlamiaAccounts\Database\Seeders;

use Illuminate\Database\Seeder;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\Name;
use Abivia\Ledger\Ledger;



class ChartOfAccountsSeeder extends Seeder
{
    private ?LedgerDomain $domain = null;

    public function run(): void
    {
        $this->command->info('Creating Chart of Accounts...');

        // Get the default domain
        $this->domain = LedgerDomain::where('code', 'MAIN')->first();

        if (!$this->domain) {
            $this->command->error('Ledger domain not found. Run LedgerInitializationSeeder first.');
            return;
        }

        // Set the domain context so Abivia Ledger knows which domain to use
        \Abivia\Ledger\Ledger::domain('MAIN');

        // Main Categories
        $ctrl = new LedgerAccountController();
        
        // Root categories
        $assets = $this->createAccount('1000', 'Assets', 'asset', true, true);
        $liabilities = $this->createAccount('2000', 'Liabilities', 'liability', true, false);
        $revenue = $this->createAccount('3000', 'Revenue', 'revenue', true, false);
        $expenses = $this->createAccount('4000', 'Expenses', 'expense', true, true);
        $equity = $this->createAccount('5000', 'Equity', 'equity', true, false);

        // Assets Sub-accounts
        $this->createAccount('1100', 'Current Assets', 'asset', true, true, $assets->ledgerUuid);
        $this->createAccount('1110', 'Cash', 'asset', false, true, $assets->ledgerUuid);
        $this->createAccount('1120', 'Bank Accounts', 'asset', false, true, $assets->ledgerUuid);
        $this->createAccount('1200', 'Accounts Receivable', 'asset', false, true, $assets->ledgerUuid);
        $this->createAccount('1300', 'Inventory', 'asset', false, true, $assets->ledgerUuid);

        // Liabilities Sub-accounts
        $this->createAccount('2100', 'Accounts Payable', 'liability', false, false, $liabilities->ledgerUuid);
        $this->createAccount('2200', 'GST Payable', 'liability', false, false, $liabilities->ledgerUuid);
        $this->createAccount('2300', 'Sales Tax Payable', 'liability', false, false, $liabilities->ledgerUuid);

        // Revenue Sub-accounts
        $this->createAccount('3100', 'Sales Revenue', 'revenue', false, false, $revenue->ledgerUuid);
        $this->createAccount('3200', 'Service Revenue', 'revenue', false, false, $revenue->ledgerUuid);
        $this->createAccount('3300', 'Other Income', 'revenue', false, false, $revenue->ledgerUuid);

        // Expenses Sub-accounts
        $this->createAccount('4100', 'Cost of Goods Sold', 'expense', false, true, $expenses->ledgerUuid);
        $this->createAccount('4200', 'Operating Expenses', 'expense', false, true, $expenses->ledgerUuid);
        $this->createAccount('4300', 'Salaries & Wages', 'expense', false, true, $expenses->ledgerUuid);
        $this->createAccount('4400', 'Rent Expense', 'expense', false, true, $expenses->ledgerUuid);
        $this->createAccount('4500', 'Utilities', 'expense', false, true, $expenses->ledgerUuid);
        $this->createAccount('4600', 'Office Supplies', 'expense', false, true, $expenses->ledgerUuid);

        // Equity Sub-accounts
        $this->createAccount('5100', 'Owner\'s Capital', 'equity', false, false, $equity->ledgerUuid);
        $this->createAccount('5200', 'Retained Earnings', 'equity', false, false, $equity->ledgerUuid);
        $this->createAccount('5300', 'Drawings', 'equity', false, true, $equity->ledgerUuid);


        $this->command->info('✓ Chart of Accounts created successfully!');
    }

    private function createAccount(
        string $code,              // Account code
        string $name,           // Account name
        string $categoryName,   //asset, liability, equity, revenue, expense
        bool $isCategory,       // true if this is a category account
        bool $debit,            // true if normal balance is debit
        ?string $parentUuid = null  // Parent account code (if any)
    ): LedgerAccount
     {
        // Check if account already exists

        $this->command->info("Checking if account: {$name} ({$code}) already exists...");
        $existing = LedgerAccount::where('code', $code)->first();
        
        if ($existing) {
                    $existingName = $existing->name ?? 'No name';
            $this->command->warn("Account {$code} already exists ({$existingName}, {$existing->ledgerUuid}), skipping...");
            return $existing;

        }

        $this->command->info("Creating account: {$name}, parent: {$parentUuid}");
        $ctrl = new LedgerAccountController();
        $parent = LedgerAccount::where('code', $parentUuid)->first();
        $this->command->info("Parent account: " . ($parent ? $parent->code : 'None') . ', UUID: ' . ($parent ? $parent->ledgerUuid : 'N/A'));
        // $message = Account::fromArray([
        //     'code'       => $code,
        //     'category'   => $category,
        //     'debit'      => $debit,
        //     'parentUuid' => $parent->ledgerUuid ?? null,
        //     'names'      => [
        //         [
        //             'name'     => $name,
        //             'language' => 'en',
        //         ],
        //     ],
        // ]);
        $message = Account::fromArray([
            'code'       => $code,
            'category'   => $isCategory,
            'debit'      => $debit,
            'parent'     => [
                'uuid' => $parent->ledgerUuid ?? null,
            ],
            'names'      => [
                [
                    'name'     => $name,
                    'language' => 'en',
                ],
            ],
        ]);



        $result = $ctrl->add($message);

        if ($result->success) {
            $this->command->info("✓ {$code} - {$name}");
            return $result->account;
        } else {
            $error = $result->errors[0] ?? 'Unknown error';
            $this->command->error("Failed to create {$code}: {$error}");
            throw new \Exception("Failed to create account {$code}: {$error}");
        }

        

        return $result;
    }


}
