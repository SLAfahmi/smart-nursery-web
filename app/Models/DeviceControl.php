<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Konfigurasi alat yang diinginkan (diatur dari web) dan perintah untuk ESP32.
 */
class DeviceControl extends Model
{
    public const DEVICE_KEY = 'NURSERY_01';

    // Perintah relay yang belum terkirim dalam waktu ini dibuang (alat offline)
    public const RELAY_COMMAND_TTL_SECONDS = 30;

    // Nilai default firmware (include/config.h)
    public const DEFAULT_SYS_CONFIG = [
        'sensor_interval' => 10,
        'log_interval' => 3600,
        'cooldown' => 14400,
    ];

    // Kolom yang ikut dikirim ke alat lewat device_command
    public const CONFIG_FIELDS = ['system_mode', 'manual_mode_type', 'manual_duration', 'schedules_json', 'sys_config'];

    protected $fillable = [
        'device_key',
        'system_mode',
        'manual_mode_type',
        'manual_duration',
        'schedules_json',
        'sys_config',
        'command_version',
        'pending_relays',
        'updated_by_user_id',
    ];

    protected $casts = [
        'system_mode' => 'integer',
        'manual_mode_type' => 'integer',
        'manual_duration' => 'integer',
        'schedules_json' => 'array',
        'sys_config' => 'array',
        'command_version' => 'integer',
        'pending_relays' => 'array',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function current(): self
    {
        return static::firstOrCreate(['device_key' => self::DEVICE_KEY], [
            'system_mode' => 0,
            'manual_mode_type' => 0,
            'manual_duration' => 10,
            'schedules_json' => self::defaultSchedules(),
            'command_version' => 0,
        ]);
    }

    /**
     * Jadwal default dalam format firmware (settings_store.cpp: scheduleFromJson):
     * [{"zone":1,"slots":[{"active","hour","minute","duration"}, ...]}, ...]
     */
    public static function defaultSchedules(): array
    {
        $times = [[7, 0], [12, 0], [17, 0]];
        $schedules = [];

        for ($zone = 1; $zone <= config('nursery.zones'); $zone++) {
            $slots = [];
            for ($slot = 0; $slot < config('nursery.schedule_slots'); $slot++) {
                [$hour, $minute] = $times[$slot] ?? [6, 0];
                $slots[] = ['active' => false, 'hour' => $hour, 'minute' => $minute, 'duration' => 10];
            }
            $schedules[] = ['zone' => $zone, 'slots' => $slots];
        }

        return $schedules;
    }

    public function sysConfig(): array
    {
        return array_merge(self::DEFAULT_SYS_CONFIG, $this->sys_config ?? []);
    }

    /**
     * Tandai konfigurasi berubah dari web, agar dikirim ulang ke alat.
     */
    public function markChangedBy(User $user): void
    {
        $this->command_version = ($this->command_version ?? 0) + 1;
        $this->updated_by_user_id = $user->id;
    }

    /**
     * Isi `device_command` pada balasan telemetry (cloud_task.cpp: applyDeviceCommand)
     */
    public function toDeviceCommand(): array
    {
        $sys = $this->sys_config ?? [];

        return array_filter([
            'version' => $this->command_version ?? 0,
            'system_mode' => $this->system_mode,
            'manual_mode_type' => $this->manual_mode_type,
            'manual_duration' => $this->manual_duration,
            'sensor_interval' => $sys['sensor_interval'] ?? null,
            'log_interval' => $sys['log_interval'] ?? null,
            'cooldown' => $sys['cooldown'] ?? null,
            'schedules' => $this->schedules_json,
        ], fn ($value) => $value !== null);
    }

    /**
     * Salin konfigurasi yang dilaporkan alat (mis. diubah lewat dashboard lokal)
     * ke web. Hanya dipanggil jika alat sudah menerapkan versi terbaru dari web.
     *
     * @param  array  $state  system_mode, config (format /api/config firmware), schedules
     */
    public function applyDeviceState(array $state): void
    {
        if (isset($state['system_mode'])) {
            $this->system_mode = $state['system_mode'];
        }

        $config = $state['config'] ?? [];
        if (isset($config['manualMode'])) {
            $this->manual_mode_type = $config['manualMode'];
        }
        if (isset($config['manualDuration'])) {
            $this->manual_duration = $config['manualDuration'];
        }

        $sys = array_filter([
            'sensor_interval' => $config['sensorInterval'] ?? null,
            'log_interval' => $config['logInterval'] ?? null,
            'cooldown' => $config['cooldown'] ?? null,
        ], fn ($value) => $value !== null);
        if ($sys) {
            $this->sys_config = array_merge($this->sys_config ?? [], $sys);
        }

        if (isset($state['schedules'])) {
            $this->schedules_json = $state['schedules'];
        }

        // Diubah dari perangkat, bukan oleh user web
        if ($this->isDirty(self::CONFIG_FIELDS)) {
            $this->updated_by_user_id = null;
        }
    }

    /**
     * @param  string|int  $relay  "main" (pompa utama) atau nomor zona
     */
    public function queueRelay(string|int $relay, bool $on): void
    {
        $pending = $this->pending_relays ?? [];
        $pending[(string) $relay] = ['on' => $on, 'at' => now()->toIso8601String()];
        $this->pending_relays = $pending;
    }

    /**
     * Perintah relay yang belum terkirim, untuk tampilan dashboard.
     *
     * @return array<string|int, bool>  relay => status yang diminta
     */
    public function pendingRelayStates(): array
    {
        return $this->freshPendingRelays()->map(fn ($command) => (bool) $command['on'])->all();
    }

    /**
     * Ambil perintah relay yang masih berlaku lalu kosongkan antrean.
     *
     * @return array<int, array{relay: string|int, on: bool}>
     */
    public function takePendingRelays(): array
    {
        $fresh = $this->freshPendingRelays();
        if ($this->pending_relays !== null) {
            $this->pending_relays = null;
        }

        return $fresh
            ->map(fn ($command, $relay) => [
                'relay' => $relay === 'main' ? 'main' : (int) $relay,
                'on' => (bool) $command['on'],
            ])
            ->values()
            ->all();
    }

    private function freshPendingRelays(): Collection
    {
        $oldest = now()->subSeconds(self::RELAY_COMMAND_TTL_SECONDS);

        return collect($this->pending_relays ?? [])
            ->filter(fn ($command) => Carbon::parse($command['at'])->greaterThanOrEqualTo($oldest));
    }
}
