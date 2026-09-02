<?php

namespace AlamiaSoft\AlamiaAccounts\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class SetupLedger extends Command
{
    protected $signature = 'ledger:setup {--fresh : Drop all tables and migrate fresh}';
    protected $description = 'Initialize Abivia Ledger and create chart of accounts';

    public function handle(): int
    {
        $this->info('🚀 Setting up Abivia Ledger...');
        $this->newLine();

        if ($this->option('fresh')) {
            if ($this->confirm('This will drop all tables. Are you sure?', false)) {
                $this->warn('Dropping all tables...');
                Artisan::call('migrate:fresh');
                $this->info(Artisan::output());
            } else {
                $this->info('Aborted.');
                return Command::FAILURE;
            }
        }

        // Run migrations
        $this->info('📦 Running migrations...');
        Artisan::call('migrate');
        $this->newLine();

        // Initialize ledger
        $this->info('🔧 Initializing ledger domain...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\LedgerInitializationSeeder'
        ]);
        $this->newLine();

        // Create chart of accounts
        $this->info('📊 Creating chart of accounts...');
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\ChartOfAccountsSeeder'
        ]);
        $this->newLine();

        $this->info('✅ Ledger setup completed successfully!');
        $this->newLine();
        $this->info('You can now:');
        $this->line('  • Access Filament admin: http://localhost:8000/admin');
        $this->line('  • View accounts: php artisan tinker → LedgerAccount::all()');
        
        return Command::SUCCESS;
    }
}
