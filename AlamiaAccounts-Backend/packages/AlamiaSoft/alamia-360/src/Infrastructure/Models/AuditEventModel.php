<?php

namespace Alamia360\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEventModel extends Model
{
    protected $table = 'alamia_audit_events';

    protected $guarded = [];

    protected $casts = [
        'input' => 'array',
        'result' => 'array',
    ];

    public $timestamps = false;

    public function getConnectionName()
    {
        return config('alamia360.persistence.connection')
            ?? (config('tenancy.database.central_connection') ? config('tenancy.database.central_connection') : parent::getConnectionName());
    }
}
