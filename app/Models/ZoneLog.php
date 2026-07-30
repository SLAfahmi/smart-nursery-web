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
        'zone_mode',
        'duration',
        'relay_status',
    ];

    protected $casts = [
        'zone_id' => 'integer',
        'soil_moisture' => 'integer',
        'zone_mode' => 'integer',
        'duration' => 'integer',
        'relay_status' => 'boolean',
    ];

    public function sensorLog(): BelongsTo
    {
        return $this->belongsTo(SensorLog::class);
    }
}
