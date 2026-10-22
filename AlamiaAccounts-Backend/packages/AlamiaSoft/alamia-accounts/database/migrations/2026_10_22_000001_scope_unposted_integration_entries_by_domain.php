<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Replaces the global unique constraint on unposted_integration_entries.idempotency_key
     * with a tenant-scoped composite unique constraint UNIQUE(domain_uuid, idempotency_key)
     * per ADR-008 so failed integration entries in one company cannot collide with or
     * overwrite another company's unposted queue entries.
     */
    public function up(): void
    {
        if (Schema::hasTable('unposted_integration_entries')) {
            Schema::table('unposted_integration_entries', function (Blueprint $table) {
                try {
                    $table->dropUnique('unposted_integration_entries_idempotency_key_unique');
                } catch (\Throwable $t) {
                    // Index may already have been dropped
                }

                try {
                    $table->unique(['domain_uuid', 'idempotency_key'], 'unposted_domain_idempotency_unique');
                } catch (\Throwable $t) {
                    // Index may already exist
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('unposted_integration_entries')) {
            Schema::table('unposted_integration_entries', function (Blueprint $table) {
                try {
                    $table->dropUnique('unposted_domain_idempotency_unique');
                } catch (\Throwable $t) {
                    // Ignore if absent
                }

                try {
                    $table->unique('idempotency_key', 'unposted_integration_entries_idempotency_key_unique');
                } catch (\Throwable $t) {
                    // Ignore if duplicate cross-domain keys exist or index already exists
                }
            });
        }
    }
};
