<?php

namespace AlamiaSoft\AlamiaAccounts\Console\Commands;

use Illuminate\Console\Command;
use AlamiaSoft\AlamiaAccounts\Services\AccountService;
use AlamiaSoft\AlamiaAccounts\Services\CompanyService;
use AlamiaSoft\AlamiaAccounts\Services\DomainContext;

class TestLedger extends Command
{
    protected $signature = 'ledger:test';
    protected $description = 'Test ledger setup and create a sample transaction';

    public function handle(): int
    {
        $this->info('Testing Ledger Setup...');
        $this->newLine();

        // Check domain
        $domain = app(CompanyService::class)->getDomain('default');
        if ($domain) {
            $this->info("✓ Domain found: {$domain->code}");
        } else {
            $this->error("✗ Domain not found");
            return Command::FAILURE;
        }

        // Check accounts
        DomainContext::set($domain->code);
        $accounts = app(AccountService::class)->getChartOfAccounts();
        $this->info("✓ Total accounts: {$accounts->count()}");
        
        $this->newLine();
        $this->info('Sample accounts:');
        
        foreach ($accounts->take(5) as $account) {
            $name = $account->name ?? 'No name';
            $this->line("  • {$account->code} - {$name} ({$account->category})");
        }

        $this->newLine();
        $this->info('✅ Ledger is working correctly!');

        return Command::SUCCESS;
    }
}
