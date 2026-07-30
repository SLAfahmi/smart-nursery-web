<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceControl;
use App\Models\SensorLog;
use App\Models\ZoneLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelemetryController extends Controller
{
    /**
     * Store live telemetry from ESP32
     * POST /api/v1/telemetry
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_key' => 'nullable|string',
            'temperature' => 'required|numeric',
            'humidity' => 'required|numeric',
            'light_level' => 'required|numeric',
            'system_mode' => 'nullable|integer',
            'is_offline_data' => 'nullable|boolean',
            'recorded_at' => 'nullable|date',
            'zones' => 'required|array|min:1|max:5',
            'zones.*.zone_id' => 'required|integer|min:1|max:5',
            'zones.*.soil_moisture' => 'required|integer',
            'zones.*.zone_mode' => 'nullable|integer',
            'zones.*.duration' => 'nullable|integer',
            'zones.*.relay_status' => 'nullable|boolean',
        ]);

        $deviceKey = $validated['device_key'] ?? 'NURSERY_01';

        try {
            DB::beginTransaction();

            $sensorLog = SensorLog::create([
                'temperature' => $validated['temperature'],
                'humidity' => $validated['humidity'],
                'light_level' => (int)$validated['light_level'],
                'system_mode' => $validated['system_mode'] ?? 0,
                'is_offline_data' => $validated['is_offline_data'] ?? false,
                'recorded_at' => $validated['recorded_at'] ?? now(),
            ]);

            foreach ($validated['zones'] as $zone) {
                ZoneLog::create([
                    'sensor_log_id' => $sensorLog->id,
                    'zone_id' => $zone['zone_id'],
                    'soil_moisture' => (int)$zone['soil_moisture'],
                    'zone_mode' => $zone['zone_mode'] ?? 0,
                    'duration' => $zone['duration'] ?? 0,
                    'relay_status' => $zone['relay_status'] ?? false,
                ]);
            }

            DB::commit();

            // Fetch device command status to return to ESP32
            $deviceControl = DeviceControl::where('device_key', $deviceKey)->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Telemetry saved successfully',
                'sensor_log_id' => $sensorLog->id,
                'device_command' => $deviceControl ? [
                    'system_mode' => $deviceControl->system_mode,
                    'manual_mode_type' => $deviceControl->manual_mode_type,
                    'manual_duration' => $deviceControl->manual_duration,
                    'schedules' => $deviceControl->schedules_json,
                ] : null,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Telemetry save error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to save telemetry data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store offline batch telemetry buffer from ESP32
     * POST /api/v1/telemetry/sync-batch
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_key' => 'nullable|string',
            'batch' => 'required|array|min:1',
            'batch.*.temperature' => 'required|numeric',
            'batch.*.humidity' => 'required|numeric',
            'batch.*.light_level' => 'required|numeric',
            'batch.*.system_mode' => 'nullable|integer',
            'batch.*.recorded_at' => 'nullable|date',
            'batch.*.zones' => 'required|array|min:1|max:5',
            'batch.*.zones.*.zone_id' => 'required|integer|min:1|max:5',
            'batch.*.zones.*.soil_moisture' => 'required|integer',
            'batch.*.zones.*.zone_mode' => 'nullable|integer',
            'batch.*.zones.*.duration' => 'nullable|integer',
            'batch.*.zones.*.relay_status' => 'nullable|boolean',
        ]);

        $insertedCount = 0;

        try {
            DB::beginTransaction();

            foreach ($validated['batch'] as $item) {
                $sensorLog = SensorLog::create([
                    'temperature' => $item['temperature'],
                    'humidity' => $item['humidity'],
                    'light_level' => (int)$item['light_level'],
                    'system_mode' => $item['system_mode'] ?? 0,
                    'is_offline_data' => true,
                    'recorded_at' => $item['recorded_at'] ?? now(),
                ]);

                foreach ($item['zones'] as $zone) {
                    ZoneLog::create([
                        'sensor_log_id' => $sensorLog->id,
                        'zone_id' => $zone['zone_id'],
                        'soil_moisture' => (int)$zone['soil_moisture'],
                        'zone_mode' => $zone['zone_mode'] ?? 0,
                        'duration' => $zone['duration'] ?? 0,
                        'relay_status' => $zone['relay_status'] ?? false,
                    ]);
                }

                $insertedCount++;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "Batch sync successful. Inserted {$insertedCount} records.",
                'synced_count' => $insertedCount,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch sync error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to sync offline batch data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current device command & mode for ESP32
     * GET /api/v1/device-command
     */
    public function getCommand(Request $request): JsonResponse
    {
        $deviceKey = $request->query('device_key', 'NURSERY_01');

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
}
