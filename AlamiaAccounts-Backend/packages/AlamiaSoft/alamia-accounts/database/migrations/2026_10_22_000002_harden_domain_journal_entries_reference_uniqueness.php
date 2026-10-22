<?php

use AlamiaSoft\AlamiaAccounts\Models\DomainJournalEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('domain_journal_entries')) {
            return;
        }

        if (!Schema::hasColumn('domain_journal_entries', 'reference')) {
            Schema::table('domain_journal_entries', function (Blueprint $table) {
                $table->string('reference', 120)->nullable()->after('journalEntryId');
            });
        }

        // Deterministically backfill NULL/blank references and disambiguate any historical duplicates
        DomainJournalEntry::reconcileAndBackfillReferences();

        // Ensure standard composite unique index (domainUuid, reference) exists
        try {
            Schema::table('domain_journal_entries', function (Blueprint $table) {
                $table->unique(['domainUuid', 'reference'], 'domain_reference_unique');
            });
        } catch (\Throwable $e) {
            // Index already exists from 2026_10_16_000002_create_posting_idempotency_records_table
        }

        // Enforce case-insensitive company-scoped reference uniqueness at the DB boundary
        // on both supported engines (SQLite and PostgreSQL)
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS domain_reference_ci_unique ON domain_journal_entries ("domainUuid", UPPER("reference"))'
            );
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('domain_journal_entries')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['sqlite', 'pgsql'], true)) {
            DB::statement('DROP INDEX IF EXISTS domain_reference_ci_unique');
        }
    }
};
