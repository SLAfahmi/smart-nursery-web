<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorLog extends Model
{
    protected $fillable = [
        'temperature',
        'humidity',
        'light_level',
        'system_mode',
        'is_offline_data',
        'recorded_at',
    ];

    protected $casts = [
        'temperature' => 'float',
        'humidity' => 'float',
        'light_level' => 'integer',
        'system_mode' => 'integer',
        'is_offline_data' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function zoneLogs(): HasMany
    {
        return $this->hasMany(ZoneLog::class);
    }
}
