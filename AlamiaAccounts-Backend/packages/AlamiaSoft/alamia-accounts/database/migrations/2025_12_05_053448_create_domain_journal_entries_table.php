<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This pivot table associates JournalEntries with LedgerDomains for performance.
     * Allows direct domain → journal entries queries without joining through accounts.
     */
    public function up(): void
    {
        Schema::create('domain_journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('domainUuid');
            $table->integer('journalEntryId')->unsigned();
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('domainUuid')
                ->references('domainUuid')
                ->on('ledger_domains')
                ->onDelete('cascade');
            
            $table->foreign('journalEntryId')
                ->references('journalEntryId')
                ->on('journal_entries')
                ->onDelete('cascade');
            
            // Unique constraint: one journal entry belongs to ONE domain
            $table->unique(['domainUuid', 'journalEntryId'], 'domain_entry_unique');
            
            // Also ensure one entry can't be in multiple domains
            $table->unique('journalEntryId', 'entry_unique');
            
            // Indexes for performance
            $table->index('domainUuid');
            $table->index('journalEntryId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_journal_entries');
    }
};
