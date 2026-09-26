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
        Schema::create('copilot_diagnostic_logs', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->nullable()->index();
            $table->string('company_code')->nullable()->index();
            $table->string('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->text('prompt');
            $table->string('classifier_mode', 50)->default('heuristic')->index(); // 'parlant', 'ollama', 'heuristic', 'direct_action'
            $table->string('classifier_intent', 100)->nullable()->index();
            $table->decimal('classifier_confidence', 5, 4)->nullable();
            $table->json('classifier_output')->nullable();
            $table->json('context_before')->nullable();
            $table->json('context_after')->nullable();
            $table->json('safety_evaluations')->nullable();
            $table->string('dispatched_action', 100)->nullable()->index();
            $table->json('execution_result')->nullable();
            $table->json('final_response')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('status', 50)->default('unreviewed')->index(); // 'unreviewed', 'verified', 'needs_fix', 'promoted_to_kb'
            $table->text('developer_notes')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('copilot_knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('company_code')->nullable()->index(); // null = global standard ERP knowledge
            $table->string('topic', 100)->index();
            $table->json('trigger_keywords'); // array of trigger words/phrases/regex
            $table->string('domain', 50)->default('general')->index(); // 'voucher', 'coa', 'reports', 'periods', 'opening_balance', 'general'
            $table->string('title');
            $table->text('summary');
            $table->json('steps')->nullable();
            $table->text('note')->nullable();
            $table->json('actions')->nullable(); // structured UI action buttons (navigate_page, draft_prompt, etc.)
            $table->unsignedBigInteger('source_diagnostic_id')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('copilot_knowledge_entries');
        Schema::dropIfExists('copilot_diagnostic_logs');
    }
};
