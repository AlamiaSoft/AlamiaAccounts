<?php

namespace AlamiaSoft\AlamiaAccounts\Models;

use Illuminate\Database\Eloquent\Model;

class CopilotKnowledgeEntry extends Model
{
    protected $table = 'copilot_knowledge_entries';

    protected $fillable = [
        'company_code',
        'topic',
        'trigger_keywords',
        'domain',
        'title',
        'summary',
        'steps',
        'note',
        'actions',
        'source_diagnostic_id',
        'is_active',
        'promoted_by',
        'promoted_at',
        'change_reason',
        'version',
    ];

    protected $casts = [
        'trigger_keywords' => 'array',
        'steps' => 'array',
        'actions' => 'array',
        'is_active' => 'boolean',
        'promoted_at' => 'datetime',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sourceDiagnostic()
    {
        return $this->belongsTo(CopilotDiagnosticLog::class, 'source_diagnostic_id');
    }
}
