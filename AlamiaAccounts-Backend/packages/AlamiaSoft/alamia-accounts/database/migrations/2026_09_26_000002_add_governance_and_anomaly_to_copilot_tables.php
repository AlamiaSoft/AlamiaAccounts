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
        if (Schema::hasTable('copilot_diagnostic_logs')) {
            Schema::table('copilot_diagnostic_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('copilot_diagnostic_logs', 'anomaly_flag')) {
                    $table->string('anomaly_flag', 50)->nullable()->index()->after('status');
                }
                if (!Schema::hasColumn('copilot_diagnostic_logs', 'entry_point')) {
                    $table->string('entry_point', 50)->nullable()->index()->after('classifier_mode');
                }
            });
        }

        if (Schema::hasTable('copilot_knowledge_entries')) {
            Schema::table('copilot_knowledge_entries', function (Blueprint $table) {
                if (!Schema::hasColumn('copilot_knowledge_entries', 'promoted_by')) {
                    $table->string('promoted_by')->nullable()->after('source_diagnostic_id');
                }
                if (!Schema::hasColumn('copilot_knowledge_entries', 'promoted_at')) {
                    $table->timestamp('promoted_at')->nullable()->after('promoted_by');
                }
                if (!Schema::hasColumn('copilot_knowledge_entries', 'change_reason')) {
                    $table->text('change_reason')->nullable()->after('promoted_at');
                }
                if (!Schema::hasColumn('copilot_knowledge_entries', 'version')) {
                    $table->unsignedInteger('version')->default(1)->after('change_reason');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('copilot_diagnostic_logs')) {
            Schema::table('copilot_diagnostic_logs', function (Blueprint $table) {
                if (Schema::hasColumn('copilot_diagnostic_logs', 'anomaly_flag')) {
                    $table->dropColumn('anomaly_flag');
                }
                if (Schema::hasColumn('copilot_diagnostic_logs', 'entry_point')) {
                    $table->dropColumn('entry_point');
                }
            });
        }

        if (Schema::hasTable('copilot_knowledge_entries')) {
            Schema::table('copilot_knowledge_entries', function (Blueprint $table) {
                $cols = array_filter(['promoted_by', 'promoted_at', 'change_reason', 'version'], fn($c) => Schema::hasColumn('copilot_knowledge_entries', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
