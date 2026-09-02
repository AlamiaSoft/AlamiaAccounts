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
        // 1. Accounting Periods & Fiscal Controls
        if (!Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('domain_uuid', 36)->index();
                $table->integer('fiscal_year');
                $table->integer('period_number'); // 1-12
                $table->string('period_name', 50); // e.g. "2026-01" or "January 2026"
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 20)->default('open'); // 'open', 'closed'
                $table->timestamp('closed_at')->nullable();
                $table->string('closed_by')->nullable();
                $table->timestamp('reopened_at')->nullable();
                $table->string('reopened_by')->nullable();
                $table->text('reopen_reason')->nullable();
                $table->timestamps();

                $table->unique(['domain_uuid', 'fiscal_year', 'period_number'], 'domain_fy_period_unique');
                $table->index(['domain_uuid', 'start_date', 'end_date'], 'domain_period_dates_idx');
            });
        }

        // 2. Accounting Audit Trail
        if (!Schema::hasTable('accounting_audit_trails')) {
            Schema::create('accounting_audit_trails', function (Blueprint $table) {
                $table->id();
                $table->string('domain_uuid', 36)->index();
                $table->string('user_id')->nullable();
                $table->string('user_name')->nullable();
                $table->string('action', 50); // CREATE_VOUCHER, REVERSE_VOUCHER, CLOSE_PERIOD, REOPEN_PERIOD, POST_OPENING_BALANCE
                $table->string('entity_type', 50); // voucher, period, account, opening_balance
                $table->string('entity_reference', 100)->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['domain_uuid', 'created_at'], 'domain_audit_time_idx');
                $table->index(['domain_uuid', 'action'], 'domain_audit_action_idx');
            });
        }

        // 3. Opening Balance Batches
        if (!Schema::hasTable('opening_balance_batches')) {
            Schema::create('opening_balance_batches', function (Blueprint $table) {
                $table->id();
                $table->string('domain_uuid', 36)->index();
                $table->string('reference', 64);
                $table->date('balance_date');
                $table->decimal('total_debit', 15, 2);
                $table->decimal('total_credit', 15, 2);
                $table->string('balancing_account_code', 32)->nullable();
                $table->decimal('balancing_amount', 15, 2)->default(0);
                $table->string('status', 20)->default('posted');
                $table->string('created_by')->nullable();
                $table->timestamps();

                $table->unique(['domain_uuid', 'reference'], 'domain_ob_ref_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_balance_batches');
        Schema::dropIfExists('accounting_audit_trails');
        Schema::dropIfExists('accounting_periods');
    }
};
