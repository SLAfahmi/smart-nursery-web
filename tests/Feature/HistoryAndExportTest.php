<?php

namespace Tests\Feature;

use App\Models\SensorLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data live, penyimpanan riwayat, pembersihan data lama, dan export CSV.
 */
class HistoryAndExportTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-device-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['nursery.device_token' => self::TOKEN, 'nursery.history_interval' => 60]);
    }

    private function telemetry(array $overrides = []): array
    {
        return array_merge([
            'recorded_at' => now()->toDateTimeString(),
            'temperature' => 28.4,
            'humidity' => 71.2,
            'light' => 12345.5,
            'system_mode' => 0,
            'relay_main' => false,
            'zones' => [
                ['zone' => 1, 'soil' => 45.3, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 0],
                ['zone' => 2, 'soil' => null, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 0],
                ['zone' => 3, 'soil' => 30.1, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 120],
            ],
        ], $overrides);
    }

    private function send(array $payload, string $uri = '/api/v1/telemetry')
    {
        return $this->withHeaders(['X-Device-Token' => self::TOKEN])->postJson($uri, $payload);
    }

    private function storeLog(string $recordedAt, bool $offline = false, float $temperature = 27.0): SensorLog
    {
        $log = SensorLog::create([
            'temperature' => $temperature, 'humidity' => 70, 'light_level' => 1000, 'system_mode' => 0,
            'relay_main' => false, 'is_offline_data' => $offline, 'recorded_at' => $recordedAt,
        ]);
        $log->zoneLogs()->create(['zone_id' => 1, 'soil_moisture' => 40.5, 'relay_status' => true, 'state' => 'watering']);

        return $log;
    }

    public function test_live_telemetry_is_stored_as_history_once_per_interval(): void
    {
        $this->send($this->telemetry())->assertJsonPath('sensor_log_id', 1);

        $this->travel(5)->seconds();
        $this->send($this->telemetry())->assertJsonPath('sensor_log_id', null);
        $this->assertDatabaseCount('sensor_logs', 1);

        $this->travel(60)->seconds();
        $this->send($this->telemetry())->assertJsonPath('sensor_log_id', 2);
        $this->assertDatabaseCount('sensor_logs', 2);
    }

    public function test_history_is_stored_immediately_when_relay_or_state_changes(): void
    {
        $this->send($this->telemetry());

        $watering = $this->telemetry();
        $watering['zones'][0] = ['zone' => 1, 'soil' => 45.3, 'relay' => true, 'state' => 'watering', 'fuzzy_duration' => 95];
        $this->travel(5)->seconds();
        $this->send($watering);

        $this->travel(5)->seconds();
        $this->send($this->telemetry(['relay_main' => true]));

        $this->assertDatabaseCount('sensor_logs', 3);
        $this->assertDatabaseHas('zone_logs', ['zone_id' => 1, 'relay_status' => true, 'state' => 'watering']);
    }

    public function test_offline_batch_is_always_stored(): void
    {
        $this->send($this->telemetry());
        $this->send(['data' => [$this->telemetry(), $this->telemetry()]], '/api/v1/telemetry/sync-batch')
            ->assertJson(['synced_count' => 2]);

        $this->assertDatabaseCount('sensor_logs', 3);
    }

    public function test_status_shows_latest_live_data_not_offline_batch(): void
    {
        $user = User::factory()->create();
        $this->send($this->telemetry(['temperature' => 31.5, 'relay_main' => true]));

        // Batch offline (data lama) masuk setelahnya, tidak boleh tampil sebagai data terbaru
        $this->send(['data' => [$this->telemetry(['recorded_at' => now()->subHour()->toDateTimeString(), 'temperature' => 20.0])]], '/api/v1/telemetry/sync-batch');

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('latest_sensor.temperature', 31.5)
            ->assertJsonPath('latest_sensor.relay_main', true)
            ->assertJsonPath('latest_sensor.zones.1.soil_moisture', null)
            ->assertJsonPath('latest_sensor.zones.2.fuzzy_duration', 120);
    }

    public function test_status_falls_back_to_latest_live_history(): void
    {
        $user = User::factory()->create();
        $this->storeLog(now()->subMinutes(10)->toDateTimeString(), false, 25.5);
        $this->storeLog(now()->toDateTimeString(), true, 19.0);

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('latest_sensor.temperature', 25.5)
            ->assertJsonPath('device.online', false);
    }

    public function test_dashboard_renders_placeholders_for_missing_sensor_values(): void
    {
        $user = User::factory()->create();
        $this->send($this->telemetry(['temperature' => null, 'humidity' => 70.5, 'light' => null]));

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('<span id="temp">--</span>', false)
            ->assertSee('<span id="hum">70.5</span>', false)
            ->assertSee('<span id="light">--</span>', false)
            ->assertSee('3 Total Zona');
    }

    public function test_old_history_is_pruned(): void
    {
        config(['nursery.history_retention_days' => 90]);
        $old = $this->storeLog(now()->subDays(100)->toDateTimeString());
        $old->forceFill(['created_at' => now()->subDays(100)])->save();
        $recent = $this->storeLog(now()->toDateTimeString());

        $this->artisan('model:prune', ['--model' => [SensorLog::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertDatabaseCount('zone_logs', 1);
    }

    public function test_csv_export_for_one_day(): void
    {
        $user = User::factory()->create();
        $this->storeLog('2026-10-10 23:59:00');
        $this->storeLog('2026-10-11 08:00:00', true);
        $this->storeLog('2026-10-11 09:30:00');
        $this->storeLog('2026-10-12 00:00:01');

        $response = $this->actingAs($user)->get('/api/export-csv?date=2026-10-11');
        $response->assertOk()->assertDownload('smart_nursery_log_2026-10-11.csv');

        $csv = $response->streamedContent();
        $lines = array_values(array_filter(explode("\n", trim($csv))));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Zona 3 Tanah (%)', $lines[0]);
        $this->assertCount(3, $lines, 'Header + 2 baris pada tanggal 11');
        $this->assertStringStartsWith('"2026-10-11 08:00:00"', $lines[1]);
        $this->assertStringContainsString('Offline', $lines[1]);
        $this->assertStringContainsString('40.5,ON,watering', $lines[2]);
    }

    public function test_csv_export_for_date_range(): void
    {
        $user = User::factory()->create();
        $this->storeLog('2026-09-01 10:00:00');
        $this->storeLog('2026-10-11 10:00:00');

        $response = $this->actingAs($user)->get('/api/export-csv?from=2026-09-01&to=2026-10-11');
        $response->assertOk()->assertDownload('smart_nursery_log_2026-09-01_sd_2026-10-11.csv');
        $this->assertCount(3, array_filter(explode("\n", trim($response->streamedContent()))));
    }

    public function test_csv_export_rejects_invalid_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/export-csv?from=2026-10-11&to=2026-10-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }
}
