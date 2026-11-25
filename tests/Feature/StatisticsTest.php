<?php

namespace Tests\Feature;

use App\Models\SensorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tab Statistik: ringkasan, tren per bucket waktu, dan rekap penyiraman (StatisticsController).
 */
class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        config(['nursery.zones' => 3, 'nursery.history_interval' => 60]);
        // 24 jam = bucket per jam mulai 22/11 15:00
        $this->travelTo(Carbon::parse('2026-11-23 14:30:00'));
        $this->user = User::factory()->create();
    }

    /** @param array<int, array{soil: ?float, relay: bool}> $zones zone_id => data */
    private function storeLog(string $recordedAt, array $climate = [], array $zones = [], bool $offline = false): SensorLog
    {
        $log = SensorLog::create(array_merge([
            'temperature' => 27.0, 'humidity' => 70.0, 'light_level' => 1000, 'system_mode' => 0,
            'relay_main' => false, 'is_offline_data' => $offline, 'recorded_at' => $recordedAt,
        ], $climate));

        foreach ($zones as $zone => $data) {
            $log->zoneLogs()->create([
                'zone_id' => $zone,
                'soil_moisture' => $data['soil'],
                'relay_status' => $data['relay'],
                'state' => $data['relay'] ? 'watering' : 'idle',
            ]);
        }

        return $log;
    }

    private function stats(string $range = '24h'): array
    {
        return $this->actingAs($this->user)
            ->getJson('/api/statistics?range=' . $range)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->json();
    }

    public function test_requires_login_and_valid_range(): void
    {
        // Path api/* selalu dijawab JSON (bootstrap/app.php), jadi tamu dapat 401
        $this->getJson('/api/statistics')->assertUnauthorized();

        $this->actingAs($this->user)->getJson('/api/statistics?range=1y')
            ->assertStatus(422)
            ->assertJsonValidationErrors('range');
    }

    public function test_summary_and_hourly_buckets_skip_null_and_out_of_range_data(): void
    {
        $this->storeLog('2026-11-22 14:59:59', ['temperature' => 99.0]);   // sebelum rentang
        $this->storeLog('2026-11-23 10:05:00', ['temperature' => 25.0, 'humidity' => 70.0, 'light_level' => 1000]);
        $this->storeLog('2026-11-23 10:40:00', ['temperature' => 27.0, 'humidity' => null, 'light_level' => 3000], offline: true);
        $this->storeLog('2026-11-23 13:10:00', ['temperature' => 30.0, 'humidity' => 80.0, 'light_level' => null]);
        $this->storeLog('2026-11-23 14:45:00', ['temperature' => 99.0]);   // jam alat di depan jam server

        $data = $this->stats();

        $this->assertSame(3, $data['record_count']);
        $this->assertSame(1, $data['offline_count']);
        $this->assertEquals(['avg' => 27.3, 'min' => 25, 'max' => 30], $data['summary']['temperature']);
        $this->assertEquals(['avg' => 75, 'min' => 70, 'max' => 80], $data['summary']['humidity']);
        $this->assertEquals(['avg' => 2000, 'min' => 1000, 'max' => 3000], $data['summary']['light_level']);
        $this->assertEquals(['avg' => null, 'min' => null, 'max' => null], $data['summary']['soil']);

        $this->assertCount(24, $data['labels']['ticks']);
        $this->assertCount(24, $data['series']['temperature']);
        $this->assertSame('15:00', $data['labels']['ticks'][0]);
        $this->assertSame('10:00', $data['labels']['ticks'][19]);
        $this->assertSame('23/11 10:00–11:00', $data['labels']['tips'][19]);
        $this->assertSame('23/11 14:00–sekarang', $data['labels']['tips'][23]);   // bucket berjalan

        $this->assertEquals(26.0, $data['series']['temperature'][19]);   // rata-rata 10:05 & 10:40
        $this->assertEquals(70.0, $data['series']['humidity'][19]);      // null dilewati
        $this->assertNull($data['series']['temperature'][20]);           // jam tanpa data
        $this->assertEquals(30.0, $data['series']['temperature'][22]);
        $this->assertNull($data['series']['light_level'][22]);
    }

    public function test_bucket_count_per_range_stops_at_now(): void
    {
        $this->storeLog('2026-11-20 08:00:00');

        $week = $this->stats('7d');
        // 17/11 00:00 s.d. 23/11 14:30, per 3 jam: tidak ada bucket di masa depan
        $this->assertCount(53, $week['labels']['ticks']);
        $this->assertSame('17/11', $week['labels']['ticks'][0]);
        $this->assertSame('20/11 06:00–09:00', $week['labels']['tips'][26]);
        $this->assertEquals(27.0, $week['series']['temperature'][26]);

        $month = $this->stats('30d');
        $this->assertCount(30, $month['labels']['ticks']);
        $this->assertSame('25/10/2026', $month['labels']['tips'][0]);
        $this->assertSame('23/11/2026 (hari ini)', $month['labels']['tips'][29]);
        $this->assertCount(30, $month['series']['soil']['1']);
    }

    public function test_irrigation_counts_valve_on_periods_and_caps_data_gaps(): void
    {
        $zone1 = fn (bool $on, ?float $soil = 40.0) => [1 => ['soil' => $soil, 'relay' => $on]];

        // Disisipkan acak: urutan dihitung dari waktu rekam alat (batch offline bisa datang belakangan)
        $this->storeLog('2026-11-23 12:02:30', zones: $zone1(false, 60.0));
        $this->storeLog('2026-11-23 12:00:00', zones: $zone1(false, 30.0));
        $this->storeLog('2026-11-23 12:01:00', zones: $zone1(true, null));
        $this->storeLog('2026-11-23 12:02:00', zones: $zone1(true, 50.0));
        // Penyiraman kedua: alat offline 1 jam saat valve ON -> celah dihitung maksimal 120 detik
        $this->storeLog('2026-11-23 13:00:00', zones: $zone1(true), offline: true);
        $this->storeLog('2026-11-23 14:00:00', zones: $zone1(true));
        $this->storeLog('2026-11-23 14:00:30', zones: $zone1(false));

        $data = $this->stats();

        // 60 + 30 detik, lalu 120 (dibatasi) + 30 detik
        $this->assertEquals(
            ['zone' => 1, 'count' => 2, 'total_seconds' => 240, 'avg_seconds' => 120, 'soil_avg' => 43.3],
            $data['irrigation'][0],
        );
        $this->assertEquals(
            ['zone' => 2, 'count' => 0, 'total_seconds' => 0, 'avg_seconds' => null, 'soil_avg' => null],
            $data['irrigation'][1],
        );
        $this->assertCount(3, $data['irrigation']);

        // 30, 50, 60, 40, 40, 40 (null dilewati)
        $this->assertEquals(['avg' => 43.3, 'min' => 30, 'max' => 60], $data['summary']['soil']);
        $this->assertEquals(46.7, $data['series']['soil']['1'][21]);   // jam 12: 30, 50, 60
        $this->assertNull($data['series']['soil']['2'][21]);
    }

    public function test_dashboard_has_statistics_tab_with_activity_log(): void
    {
        $this->actingAs($this->user)->get('/dashboard')
            ->assertOk()
            ->assertSee('id="tab-stats"', false)
            ->assertSee('data-tab="tab-stats"', false)
            ->assertDontSee('id="tab-log"', false)
            ->assertSee('Statistik')
            ->assertSee('Rekap Penyiraman')
            ->assertSee('Riwayat Aktivitas Pengguna');
    }
}
