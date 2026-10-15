<?php

namespace AlamiaSoft\AlamiaAccounts\Console\Commands;

use Illuminate\Console\Command;
use AlamiaSoft\AlamiaAccounts\Database\Seeders\StandardErpKnowledgeSeeder;

class SyncCopilotKnowledgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'copilot:sync-knowledge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync standard ERP knowledgebase rules from repository markdown files into database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Scanning and syncing standard ERP guidance markdown files...");
        $seeder = new StandardErpKnowledgeSeeder();
        $seeder->setCommand($this);
        $seeder->run();
        $this->info("Copilot standard knowledge sync complete.");
        return 0;
    }
}
