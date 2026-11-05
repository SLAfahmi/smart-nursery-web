<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Kondisi terakhir yang dilaporkan ESP32 (diperbarui setiap telemetry live).
 */
class DeviceState extends Model
{
    // Alat dianggap offline jika tidak mengirim telemetry selama ini (firmware kirim tiap 5 detik)
    public const OFFLINE_AFTER_SECONDS = 20;

    protected $fillable = [
        'device_key',
        'reported_version',
        'firmware_version',
        'ota_status',
        'last_seen_at',
        'state',
    ];

    protected $casts = [
        'reported_version' => 'integer',
        'ota_status' => 'array',
        'last_seen_at' => 'datetime',
        'state' => 'array',
    ];

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(now()->subSeconds(self::OFFLINE_AFTER_SECONDS));
    }

    /**
     * Jam alat pada telemetry terakhir dibandingkan jam server saat telemetry itu diterima.
     * drift_seconds > 0 berarti jam alat lebih cepat. Selisih beberapa detik wajar (waktu kirim).
     */
    public function clockStatus(): ?array
    {
        if (!$this->state || $this->last_seen_at === null) {
            return null;
        }

        // Snapshot lama belum punya device_clock; anggap recorded_at adalah jam alat
        $raw = array_key_exists('device_clock', $this->state) ? $this->state['device_clock'] : $this->state['recorded_at'];
        $deviceTime = $raw === null ? null : Carbon::parse($raw);

        return [
            'valid' => $deviceTime !== null,
            'device_time' => $deviceTime?->format('d/m/Y H:i:s'),
            'received_at' => $this->last_seen_at->format('d/m/Y H:i:s'),
            'drift_seconds' => $deviceTime ? (int) round($this->last_seen_at->diffInSeconds($deviceTime, false)) : null,
        ];
    }

    /**
     * Ubah payload telemetry firmware ke format tampilan dashboard
     * (key sama dengan kolom sensor_logs / zone_logs, lihat SensorLog::toSnapshot()).
     */
    public static function snapshotFromTelemetry(array $telemetry): array
    {
        $deviceClock = $telemetry['recorded_at'] ?? null;

        return [
            'recorded_at' => Carbon::parse($deviceClock ?? now())->toDateTimeString(),
            // Jam alat apa adanya; null = jam alat belum di-set (dipakai kartu RTC di tab Config)
            'device_clock' => $deviceClock === null ? null : Carbon::parse($deviceClock)->toDateTimeString(),
            'temperature' => $telemetry['temperature'] ?? null,
            'humidity' => $telemetry['humidity'] ?? null,
            'light_level' => $telemetry['light'] ?? null,
            'system_mode' => $telemetry['system_mode'] ?? 0,
            'relay_main' => (bool) ($telemetry['relay_main'] ?? false),
            'zones' => collect($telemetry['zones'])->map(fn ($zone) => [
                'zone_id' => (int) $zone['zone'],
                'soil_moisture' => $zone['soil'] ?? null,
                'relay_status' => (bool) ($zone['relay'] ?? false),
                'state' => $zone['state'] ?? 'idle',
                'fuzzy_duration' => $zone['fuzzy_duration'] ?? 0,
            ])->sortBy('zone_id')->values()->all(),
        ];
    }
}
