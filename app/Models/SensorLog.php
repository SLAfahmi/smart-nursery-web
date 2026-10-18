<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SensorLog extends Model
{
    use MassPrunable;

    protected $fillable = [
        'device_key',
        'temperature',
        'humidity',
        'light_level',
        'system_mode',
        'relay_main',
        'is_offline_data',
        'recorded_at',
    ];

    protected $casts = [
        'temperature' => 'float',
        'humidity' => 'float',
        'light_level' => 'float',
        'system_mode' => 'integer',
        'relay_main' => 'boolean',
        'is_offline_data' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function zoneLogs(): HasMany
    {
        return $this->hasMany(ZoneLog::class);
    }

    /**
     * Riwayat lama dihapus oleh `php artisan model:prune` (zone_logs ikut terhapus via cascade)
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(config('nursery.history_retention_days')));
    }

    /**
     * Format yang sama dengan DeviceState::snapshotFromTelemetry()
     */
    public function toSnapshot(): array
    {
        return [
            'recorded_at' => ($this->recorded_at ?? $this->created_at)->toDateTimeString(),
            'temperature' => $this->temperature,
            'humidity' => $this->humidity,
            'light_level' => $this->light_level,
            'system_mode' => $this->system_mode,
            'relay_main' => (bool) $this->relay_main,
            'zones' => $this->zoneLogs->sortBy('zone_id')->map(fn (ZoneLog $zone) => [
                'zone_id' => $zone->zone_id,
                'soil_moisture' => $zone->soil_moisture,
                'relay_status' => (bool) $zone->relay_status,
                'state' => $zone->state,
                'fuzzy_duration' => $zone->fuzzy_duration,
            ])->values()->all(),
        ];
    }
}
