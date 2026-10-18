<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceControl;
use App\Models\DeviceState;
use App\Models\SensorLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Endpoint untuk ESP32. Format payload mengikuti firmware (src/tasks/cloud_task.cpp).
 * Semua route di sini dilindungi middleware VerifyDeviceToken.
 */
class TelemetryController extends Controller
{
    private const DEVICE_KEY = DeviceControl::DEVICE_KEY;

    /**
     * Store live telemetry from ESP32
     * POST /api/v1/telemetry
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->telemetryRules());
        $deviceState = $this->deviceState($request);

        $snapshot = DeviceState::snapshotFromTelemetry($validated);

        try {
            [$sensorLog, $command] = DB::transaction(function () use ($validated, $deviceState, $snapshot) {
                $previous = DeviceState::where('device_key', self::DEVICE_KEY)->first()?->state;

                $sensorLog = $this->shouldStoreHistory($previous, $snapshot)
                    ? $this->saveTelemetry($validated, false)
                    : null;

                return [$sensorLog, $this->syncDevice($validated, $deviceState, $snapshot)];
            });
        } catch (\Throwable $e) {
            Log::error('Telemetry save error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save telemetry data',
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Telemetry saved successfully',
            'sensor_log_id' => $sensorLog?->id,
            'device_command' => $command,
        ], 201);
    }

    /**
     * Store offline batch telemetry buffer from ESP32: {"data": [<telemetry>, ...]}
     * POST /api/v1/telemetry/sync-batch
     *
     * Firmware mengirim ulang seluruh buffer sampai mendapat balasan 2xx, jadi data
     * yang tidak valid dilewati (dicatat di log) agar tidak menahan data lainnya.
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $request->validate(['data' => 'required|array|min:1|max:500']);

        $items = [];
        $skippedCount = 0;

        foreach ($request->input('data') as $index => $item) {
            $validator = Validator::make(is_array($item) ? $item : [], $this->telemetryRules());
            if ($validator->fails()) {
                $skippedCount++;
                Log::warning("Batch telemetry #{$index} dilewati", $validator->errors()->toArray());
                continue;
            }
            $items[] = $validator->validated();
        }

        try {
            DB::transaction(function () use ($items) {
                foreach ($items as $item) {
                    $this->saveTelemetry($item, true);
                }
            });
        } catch (\Throwable $e) {
            Log::error('Batch sync error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync offline batch data',
            ], 500);
        }

        $insertedCount = count($items);

        return response()->json([
            'status' => 'success',
            'message' => "Batch sync successful. Inserted {$insertedCount} records.",
            'synced_count' => $insertedCount,
            'skipped_count' => $skippedCount,
        ], 200);
    }

    /**
     * Get current device command & mode for ESP32
     * GET /api/v1/device-command
     */
    public function getCommand(Request $request): JsonResponse
    {
        $deviceKey = $request->query('device_key', self::DEVICE_KEY);

        $control = DeviceControl::with('updatedBy:id,name')
            ->where('device_key', $deviceKey)
            ->first();

        if (!$control) {
            return response()->json([
                'status' => 'error',
                'message' => 'Device control settings not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'device_key' => $control->device_key,
            'system_mode' => $control->system_mode,
            'manual_mode_type' => $control->manual_mode_type,
            'manual_duration' => $control->manual_duration,
            'schedules' => $control->schedules_json,
            'updated_by' => $control->updatedBy ? $control->updatedBy->name : 'System',
            'updated_at' => $control->updated_at ? $control->updated_at->toIso8601String() : null,
        ]);
    }

    /**
     * Aturan validasi satu payload telemetry.
     * Nilai sensor boleh null: firmware mengirim null saat sensor error / tidak terpasang.
     */
    private function telemetryRules(): array
    {
        $zones = config('nursery.zones');

        return [
            'recorded_at' => 'nullable|date',
            'temperature' => 'nullable|numeric',
            'humidity' => 'nullable|numeric',
            'light' => 'nullable|numeric',
            'system_mode' => 'nullable|integer|in:0,1,2',
            'relay_main' => 'nullable|boolean',
            'zones' => "required|array|min:1|max:{$zones}",
            'zones.*.zone' => "required|integer|distinct|min:1|max:{$zones}",
            'zones.*.soil' => 'nullable|numeric',
            'zones.*.relay' => 'nullable|boolean',
            'zones.*.state' => 'nullable|string|max:16',
            'zones.*.fuzzy_duration' => 'nullable|numeric',
        ];
    }

    /**
     * Status konfigurasi yang dilaporkan alat: command_version, config, schedules.
     * Jika tidak valid, diabaikan (telemetry tetap disimpan dan perintah dikirim penuh).
     */
    private function deviceState(Request $request): array
    {
        $zones = config('nursery.zones');

        $validator = Validator::make($request->only(['command_version', 'config', 'schedules']), [
            'command_version' => 'nullable|integer|min:0',
            'config' => 'nullable|array',
            'config.sensorInterval' => 'nullable|integer|min:2|max:3600',
            'config.manualMode' => 'nullable|integer|in:0,1',
            'config.manualDuration' => 'nullable|integer|min:1|max:3600',
            'config.logInterval' => 'nullable|integer|min:60|max:86400',
            'config.cooldown' => 'nullable|integer|min:0|max:86400',
            'schedules' => "nullable|array|size:{$zones}",
            'schedules.*.zone' => "required|integer|distinct|min:1|max:{$zones}",
            'schedules.*.slots' => 'required|array|size:' . config('nursery.schedule_slots'),
            'schedules.*.slots.*.active' => 'required|boolean',
            'schedules.*.slots.*.hour' => 'required|integer|min:0|max:23',
            'schedules.*.slots.*.minute' => 'required|integer|min:0|max:59',
            'schedules.*.slots.*.duration' => 'required|integer|min:1|max:3600',
        ]);

        if ($validator->fails()) {
            Log::warning('Status konfigurasi dari alat diabaikan', $validator->errors()->toArray());

            return [];
        }

        return $validator->validated();
    }

    /**
     * Catat kondisi alat dan tentukan isi device_command.
     *
     * - Versi alat ≠ versi web (atau firmware lama tanpa versi): kirim konfigurasi penuh.
     * - Versi sama: alat sudah up to date; salin konfigurasi alat ke web, karena bisa
     *   saja diubah lewat dashboard lokal (WiFi AP).
     * - Perintah relay dikirim sekali, terpisah dari versi.
     */
    private function syncDevice(array $telemetry, array $deviceState, array $snapshot): ?array
    {
        $control = DeviceControl::where('device_key', self::DEVICE_KEY)->lockForUpdate()->first()
            ?? DeviceControl::current();
        $reportedVersion = $deviceState['command_version'] ?? null;

        if ($reportedVersion !== null && $reportedVersion === $control->command_version) {
            $control->applyDeviceState(['system_mode' => $telemetry['system_mode'] ?? null] + $deviceState);
        }

        $command = ($reportedVersion === $control->command_version) ? [] : $control->toDeviceCommand();

        $relays = $control->takePendingRelays();
        if ($relays) {
            $command['relays'] = $relays;
        }

        // updated_at hanya berubah jika konfigurasi berubah (dipakai untuk "Terakhir diubah")
        $control->timestamps = $control->isDirty(DeviceControl::CONFIG_FIELDS);
        $control->save();

        DeviceState::updateOrCreate(['device_key' => self::DEVICE_KEY], [
            'reported_version' => $reportedVersion,
            'last_seen_at' => now(),
            'state' => $snapshot,
        ]);

        return $command ?: null;
    }

    /**
     * Telemetry live tiap 5 detik tidak semuanya disimpan sebagai riwayat. Simpan jika:
     * - sudah lewat history_interval sejak riwayat live terakhir, atau
     * - status relay / zona / mode berubah (agar penyiraman singkat tetap tercatat).
     */
    private function shouldStoreHistory(?array $previous, array $snapshot): bool
    {
        if ($previous === null || $this->statusSignature($previous) !== $this->statusSignature($snapshot)) {
            return true;
        }

        $lastStoredAt = SensorLog::where('device_key', self::DEVICE_KEY)
            ->where('is_offline_data', false)
            ->latest('id')
            ->first(['created_at'])
            ?->created_at;

        return $lastStoredAt === null
            || $lastStoredAt->lte(now()->subSeconds(config('nursery.history_interval')));
    }

    private function statusSignature(array $snapshot): array
    {
        return [
            $snapshot['system_mode'] ?? null,
            $snapshot['relay_main'] ?? null,
            collect($snapshot['zones'] ?? [])->map(fn ($zone) => [$zone['zone_id'], $zone['relay_status'], $zone['state']])->all(),
        ];
    }

    private function saveTelemetry(array $data, bool $isOffline): SensorLog
    {
        $sensorLog = SensorLog::create([
            'device_key' => self::DEVICE_KEY,
            'temperature' => $data['temperature'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'light_level' => $data['light'] ?? null,
            'system_mode' => $data['system_mode'] ?? 0,
            'relay_main' => $data['relay_main'] ?? false,
            'is_offline_data' => $isOffline,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);

        foreach ($data['zones'] as $zone) {
            $sensorLog->zoneLogs()->create([
                'zone_id' => $zone['zone'],
                'soil_moisture' => $zone['soil'] ?? null,
                'relay_status' => $zone['relay'] ?? false,
                'state' => $zone['state'] ?? 'idle',
                'fuzzy_duration' => $zone['fuzzy_duration'] ?? 0,
            ]);
        }

        return $sensorLog;
    }
}
