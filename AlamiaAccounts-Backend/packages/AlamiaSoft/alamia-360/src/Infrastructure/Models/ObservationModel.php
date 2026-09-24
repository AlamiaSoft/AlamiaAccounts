<?php

namespace Alamia360\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

class ObservationModel extends Model
{
    protected $table = 'alamia_observations';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'observed_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('alamia360.persistence.connection')
            ?? (config('tenancy.database.central_connection') ? config('tenancy.database.central_connection') : parent::getConnectionName());
    }
}
