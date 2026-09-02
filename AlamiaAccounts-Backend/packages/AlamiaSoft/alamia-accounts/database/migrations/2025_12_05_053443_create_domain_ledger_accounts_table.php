<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This pivot table associates LedgerAccounts with LedgerDomains.
     * One account belongs to ONE domain (business rule: no sharing across domains).
     */
    public function up(): void
    {
        Schema::create('domain_ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('domainUuid');
            $table->string('ledgerUuid'); // ledger account UUID
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('domainUuid')
                ->references('domainUuid')
                ->on('ledger_domains')
                ->onDelete('cascade');
            
            $table->foreign('ledgerUuid')
                ->references('ledgerUuid')
                ->on('ledger_accounts')
                ->onDelete('cascade');
            
            // Unique constraint: one account can only belong to ONE domain
            $table->unique(['domainUuid', 'ledgerUuid'], 'domain_account_unique');
            
            // Also ensure one account can't be in multiple domains
            $table->unique('ledgerUuid', 'account_unique');
            
            // Indexes for performance
            $table->index('domainUuid');
            $table->index('ledgerUuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_ledger_accounts');
    }
};
