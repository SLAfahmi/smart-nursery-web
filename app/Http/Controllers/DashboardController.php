<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DeviceControl;
use App\Models\SensorLog;
use App\Models\ZoneLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $deviceControl = DeviceControl::with('updatedBy')->where('device_key', 'NURSERY_01')->first();

        if (!$deviceControl) {
            $defaultSchedules = [];
            for ($i = 0; $i < 5; $i++) {
                $defaultSchedules[] = [
                    'schedules' => [
                        ['active' => false, 'hour' => 7, 'minute' => 0, 'duration' => 10],
                        ['active' => false, 'hour' => 12, 'minute' => 0, 'duration' => 10],
                        ['active' => false, 'hour' => 17, 'minute' => 0, 'duration' => 10],
                    ]
                ];
            }

            $deviceControl = DeviceControl::create([
                'device_key' => 'NURSERY_01',
                'system_mode' => 0,
                'manual_mode_type' => 0,
                'manual_duration' => 10,
                'schedules_json' => $defaultSchedules,
                'updated_by_user_id' => Auth::id(),
            ]);
        }

        $latestSensor = SensorLog::with('zoneLogs')->latest()->first();
        $recentLogs = ActivityLog::with('user')->latest()->take(10)->get();

        return view('dashboard', compact('deviceControl', 'latestSensor', 'recentLogs'));
    }

    public function getStatus(): JsonResponse
    {
        $deviceControl = DeviceControl::with('updatedBy:id,name')->where('device_key', 'NURSERY_01')->first();
        $latestSensor = SensorLog::with(['zoneLogs' => function($q) {
            $q->orderBy('zone_id', 'asc');
        }])->latest()->first();

        $recentLogs = ActivityLog::with('user:id,name')->latest()->take(10)->get();

        return response()->json([
            'status' => 'success',
            'device_control' => [
                'system_mode' => $deviceControl ? $deviceControl->system_mode : 0,
                'manual_mode_type' => $deviceControl ? $deviceControl->manual_mode_type : 0,
                'manual_duration' => $deviceControl ? $deviceControl->manual_duration : 10,
                'schedules' => $deviceControl ? $deviceControl->schedules_json : [],
                'updated_by' => $deviceControl && $deviceControl->updatedBy ? $deviceControl->updatedBy->name : 'System',
                'updated_at' => $deviceControl && $deviceControl->updated_at ? $deviceControl->updated_at->diffForHumans() : 'Belum pernah',
                'updated_at_exact' => $deviceControl && $deviceControl->updated_at ? $deviceControl->updated_at->format('d M Y, H:i T') : '-',
            ],
            'latest_sensor' => $latestSensor ? [
                'temperature' => $latestSensor->temperature,
                'humidity' => $latestSensor->humidity,
                'light_level' => $latestSensor->light_level,
                'system_mode' => $latestSensor->system_mode,
                'recorded_at' => $latestSensor->recorded_at ? $latestSensor->recorded_at->format('H:i:s') : $latestSensor->created_at->format('H:i:s'),
                'zones' => $latestSensor->zoneLogs,
            ] : null,
            'recent_logs' => $recentLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user ? $log->user->name : 'System',
                    'action' => $log->action,
                    'time' => $log->created_at->diffForHumans(),
                    'time_exact' => $log->created_at->format('d/m/Y H:i:s'),
                ];
            }),
        ]);
    }

    public function updateMode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'system_mode' => 'required|integer|in:0,1,2',
            'manual_mode_type' => 'nullable|integer|in:0,1',
            'manual_duration' => 'nullable|integer|min:1|max:3600',
        ]);

        $user = Auth::user();
        $deviceControl = DeviceControl::where('device_key', 'NURSERY_01')->first();

        if (!$deviceControl) {
            $deviceControl = new DeviceControl(['device_key' => 'NURSERY_01']);
        }

        $oldMode = $deviceControl->system_mode;
        $deviceControl->system_mode = $validated['system_mode'];

        if (isset($validated['manual_mode_type'])) {
            $deviceControl->manual_mode_type = $validated['manual_mode_type'];
        }
        if (isset($validated['manual_duration'])) {
            $deviceControl->manual_duration = $validated['manual_duration'];
        }

        $deviceControl->updated_by_user_id = $user->id;
        $deviceControl->save();

        $modeNames = [0 => 'Auto (Fuzzy)', 1 => 'Manual', 2 => 'Schedule'];
        $newModeName = $modeNames[$validated['system_mode']] ?? 'Unknown';

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => "Mengubah Mode Sistem ke {$newModeName}",
            'details_json' => [
                'old_mode' => $oldMode,
                'new_mode' => $validated['system_mode'],
                'manual_mode_type' => $deviceControl->manual_mode_type,
                'manual_duration' => $deviceControl->manual_duration,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Mode berhasil diubah ke {$newModeName} oleh {$user->name}",
            'updated_by' => $user->name,
            'updated_at' => now()->format('d M Y, H:i T'),
        ]);
    }

    public function toggleRelay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zone_id' => 'required|integer|min:1|max:5',
            'relay_status' => 'required|boolean',
        ]);

        $user = Auth::user();
        $deviceControl = DeviceControl::where('device_key', 'NURSERY_01')->first();

        if ($deviceControl) {
            $deviceControl->updated_by_user_id = $user->id;
            $deviceControl->save();
        }

        $actionStr = $validated['relay_status']
            ? "Menyalakan Valve Relay Zona {$validated['zone_id']}"
            : "Mematikan Valve Relay Zona {$validated['zone_id']}";

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => $actionStr,
            'details_json' => [
                'zone_id' => $validated['zone_id'],
                'relay_status' => $validated['relay_status'],
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Aksi berhasil dikirim oleh {$user->name}",
            'updated_by' => $user->name,
            'updated_at' => now()->format('d M Y, H:i T'),
        ]);
    }

    public function saveSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedules' => 'required|array|size:5',
        ]);

        $user = Auth::user();
        $deviceControl = DeviceControl::where('device_key', 'NURSERY_01')->first();

        if (!$deviceControl) {
            $deviceControl = new DeviceControl(['device_key' => 'NURSERY_01']);
        }

        $deviceControl->schedules_json = $validated['schedules'];
        $deviceControl->updated_by_user_id = $user->id;
        $deviceControl->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => "Memperbarui Konfigurasi Jadwal Penyiraman",
            'details_json' => ['schedules' => $validated['schedules']],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Jadwal berhasil disimpan oleh {$user->name}",
            'updated_by' => $user->name,
            'updated_at' => now()->format('d M Y, H:i T'),
        ]);
    }
}
