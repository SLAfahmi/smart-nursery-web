<?php

namespace App\Http\Controllers;

use App\Models\ZoneLog;
use App\Support\RunningStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Data tab Statistik: ringkasan sensor, tren per bucket waktu, dan rekap penyiraman per zona.
 * Dihitung di PHP (bukan fungsi tanggal SQL) agar sama di MySQL hosting dan SQLite test.
 */
class StatisticsController extends Controller
{
    /** Ukuran bucket (detik) per rentang */
    private const BUCKETS = ['24h' => 3600, '7d' => 10800, '30d' => 86400];

    /** Kolom iklim di sensor_logs => jumlah desimal */
    private const CLIMATE = ['temperature' => 1, 'humidity' => 1, 'light_level' => 0];

    private Carbon $from;
    private Carbon $to;
    private int $step;
    private int $bucketCount;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'range' => 'nullable|in:' . implode(',', array_keys(self::BUCKETS)),
        ]);

        $range = $validated['range'] ?? '24h';
        $this->step = self::BUCKETS[$range];
        $this->to = now();
        $this->from = match ($range) {
            '24h' => $this->to->copy()->startOfHour()->subHours(23),
            '7d' => $this->to->copy()->startOfDay()->subDays(6),
            '30d' => $this->to->copy()->startOfDay()->subDays(29),
        };
        // Sampai bucket yang memuat "sekarang"; tidak ada bucket di masa depan
        $this->bucketCount = intdiv($this->to->getTimestamp() - $this->from->getTimestamp(), $this->step) + 1;

        $climate = $this->climateStats();
        $zones = $this->zoneStats();

        return response()->json([
            'status' => 'success',
            'range' => $range,
            'labels' => $this->labels($range),
            'summary' => $climate['summary'] + ['soil' => $zones['soil_summary']],
            'series' => $climate['series'] + ['soil' => $zones['soil_series']],
            'irrigation' => $zones['irrigation'],
            'record_count' => $climate['record_count'],
            'offline_count' => $climate['offline_count'],
            'generated_at' => $this->to->format('d/m H:i'),
        ]);
    }

    /** Suhu, kelembaban udara, dan cahaya dari sensor_logs */
    private function climateStats(): array
    {
        $summary = [];
        $perBucket = [];
        foreach (self::CLIMATE as $metric => $digits) {
            $summary[$metric] = new RunningStat();
            $perBucket[$metric] = $this->emptyBuckets();
        }

        $recordCount = 0;
        $offlineCount = 0;
        $rows = DB::table('sensor_logs')
            ->whereBetween('recorded_at', [$this->from, $this->to])
            ->select('recorded_at', 'temperature', 'humidity', 'light_level', 'is_offline_data')
            ->cursor();

        foreach ($rows as $row) {
            $recordCount++;
            $offlineCount += $row->is_offline_data ? 1 : 0;
            $index = $this->bucketIndex(strtotime($row->recorded_at));

            foreach (self::CLIMATE as $metric => $digits) {
                if ($row->{$metric} !== null) {
                    $summary[$metric]->add((float) $row->{$metric});
                    $perBucket[$metric][$index]->add((float) $row->{$metric});
                }
            }
        }

        $series = [];
        foreach (self::CLIMATE as $metric => $digits) {
            $series[$metric] = array_map(fn (RunningStat $stat) => $stat->avg($digits), $perBucket[$metric]);
            $summary[$metric] = $summary[$metric]->toArray($digits);
        }

        return [
            'summary' => $summary,
            'series' => $series,
            'record_count' => $recordCount,
            'offline_count' => $offlineCount,
        ];
    }

    /**
     * Kelembaban tanah dan penyiraman per zona dari zone_logs.
     * Lama valve ON = selisih waktu dari baris ON ke baris berikutnya zona itu (lihat ZoneLog::maxValveGap()).
     */
    private function zoneStats(): array
    {
        $maxGap = ZoneLog::maxValveGap();
        $zoneIds = range(1, config('nursery.zones'));

        $soilSummary = new RunningStat();
        $soilAvg = [];
        $soilBuckets = [];
        $irrigation = [];
        $previous = [];   // zone_id => [timestamp, valve ON]
        foreach ($zoneIds as $zone) {
            $soilAvg[$zone] = new RunningStat();
            $soilBuckets[$zone] = $this->emptyBuckets();
            $irrigation[$zone] = ['count' => 0, 'seconds' => 0];
        }

        // Urut waktu rekam alat: batch offline yang terkirim belakangan tetap masuk di urutannya
        $rows = DB::table('zone_logs')
            ->join('sensor_logs', 'sensor_logs.id', '=', 'zone_logs.sensor_log_id')
            ->whereBetween('sensor_logs.recorded_at', [$this->from, $this->to])
            ->orderBy('sensor_logs.recorded_at')->orderBy('sensor_logs.id')
            ->select('sensor_logs.recorded_at', 'zone_logs.zone_id', 'zone_logs.soil_moisture', 'zone_logs.relay_status')
            ->cursor();

        foreach ($rows as $row) {
            $zone = (int) $row->zone_id;
            if (!isset($irrigation[$zone])) {
                continue;   // zona di luar konfigurasi sekarang (NURSERY_ZONES diubah)
            }

            $timestamp = strtotime($row->recorded_at);
            $on = (bool) $row->relay_status;

            if ($row->soil_moisture !== null) {
                $soil = (float) $row->soil_moisture;
                $soilSummary->add($soil);
                $soilAvg[$zone]->add($soil);
                $soilBuckets[$zone][$this->bucketIndex($timestamp)]->add($soil);
            }

            [$prevTimestamp, $prevOn] = $previous[$zone] ?? [null, false];
            if ($prevOn) {
                $irrigation[$zone]['seconds'] += min($timestamp - $prevTimestamp, $maxGap);
            }
            if ($on && !$prevOn) {
                $irrigation[$zone]['count']++;
            }
            $previous[$zone] = [$timestamp, $on];
        }

        return [
            'soil_summary' => $soilSummary->toArray(1),
            'soil_series' => array_map(
                fn (array $stats) => array_map(fn (RunningStat $stat) => $stat->avg(1), $stats),
                $soilBuckets,
            ),
            'irrigation' => array_map(fn (int $zone) => [
                'zone' => $zone,
                'count' => $irrigation[$zone]['count'],
                'total_seconds' => $irrigation[$zone]['seconds'],
                'avg_seconds' => $irrigation[$zone]['count']
                    ? (int) round($irrigation[$zone]['seconds'] / $irrigation[$zone]['count'])
                    : null,
                'soil_avg' => $soilAvg[$zone]->avg(1),
            ], $zoneIds),
        ];
    }

    /** @return RunningStat[] satu akumulator per bucket */
    private function emptyBuckets(): array
    {
        return array_map(fn () => new RunningStat(), range(1, $this->bucketCount));
    }

    private function bucketIndex(int $timestamp): int
    {
        $index = intdiv($timestamp - $this->from->getTimestamp(), $this->step);

        return max(0, min($this->bucketCount - 1, $index));
    }

    /**
     * Label sumbu X (pendek) dan tooltip (lengkap) per bucket, diformat di server (zona waktu app).
     * Bucket terakhir selalu masih berjalan (memuat "sekarang"), jadi labelnya ditandai.
     */
    private function labels(string $range): array
    {
        $ticks = [];
        $tips = [];
        for ($i = 0; $i < $this->bucketCount; $i++) {
            $start = $this->from->copy()->addSeconds($i * $this->step);
            $current = $i === $this->bucketCount - 1;
            $ticks[] = $range === '24h' ? $start->format('H:i') : $start->format('d/m');
            $tips[] = $range === '30d'
                ? $start->format('d/m/Y') . ($current ? ' (hari ini)' : '')
                : $start->format('d/m H:i') . '–' . ($current ? 'sekarang' : $start->copy()->addSeconds($this->step)->format('H:i'));
        }

        return ['ticks' => $ticks, 'tips' => $tips];
    }
}
