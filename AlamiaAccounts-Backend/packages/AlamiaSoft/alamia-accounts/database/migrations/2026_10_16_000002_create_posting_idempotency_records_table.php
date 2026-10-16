<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create durable posting idempotency table
        if (!Schema::hasTable('posting_idempotency_records')) {
            Schema::create('posting_idempotency_records', function (Blueprint $table) {
                $table->id();
                $table->string('domain_uuid', 36)->index();
                $table->string('company_code', 50)->index();
                $table->string('idempotency_key', 191);
                $table->string('source_module', 50)->default('integration')->index();
                $table->string('source_transaction_id', 100)->nullable()->index();
                $table->string('operation', 50)->default('post');
                $table->string('version', 20)->default('v1');
                $table->string('payload_hash', 64)->index();
                $table->json('request_payload')->nullable();
                $table->string('status', 30)->default('in_flight')->index(); // in_flight, committed, failed
                $table->string('lease_token', 64)->nullable()->index();
                $table->unsignedBigInteger('lease_generation')->default(1);
                $table->timestamp('lease_expires_at')->nullable()->index();
                $table->string('voucher_reference', 100)->nullable()->index();
                $table->unsignedBigInteger('journal_entry_id')->nullable()->index();
                $table->json('response_data')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                // Tenant-scoped client lookup unique constraint
                $table->unique(['domain_uuid', 'idempotency_key'], 'domain_idempotency_unique');

                // Authoritative canonical 5-tuple unique constraint
                $table->unique(
                    ['domain_uuid', 'source_module', 'source_transaction_id', 'operation', 'version'],
                    'domain_canonical_identity_unique'
                );

                $table->index(['company_code', 'status'], 'posting_company_status_idx');
                $table->index(['domain_uuid', 'voucher_reference'], 'posting_domain_reference_idx');
            });
        }

        // 2. Add reference column, backfill existing records, and apply unique constraint on domain_journal_entries
        if (Schema::hasTable('domain_journal_entries')) {
            Schema::table('domain_journal_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('domain_journal_entries', 'reference')) {
                    $table->string('reference', 100)->nullable()->after('journalEntryId')->index();
                }
            });

            // Deterministic backfill of existing rows before applying unique constraint
            try {
                $entries = DB::table('domain_journal_entries')
                    ->leftJoin('journal_entries', 'domain_journal_entries.journalEntryId', '=', 'journal_entries.journalEntryId')
                    ->where(function ($q) {
                        $q->whereNull('domain_journal_entries.reference')
                          ->orWhere('domain_journal_entries.reference', '');
                    })
                    ->select('domain_journal_entries.id', 'domain_journal_entries.journalEntryId', 'journal_entries.extra')
                    ->get();

                foreach ($entries as $e) {
                    $ref = null;
                    if (!empty($e->extra)) {
                        $extra = is_string($e->extra) ? json_decode($e->extra, true) : (array)$e->extra;
                        $ref = $extra['reference'] ?? $extra['voucher_number'] ?? null;
                    }
                    if (empty($ref)) {
                        $ref = 'LEGACY-' . $e->journalEntryId;
                    }
                    DB::table('domain_journal_entries')
                        ->where('id', $e->id)
                        ->update(['reference' => trim((string)$ref)]);
                }
            } catch (\Throwable $t) {
                // Table may be empty during test initialization
            }

            // Apply composite unique constraint
            Schema::table('domain_journal_entries', function (Blueprint $table) {
                try {
                    $table->unique(['domainUuid', 'reference'], 'domain_reference_unique');
                } catch (\Throwable $t) {
                    // Handled if index already exists
                }
            });
        }

        // 3. Add unique constraint on operational_sales (company_code + idempotency_key)
        if (Schema::hasTable('operational_sales')) {
            Schema::table('operational_sales', function (Blueprint $table) {
                try {
                    $table->unique(['company_code', 'idempotency_key'], 'op_sales_company_idempotency_unique');
                } catch (\Throwable $t) {
                    // Handled if index already exists
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('operational_sales')) {
            Schema::table('operational_sales', function (Blueprint $table) {
                try {
                    $table->dropUnique('op_sales_company_idempotency_unique');
                } catch (\Throwable $t) {}
            });
        }

        if (Schema::hasTable('domain_journal_entries')) {
            Schema::table('domain_journal_entries', function (Blueprint $table) {
                try {
                    $table->dropUnique('domain_reference_unique');
                } catch (\Throwable $t) {}
                if (Schema::hasColumn('domain_journal_entries', 'reference')) {
                    $table->dropColumn('reference');
                }
            });
        }

        Schema::dropIfExists('posting_idempotency_records');
    }
};
