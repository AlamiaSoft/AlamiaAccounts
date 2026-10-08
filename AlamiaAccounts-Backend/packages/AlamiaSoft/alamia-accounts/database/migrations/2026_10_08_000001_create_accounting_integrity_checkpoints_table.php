<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_integrity_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('domain_uuid', 64)->index();
            $table->string('event_type', 64)->index();
            $table->string('voucher_reference', 128)->nullable()->index();
            $table->string('voucher_type', 128)->nullable()->index();
            $table->unsignedBigInteger('journal_entry_id')->nullable();
            $table->date('as_of_date')->index();
            $table->string('currency', 16)->default('PKR');

            // Trial Balance Invariant Check
            $table->decimal('trial_balance_debit', 15, 2)->default(0.00);
            $table->decimal('trial_balance_credit', 15, 2)->default(0.00);
            $table->decimal('trial_balance_difference', 15, 2)->default(0.00);

            // Balance Sheet Invariant Check (Assets = Liabilities + Equity + PeriodNetProfit)
            $table->decimal('assets', 15, 2)->default(0.00);
            $table->decimal('liabilities', 15, 2)->default(0.00);
            $table->decimal('equity', 15, 2)->default(0.00);
            $table->decimal('bs_difference', 15, 2)->default(0.00);

            // Invariant Transition & Validation State
            $table->boolean('invariants_passed')->default(true)->index();
            $table->string('transition_state', 32)->default('PASS_TO_PASS')->index();
            $table->json('violations')->nullable();
            $table->json('metadata')->nullable();

            // Link to Previous Checkpoint for Continuous Causal Chain
            $table->unsignedBigInteger('previous_checkpoint_id')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_integrity_checkpoints');
    }
};
