<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('unposted_integration_entries')) {
            Schema::create('unposted_integration_entries', function (Blueprint $table) {
                $table->id();
                $table->string('domain_uuid', 36)->index();
                $table->string('company_code', 50)->index();
                $table->string('source_module', 50)->default('travelos')->index();
                $table->string('source_reference', 100)->index();
                $table->string('idempotency_key', 150)->unique();
                $table->string('event_type', 50); // e.g., 'expense', 'sale', 'payment'
                $table->json('payload');
                $table->text('error_message')->nullable();
                $table->string('status', 30)->default('failed')->index(); // 'failed', 'retrying', 'resolved', 'ignored'
                $table->unsignedInteger('retry_count')->default(0);
                $table->timestamp('last_attempted_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->string('resolved_voucher_ref', 100)->nullable();
                $table->timestamps();

                $table->index(['company_code', 'status']);
                $table->index(['source_module', 'source_reference']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unposted_integration_entries');
    }
};
