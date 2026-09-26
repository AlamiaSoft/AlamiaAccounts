<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotDiagnosticLog extends Model
{
    protected $table = 'copilot_diagnostic_logs';

    protected $fillable = [
        'session_id',
        'company_code',
        'user_id',
        'user_name',
        'prompt',
        'classifier_mode',
        'classifier_intent',
        'classifier_confidence',
        'classifier_output',
        'context_before',
        'context_after',
        'safety_evaluations',
        'dispatched_action',
        'execution_result',
        'final_response',
        'duration_ms',
        'status',
        'developer_notes',
        'anomaly_flag',
        'entry_point',
    ];

    protected $casts = [
        'classifier_output' => 'array',
        'context_before' => 'array',
        'context_after' => 'array',
        'safety_evaluations' => 'array',
        'execution_result' => 'array',
        'final_response' => 'array',
        'classifier_confidence' => 'float',
        'duration_ms' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function knowledgeEntries()
    {
        return $this->hasMany(CopilotKnowledgeEntry::class, 'source_diagnostic_id');
    }
}
