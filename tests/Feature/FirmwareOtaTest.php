<?php

namespace Tests\Feature;

use App\Models\DeviceControl;
use App\Models\DeviceState;
use App\Models\FirmwareRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * OTA cloud: kontrak dengan firmware (src/ota.cpp) dan kartu "Update Firmware (OTA)" di tab Config.
 */
class FirmwareOtaTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-device-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['nursery.device_token' => self::TOKEN]);
        Storage::fake('local');
    }

    /** Telemetry live firmware yang sudah mendukung OTA */
    private function telemetry(array $overrides = []): array
    {
        return array_merge([
            'recorded_at' => '2026-10-29 12:00:00',
            'temperature' => 28.4,
            'humidity' => 71.2,
            'light' => 12345.5,
            'system_mode' => 0,
            'relay_main' => false,
            'zones' => [
                ['zone' => 1, 'soil' => 45.3, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 0],
                ['zone' => 2, 'soil' => 50.0, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 0],
                ['zone' => 3, 'soil' => 30.1, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 0],
            ],
            'firmware_version' => '1.0.1',
        ], $overrides);
    }

    private function sendTelemetry(array $payload)
    {
        return $this->withHeaders(['X-Device-Token' => self::TOKEN])->postJson('/api/v1/telemetry', $payload);
    }

    /** Image ESP32 palsu: byte pertama 0xE9 */
    private function binary(string $firstByte = "\xE9"): string
    {
        return $firstByte . str_repeat("\0", 1023);
    }

    private function upload(string $version, ?string $content = null, ?User $user = null)
    {
        return $this->actingAs($user ?? User::factory()->create())->postJson('/api/firmware', [
            'version' => $version,
            'firmware' => UploadedFile::fake()->createWithContent('fw.bin', $content ?? $this->binary()),
            'notes' => 'Perbaikan sensor',
        ]);
    }

    private function release(string $version): FirmwareRelease
    {
        $this->upload($version)->assertOk();

        return FirmwareRelease::where('version', $version)->firstOrFail();
    }

    public function test_older_firmware_gets_ota_offer_for_active_release(): void
    {
        $release = $this->release('1.0.2');

        $this->sendTelemetry($this->telemetry())
            ->assertStatus(201)
            ->assertJsonPath('ota.version', '1.0.2')
            ->assertJsonPath('ota.path', "/api/v1/firmware/{$release->id}/download")
            ->assertJsonPath('ota.size', 1024)
            ->assertJsonPath('ota.md5', md5($this->binary()));
    }

    public function test_no_offer_when_device_runs_release_version(): void
    {
        $this->release('1.0.2');

        $this->sendTelemetry($this->telemetry(['firmware_version' => '1.0.2']))
            ->assertStatus(201)
            ->assertJsonPath('ota', null);
    }

    public function test_no_offer_for_firmware_without_ota_support(): void
    {
        $this->release('1.0.2');
        $payload = $this->telemetry();
        unset($payload['firmware_version']);

        $this->sendTelemetry($payload)
            ->assertStatus(201)
            ->assertJsonPath('ota', null);
    }

    public function test_no_offer_after_device_rejected_release(): void
    {
        $this->release('1.0.2');

        $this->sendTelemetry($this->telemetry(['ota' => ['target' => '1.0.2', 'state' => 'rejected', 'error' => 'rollback']]))
            ->assertStatus(201)
            ->assertJsonPath('ota', null);
    }

    public function test_failed_attempt_still_gets_offer(): void
    {
        $this->release('1.0.2');

        $this->sendTelemetry($this->telemetry(['ota' => ['target' => '1.0.2', 'state' => 'failed', 'error' => 'MD5 mismatch']]))
            ->assertJsonPath('ota.version', '1.0.2');
    }

    public function test_invalid_ota_status_does_not_break_telemetry_or_config_sync(): void
    {
        DeviceControl::create([
            'device_key' => 'NURSERY_01',
            'system_mode' => 1,
            'manual_mode_type' => 1,
            'manual_duration' => 30,
            'schedules_json' => DeviceControl::defaultSchedules(),
            'command_version' => 5,
        ]);

        // Alat sudah di versi 5 dan mode diubah lewat dashboard lokal; status ota-nya rusak
        $this->sendTelemetry($this->telemetry([
            'system_mode' => 2,
            'command_version' => 5,
            'ota' => ['target' => '1.0.2', 'state' => 'aneh'],
        ]))
            ->assertStatus(201)
            ->assertJsonPath('device_command', null)
            ->assertJsonPath('ota', null);

        $this->assertDatabaseCount('sensor_logs', 1);
        $this->assertSame(2, DeviceControl::first()->system_mode);
        $this->assertSame(5, DeviceState::first()->reported_version);
        $this->assertNull(DeviceState::first()->ota_status);
    }

    public function test_telemetry_stores_firmware_version_and_ota_status(): void
    {
        $this->sendTelemetry($this->telemetry(['ota' => ['target' => '1.0.2', 'state' => 'deferred']]))
            ->assertStatus(201);

        $state = DeviceState::first();
        $this->assertSame('1.0.1', $state->firmware_version);
        $this->assertSame(['target' => '1.0.2', 'state' => 'deferred'], $state->ota_status);

        // Proses OTA selesai: alat berhenti mengirim "ota"
        $this->sendTelemetry($this->telemetry(['firmware_version' => '1.0.2']));

        $state->refresh();
        $this->assertSame('1.0.2', $state->firmware_version);
        $this->assertNull($state->ota_status);
    }

    public function test_download_requires_device_token(): void
    {
        $release = $this->release('1.0.2');

        $this->get("/api/v1/firmware/{$release->id}/download")->assertStatus(401);
    }

    public function test_download_serves_release_binary(): void
    {
        $release = $this->release('1.0.2');

        $response = $this->withHeaders(['X-Device-Token' => self::TOKEN])
            ->get("/api/v1/firmware/{$release->id}/download")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Content-Length', '1024')
            ->assertHeader('x-MD5', md5($this->binary()))
            ->assertDownload('smart-nursery-1.0.2.bin');

        $this->assertSame($this->binary(), $response->streamedContent());
    }

    public function test_download_of_missing_file_returns_404(): void
    {
        $release = $this->release('1.0.2');
        Storage::disk('local')->delete($release->path);

        $this->withHeaders(['X-Device-Token' => self::TOKEN])
            ->get("/api/v1/firmware/{$release->id}/download")
            ->assertNotFound();
    }

    public function test_upload_rejects_non_esp32_binary(): void
    {
        $this->upload('1.0.2', $this->binary("\x7F"))
            ->assertStatus(422)
            ->assertJson(['status' => 'error', 'message' => 'File bukan firmware ESP32 (.bin)']);

        $this->assertDatabaseCount('firmware_releases', 0);
    }

    public function test_upload_rejects_bad_or_duplicate_version(): void
    {
        // Hanya angka 0-9 bertitik X.Y.Z (versi juga dipakai sebagai nama file)
        foreach (['v1.0', '1.a.2', '1.0', '1.0.2.3', '1000.0.0', '1.0.2-beta', '١.٠.٢', '../1.0.2', '1.0.2/x'] as $bad) {
            $this->upload($bad)->assertStatus(422)->assertJsonValidationErrors('version');
        }
        $this->assertDatabaseCount('firmware_releases', 0);

        $this->release('1.0.2');
        $this->upload('1.0.2')->assertStatus(422)->assertJsonValidationErrors('version');

        $this->assertDatabaseCount('firmware_releases', 1);
    }

    public function test_upload_rejects_non_bin_file_and_long_notes(): void
    {
        $user = User::factory()->create();

        // Isi berawalan 0xE9 tetapi ekstensi bukan .bin
        $this->actingAs($user)->postJson('/api/firmware', [
            'version' => '1.0.2',
            'firmware' => UploadedFile::fake()->createWithContent('fw.txt', $this->binary()),
        ])->assertStatus(422)->assertJsonValidationErrors(['firmware' => 'File firmware harus berformat .bin.']);

        foreach (['fw.bin.exe', 'fw.hex', 'fw', 'fw.bin.'] as $name) {
            $this->actingAs($user)->postJson('/api/firmware', [
                'version' => '1.0.2',
                'firmware' => UploadedFile::fake()->createWithContent($name, $this->binary()),
            ])->assertStatus(422)->assertJsonValidationErrors('firmware');
        }

        $this->actingAs($user)->postJson('/api/firmware', [
            'version' => '1.0.2',
            'firmware' => UploadedFile::fake()->create('fw.bin', 6401),
        ])->assertStatus(422)->assertJsonValidationErrors('firmware');

        $this->actingAs($user)->postJson('/api/firmware', [
            'version' => '1.0.2',
            'firmware' => UploadedFile::fake()->createWithContent('fw.bin', $this->binary()),
            'notes' => str_repeat('n', 501),
        ])->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->assertDatabaseCount('firmware_releases', 0);
    }

    public function test_version_whitespace_is_trimmed_before_validation(): void
    {
        // Middleware TrimStrings membuang spasi/newline, jadi nama file tetap bersih
        $this->upload(" 1.0.3\n")->assertOk();

        $release = FirmwareRelease::sole();
        $this->assertSame('1.0.3', $release->version);
        $this->assertSame('firmware/1.0.3.bin', $release->path);
    }

    public function test_upload_accepts_uppercase_bin_extension(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/api/firmware', [
            'version' => '1.0.4',
            'firmware' => UploadedFile::fake()->createWithContent('FIRMWARE.BIN', $this->binary()),
        ])->assertOk();

        $this->assertSame('firmware/1.0.4.bin', FirmwareRelease::sole()->path);
    }

    public function test_config_tab_restricts_firmware_inputs(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertSee('pattern="\d{1,3}\.\d{1,3}\.\d{1,3}"', false)
            ->assertSee('accept=".bin"', false)
            ->assertDontSee('application/octet-stream', false);
    }

    public function test_upload_requires_login(): void
    {
        $this->postJson('/api/firmware', [
            'version' => '1.0.2',
            'firmware' => UploadedFile::fake()->createWithContent('fw.bin', $this->binary()),
        ])->assertStatus(401);

        $this->assertDatabaseCount('firmware_releases', 0);
    }

    public function test_upload_activates_new_release_and_deactivates_old(): void
    {
        $user = User::factory()->create(['name' => 'Admin Test']);
        $old = $this->release('1.0.1');

        $this->upload('1.0.2', null, $user)
            ->assertOk()
            ->assertJson(['status' => 'success', 'message' => 'Firmware 1.0.2 dirilis oleh Admin Test']);

        $new = FirmwareRelease::where('version', '1.0.2')->first();
        $this->assertTrue($new->is_active);
        $this->assertFalse($old->fresh()->is_active);
        $this->assertSame($user->id, $new->uploaded_by_user_id);
        $this->assertSame('Perbaikan sensor', $new->notes);
        $this->assertSame('firmware/1.0.2.bin', $new->path);
        Storage::disk('local')->assertExists('firmware/1.0.2.bin');
        $this->assertTrue(FirmwareRelease::active()->is($new));

        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'Merilis firmware 1.0.2 (OTA)']);
    }

    public function test_cancel_stops_offer(): void
    {
        $user = User::factory()->create();
        $release = $this->release('1.0.2');

        $this->actingAs($user)->postJson("/api/firmware/{$release->id}/cancel")
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($release->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Membatalkan rilis firmware 1.0.2']);

        $this->sendTelemetry($this->telemetry())->assertJsonPath('ota', null);
    }

    public function test_status_reports_firmware_block(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('firmware.device_version', null)
            ->assertJsonPath('firmware.ota', null)
            ->assertJsonPath('firmware.release', null);

        $release = $this->release('1.0.2');
        $this->sendTelemetry($this->telemetry(['ota' => ['target' => '1.0.2', 'state' => 'ready']]));

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('firmware.device_version', '1.0.1')
            ->assertJsonPath('firmware.ota.state', 'ready')
            ->assertJsonPath('firmware.release.id', $release->id)
            ->assertJsonPath('firmware.release.version', '1.0.2')
            ->assertJsonPath('firmware.release.size', 1024);
    }

    public function test_config_tab_shows_firmware_card(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertOk()
            ->assertSee('Update Firmware (OTA)');
    }
}
