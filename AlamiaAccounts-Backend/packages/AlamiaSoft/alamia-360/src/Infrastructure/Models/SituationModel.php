<?php

namespace Alamia360\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SituationModel extends Model
{
    protected $table = 'alamia_situations';

    protected $guarded = [];

    protected $casts = [
        'context'     => 'array',
        'resolution'  => 'array',
        'due_at'      => 'datetime',
        'detected_at' => 'datetime',
    ];

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEventModel::class, 'situation_id');
    }

    public function getConnectionName()
    {
        return config('alamia360.persistence.connection')
            ?? (config('tenancy.database.central_connection') ? config('tenancy.database.central_connection') : parent::getConnectionName());
    }
}
