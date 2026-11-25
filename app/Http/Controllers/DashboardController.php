<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DeviceControl;
use App\Models\DeviceState;
use App\Models\FirmwareRelease;
use App\Models\SensorLog;
use App\Models\ZoneLog;
use App\Support\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    private const MODE_NAMES = [0 => 'Auto (Fuzzy)', 1 => 'Manual', 2 => 'Schedule'];

    /** Export ikut membaca riwayat sejauh ini (detik) sebelum awal rentang, untuk durasi siram yang melewati awal rentang */
    private const EXPORT_LOOKBACK = 3600;

    public function index()
    {
        $deviceControl = DeviceControl::current()->load('updatedBy');
        $latest = $this->latestSnapshot(DeviceState::where('device_key', DeviceControl::DEVICE_KEY)->first());
        $recentLogs = ActivityLog::with('user')->latest()->take(10)->get();

        return view('dashboard', compact('deviceControl', 'latest', 'recentLogs'));
    }

    public function getStatus(): JsonResponse
    {
        $deviceControl = DeviceControl::current()->load('updatedBy:id,name');
        $deviceState = DeviceState::where('device_key', DeviceControl::DEVICE_KEY)->first();
        $latest = $this->latestSnapshot($deviceState);

        $recentLogs = ActivityLog::with('user:id,name')->latest()->take(10)->get();

        return response()->json([
            'status' => 'success',
            'device_control' => [
                'system_mode' => $deviceControl->system_mode,
                'manual_mode_type' => $deviceControl->manual_mode_type,
                'manual_duration' => $deviceControl->manual_duration,
                'schedules' => $deviceControl->schedules_json,
                'sys_config' => $deviceControl->sysConfig(),
                'pending_relays' => (object) $deviceControl->pendingRelayStates(),
                'updated_by' => $deviceControl->updatedBy ? $deviceControl->updatedBy->name : 'System',
                'updated_at' => $deviceControl->updated_at ? $deviceControl->updated_at->diffForHumans() : 'Belum pernah',
                'updated_at_exact' => $deviceControl->updated_at ? $deviceControl->updated_at->format('d M Y, H:i T') : '-',
            ],
            'device' => [
                'online' => $deviceState?->isOnline() ?? false,
                'last_seen' => $deviceState?->last_seen_at?->diffForHumans(),
                // null = firmware belum melaporkan versi (firmware lama)
                'synced' => $deviceState?->reported_version === null
                    ? null
                    : $deviceState->reported_version === $deviceControl->command_version,
                'clock' => $deviceState?->clockStatus(),
            ],
            // Kartu "Update Firmware (OTA)" di tab Config
            'firmware' => [
                // null = firmware alat belum mendukung OTA (belum melaporkan versi)
                'device_version' => $deviceState?->firmware_version,
                'ota' => $deviceState?->ota_status,
                'release' => ($release = FirmwareRelease::active()) ? [
                    'id' => $release->id,
                    'version' => $release->version,
                    'size' => $release->size,
                    'uploaded_at' => $release->created_at->format('d/m/Y H:i'),
                ] : null,
            ],
            'latest_sensor' => $latest
                ? array_merge($latest, ['recorded_at' => Carbon::parse($latest['recorded_at'])->format('d/m H:i:s')])
                : null,
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
        $deviceControl = DeviceControl::current();

        $oldMode = $deviceControl->system_mode;
        $deviceControl->system_mode = $validated['system_mode'];

        if (isset($validated['manual_mode_type'])) {
            $deviceControl->manual_mode_type = $validated['manual_mode_type'];
        }
        if (isset($validated['manual_duration'])) {
            $deviceControl->manual_duration = $validated['manual_duration'];
        }

        // Firmware mematikan semua relay saat mode berganti, jadi perintah relay lama tidak berlaku
        if ($oldMode !== $deviceControl->system_mode) {
            $deviceControl->pending_relays = null;
        }

        $deviceControl->markChangedBy($user);
        $deviceControl->save();

        $newModeName = self::MODE_NAMES[$validated['system_mode']] ?? 'Unknown';

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

    /**
     * Perintah relay dikirim ke alat pada balasan telemetry berikutnya (≤ 5 detik).
     * Firmware hanya menerima perintah relay pada mode Manual.
     */
    public function toggleRelay(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'relay' => ['required', Rule::in(array_merge(['main'], range(1, config('nursery.zones'))))],
            'on' => 'required|boolean',
        ]);

        $user = Auth::user();
        $deviceControl = DeviceControl::current();

        if ($deviceControl->system_mode !== 1) {
            return response()->json([
                'status' => 'error',
                'message' => 'Relay hanya bisa dikontrol pada mode Manual.',
            ], 409);
        }

        $relay = $validated['relay'] === 'main' ? 'main' : (int) $validated['relay'];
        $on = (bool) $validated['on'];

        $deviceControl->queueRelay($relay, $on);
        $deviceControl->updated_by_user_id = $user->id;
        $deviceControl->save();

        $target = $relay === 'main' ? 'Pompa Utama' : "Valve Relay Zona {$relay}";
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => ($on ? 'Menyalakan ' : 'Mematikan ') . $target,
            'details_json' => ['relay' => $relay, 'on' => $on],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Perintah dikirim ke alat oleh {$user->name}",
            'updated_by' => $user->name,
            'updated_at' => now()->format('d M Y, H:i T'),
        ]);
    }

    public function saveSchedule(Request $request): JsonResponse
    {
        // Format & rentang sama dengan firmware (settings_store.cpp: scheduleFromJson).
        // Firmware menolak seluruh jadwal jika ada satu nilai di luar rentang.
        $zones = config('nursery.zones');
        $validated = $request->validate([
            'schedules' => "required|array|size:{$zones}",
            'schedules.*.zone' => "required|integer|distinct|min:1|max:{$zones}",
            'schedules.*.slots' => 'required|array|size:' . config('nursery.schedule_slots'),
            'schedules.*.slots.*.active' => 'required|boolean',
            'schedules.*.slots.*.hour' => 'nullable|required_if:schedules.*.slots.*.active,true|integer|min:0|max:23',
            'schedules.*.slots.*.minute' => 'nullable|required_if:schedules.*.slots.*.active,true|integer|min:0|max:59',
            'schedules.*.slots.*.duration' => 'nullable|required_if:schedules.*.slots.*.active,true|integer|min:1|max:3600',
        ], [
            'schedules.*.slots.*.*.required_if' => 'Jam dan durasi wajib diisi untuk jadwal yang aktif.',
        ]);

        $user = Auth::user();
        $deviceControl = DeviceControl::current();

        $deviceControl->schedules_json = $validated['schedules'];
        $deviceControl->markChangedBy($user);
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

    // ── Sys Config ────────────────────────────────────────────
    public function saveSysConfig(Request $request): JsonResponse
    {
        // Rentang sama dengan firmware (settings_store.cpp: settingsFromJson)
        $validated = $request->validate([
            'sensor_interval' => 'required|integer|min:2|max:3600',
            'log_interval'    => 'required|integer|min:60|max:86400',
            'cooldown'        => 'required|integer|min:0|max:86400',
        ]);

        $user = Auth::user();
        $deviceControl = DeviceControl::current();

        $deviceControl->sys_config = $validated;
        $deviceControl->markChangedBy($user);
        $deviceControl->save();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => "Memperbarui Konfigurasi Sistem (Interval/Cooldown)",
            'details_json' => $validated,
        ]);

        return response()->json(['status' => 'success']);
    }

    // ── Export CSV / XLSX ─────────────────────────────────────
    /**
     * Per hari: ?date=2026-10-11 (default hari ini).
     * Rentang:  ?from=2026-10-01&to=2026-10-11
     * Diurutkan berdasarkan waktu rekam alat (recorded_at), termasuk data dari buffer offline.
     */
    public function exportCsv(Request $request)
    {
        [$from, $to, $basename] = $this->exportRange($request);

        $callback = function () use ($from, $to) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");   // BOM agar Excel membaca UTF-8 (simbol °)

            fputcsv($handle, $this->exportHeader());
            foreach ($this->exportRows($from, $to) as $row) {
                $row[0] = $row[0]?->format('Y-m-d H:i:s');
                fputcsv($handle, array_map(fn ($value) => $value ?? '', $row));
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$basename}.csv\"",
        ]);
    }

    /** Sama dengan CSV, tetapi angka tetap angka dan Waktu berupa tanggal Excel (bisa diurutkan / difilter) */
    public function exportXlsx(Request $request)
    {
        [$from, $to, $basename] = $this->exportRange($request);

        $header = $this->exportHeader();
        $widths = array_map(fn ($title) => max(10, mb_strlen($title) + 2), $header);
        $widths[0] = 20;

        $xlsx = new XlsxWriter('Log Sensor', $widths);
        $xlsx->addRow($header, XlsxWriter::STYLE_HEADER);
        foreach ($this->exportRows($from, $to) as $row) {
            $xlsx->addRow($row);
        }

        return response()->download($xlsx->finish(), "{$basename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** @return array{0: Carbon, 1: Carbon, 2: string} awal, akhir, nama file tanpa ekstensi */
    private function exportRange(Request $request): array
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
            'from' => 'nullable|date_format:Y-m-d|required_with:to',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        if (isset($validated['from'])) {
            $from = Carbon::parse($validated['from'])->startOfDay();
            $to = Carbon::parse($validated['to'] ?? $validated['from'])->endOfDay();
        } else {
            $from = Carbon::parse($validated['date'] ?? now())->startOfDay();
            $to = $from->copy()->endOfDay();
        }

        $basename = $from->isSameDay($to)
            ? "smart_nursery_log_{$from->toDateString()}"
            : "smart_nursery_log_{$from->toDateString()}_sd_{$to->toDateString()}";

        return [$from, $to, $basename];
    }

    private function exportHeader(): array
    {
        $header = ['Waktu', 'Suhu (°C)', 'Kelembaban Udara (%)', 'Cahaya (lx)'];
        foreach (range(1, config('nursery.zones')) as $zone) {
            array_push($header, "Zona {$zone} Tanah (%)", "Zona {$zone} Valve", "Zona {$zone} Status", "Zona {$zone} Durasi Siram (detik)");
        }

        return array_merge($header, ['Pompa', 'Mode', 'Sumber']);
    }

    /**
     * Satu baris per log, kolom sesuai exportHeader(). Kolom pertama Carbon (atau null), nilai kosong null.
     * Diambil per 500 baris agar memori tetap kecil walau rentangnya panjang.
     *
     * Durasi siram diisi di baris saat valve zona kembali OFF: lama valve ON sesi itu (detik),
     * dihitung sama seperti Rekap Penyiraman di tab Statistik.
     */
    private function exportRows(Carbon $from, Carbon $to): \Generator
    {
        $zoneCount = config('nursery.zones');
        $maxGap = ZoneLog::maxValveGap();
        $valves = [];   // zone_id => [waktu baris terakhir, valve ON, detik ON sesi berjalan]

        $logs = SensorLog::with('zoneLogs')
            ->whereBetween('recorded_at', [$from->copy()->subSeconds(self::EXPORT_LOOKBACK), $to])
            ->orderBy('recorded_at')->orderBy('id')
            ->lazy(500);

        foreach ($logs as $log) {
            $timestamp = $log->recorded_at->getTimestamp();
            $zones = $log->zoneLogs->keyBy('zone_id');

            $durations = [];
            foreach ($zones as $zoneId => $z) {
                [$prevTimestamp, $prevOn, $seconds] = $valves[$zoneId] ?? [null, false, 0];
                if ($prevOn) {
                    $seconds += min($timestamp - $prevTimestamp, $maxGap);
                    if (!$z->relay_status) {
                        $durations[$zoneId] = $seconds;
                    }
                }
                $valves[$zoneId] = [$timestamp, $z->relay_status, $z->relay_status ? $seconds : 0];
            }

            if ($log->recorded_at->lt($from)) {
                continue;   // riwayat sebelum rentang hanya untuk durasi siram
            }

            $row = [$log->recorded_at, $log->temperature, $log->humidity, $log->light_level];
            foreach (range(1, $zoneCount) as $zone) {
                $z = $zones->get($zone);
                array_push($row,
                    $z?->soil_moisture,
                    $z ? ($z->relay_status ? 'ON' : 'OFF') : null,
                    $z?->state,
                    $durations[$zone] ?? null,
                );
            }
            array_push($row,
                $log->relay_main ? 'ON' : 'OFF',
                self::MODE_NAMES[$log->system_mode] ?? $log->system_mode,
                $log->is_offline_data ? 'Offline' : 'Live',
            );

            yield $row;
        }
    }

    // ── Reset Log ─────────────────────────────────────────────
    public function resetLog(): JsonResponse
    {
        $user = Auth::user();
        SensorLog::query()->delete();

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => "Menghapus Semua Data Log Sensor",
        ]);

        return response()->json(['status' => 'success']);
    }

    /**
     * Data live terakhir dari alat. Sebelum ada telemetry baru (mis. tepat setelah
     * deploy), pakai riwayat live terakhir; data batch offline tidak dipakai karena lama.
     */
    private function latestSnapshot(?DeviceState $deviceState): ?array
    {
        if ($deviceState?->state) {
            return $deviceState->state;
        }

        return SensorLog::with('zoneLogs')
            ->where('is_offline_data', false)
            ->latest('id')
            ->first()
            ?->toSnapshot();
    }
}
