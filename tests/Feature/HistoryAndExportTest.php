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

    private function storeLog(string $recordedAt, bool $offline = false, float $temperature = 27.0, bool $valveOn = true): SensorLog
    {
        $log = SensorLog::create([
            'temperature' => $temperature, 'humidity' => 70, 'light_level' => 1000, 'system_mode' => 0,
            'relay_main' => false, 'is_offline_data' => $offline, 'recorded_at' => $recordedAt,
        ]);
        $log->zoneLogs()->create([
            'zone_id' => 1, 'soil_moisture' => 40.5, 'relay_status' => $valveOn, 'state' => $valveOn ? 'watering' : 'idle',
        ]);

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

    public function test_export_fills_watering_duration_when_valve_turns_off(): void
    {
        $user = User::factory()->create();
        $valve = fn (string $at, bool $on, bool $offline = false) => $this->storeLog($at, $offline, valveOn: $on);

        // Disimpan acak: durasi dihitung urut waktu rekam alat (batch offline bisa datang belakangan)
        $valve('2026-10-11 08:01:40', false);
        $valve('2026-10-10 23:59:00', true);    // mulai sebelum rentang, selesai setelah tengah malam
        $valve('2026-10-11 00:00:30', false);
        $valve('2026-10-11 08:00:00', false);
        $valve('2026-10-11 08:00:05', true);
        $valve('2026-10-11 08:01:05', true);
        $valve('2026-10-11 09:00:00', true);
        $valve('2026-10-11 10:00:00', true, offline: true);   // alat offline 1 jam: celah dihitung maks 120 detik
        $valve('2026-10-11 10:00:30', false);
        $valve('2026-10-11 23:59:30', true);    // selesai di luar rentang: durasi belum ada
        $valve('2026-10-12 00:00:30', false);

        $csv = $this->actingAs($user)->get('/api/export-csv?date=2026-10-11')->assertOk()->streamedContent();
        $rows = array_map(
            fn (string $line) => str_getcsv($line, ',', '"', '\\'),
            array_values(array_filter(explode("\n", trim(substr($csv, 3))))),
        );

        $column = array_search('Zona 1 Durasi Siram (detik)', $rows[0], true);
        $this->assertSame(array_search('Zona 1 Status', $rows[0], true) + 1, $column);

        $durations = [];
        foreach (array_slice($rows, 1) as $row) {
            $durations[substr($row[0], 11)] = $row[$column];
        }

        $this->assertSame([
            '00:00:30' => '90',    // 23:59:00 -> 00:00:30
            '08:00:00' => '',
            '08:00:05' => '',
            '08:01:05' => '',
            '08:01:40' => '95',
            '09:00:00' => '',
            '10:00:00' => '',
            '10:00:30' => '150',   // 120 (dibatasi) + 30
            '23:59:30' => '',
        ], $durations);
    }

    /** @return array<string, \SimpleXMLElement> isi .xlsx (semua bagian XML harus valid) */
    private function readXlsx($response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'File .xlsx harus berupa ZIP yang valid');

        $parts = [];
        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $name) {
            $xml = $zip->getFromName($name);
            $this->assertNotFalse($xml, "Bagian {$name} wajib ada");
            $parts[$name] = simplexml_load_string($xml);
            $this->assertNotFalse($parts[$name], "Bagian {$name} harus XML yang valid");
        }
        $zip->close();
        @unlink($path);

        return $parts;
    }

    public function test_xlsx_export_for_one_day(): void
    {
        $user = User::factory()->create();
        $this->storeLog('2026-10-10 23:59:00');
        $this->storeLog('2026-10-11 08:00:00', true);
        $this->storeLog('2026-10-11 09:30:00', false, 28.4);
        $this->storeLog('2026-10-12 00:00:01');

        $response = $this->actingAs($user)->get('/api/export-xlsx?date=2026-10-11');
        $response->assertOk()->assertDownload('smart_nursery_log_2026-10-11.xlsx');
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));

        $parts = $this->readXlsx($response);
        $this->assertSame('Log Sensor', (string) $parts['xl/workbook.xml']->sheets->sheet['name']);

        $rows = $parts['xl/worksheets/sheet1.xml']->sheetData->row;
        $this->assertCount(3, $rows, 'Header + 2 baris pada tanggal 11');

        // Header tebal: teks, gaya 1
        $this->assertSame('Waktu', (string) $rows[0]->c[0]->is->t);
        $this->assertSame('1', (string) $rows[0]->c[0]['s']);
        $this->assertStringContainsString('Zona 3 Tanah (%)', $rows[0]->asXML());

        // Waktu = tanggal Excel asli (gaya 2), 2026-10-11 08:00:00 -> 46306.3333...
        $this->assertSame('2', (string) $rows[1]->c[0]['s']);
        $this->assertEqualsWithDelta(46306 + 8 / 24, (float) $rows[1]->c[0]->v, 1e-6);
        $this->assertStringContainsString('Offline', $rows[1]->asXML());

        // Angka tetap angka (bukan teks), teks ON/watering tetap teks
        $cells = [];
        foreach ($rows[2]->c as $c) {
            $cells[(string) $c['r']] = isset($c['t']) ? (string) $c->is->t : (float) $c->v;
        }
        $this->assertSame(28.4, $cells['B3']);
        $this->assertSame(40.5, $cells['E3']);
        $this->assertSame('ON', $cells['F3']);
        $this->assertSame('watering', $cells['G3']);
        $this->assertArrayNotHasKey('H3', $cells, 'Valve masih ON = durasi siram kosong');
        $this->assertArrayNotHasKey('I3', $cells, 'Zona 2 tanpa data = sel kosong');
        $this->assertStringContainsString('Zona 1 Durasi Siram (detik)', $rows[0]->asXML());
    }

    public function test_xlsx_export_for_date_range_and_empty_day(): void
    {
        $user = User::factory()->create();
        $this->storeLog('2026-09-01 10:00:00');
        $this->storeLog('2026-10-11 10:00:00');

        $response = $this->actingAs($user)->get('/api/export-xlsx?from=2026-09-01&to=2026-10-11');
        $response->assertOk()->assertDownload('smart_nursery_log_2026-09-01_sd_2026-10-11.xlsx');
        $this->assertCount(3, $this->readXlsx($response)['xl/worksheets/sheet1.xml']->sheetData->row);

        // Hari tanpa data tetap menghasilkan file berisi header saja
        $response = $this->actingAs($user)->get('/api/export-xlsx?date=2026-08-01');
        $response->assertOk();
        $this->assertCount(1, $this->readXlsx($response)['xl/worksheets/sheet1.xml']->sheetData->row);
    }

    public function test_xlsx_export_rejects_invalid_range_and_requires_login(): void
    {
        // Path api/* selalu dijawab JSON (bootstrap/app.php), jadi tamu dapat 401, bukan redirect
        $this->get('/api/export-xlsx?date=2026-10-11')->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson('/api/export-xlsx?from=2026-10-11&to=2026-10-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_csv_export_rejects_invalid_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/export-csv?from=2026-10-11&to=2026-10-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }
}
