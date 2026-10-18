<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZoneLog extends Model
{
    protected $fillable = [
        'sensor_log_id',
        'zone_id',
        'soil_moisture',
        'relay_status',
        'state',
        'fuzzy_duration',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'soil_moisture' => 'float',
        'relay_status' => 'boolean',
        'fuzzy_duration' => 'float',
    ];

    public function sensorLog(): BelongsTo
    {
        return $this->belongsTo(SensorLog::class);
    }
}
