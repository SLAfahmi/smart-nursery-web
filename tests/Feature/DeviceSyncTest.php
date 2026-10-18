<?php

namespace Tests\Feature;

use App\Models\DeviceControl;
use App\Models\DeviceState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Sinkronisasi konfigurasi web <-> firmware (command_version) dan perintah relay.
 */
class DeviceSyncTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-device-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['nursery.device_token' => self::TOKEN]);
    }

    /** Telemetry seperti firmware baru (buildTelemetry(true) di cloud_task.cpp) */
    private function telemetry(int $commandVersion, array $overrides = []): array
    {
        $schedules = DeviceControl::defaultSchedules();

        return array_merge([
            'recorded_at' => '2026-10-11 12:00:00',
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
            'command_version' => $commandVersion,
            'config' => ['sensorInterval' => 10, 'manualMode' => 0, 'manualDuration' => 60, 'logInterval' => 3600, 'cooldown' => 14400],
            'schedules' => $schedules,
        ], $overrides);
    }

    private function sendTelemetry(array $payload)
    {
        return $this->withHeaders(['X-Device-Token' => self::TOKEN])->postJson('/api/v1/telemetry', $payload);
    }

    private function control(array $attributes = []): DeviceControl
    {
        return DeviceControl::create(array_merge([
            'device_key' => 'NURSERY_01',
            'system_mode' => 1,
            'manual_mode_type' => 1,
            'manual_duration' => 30,
            'schedules_json' => DeviceControl::defaultSchedules(),
            'sys_config' => ['sensor_interval' => 20, 'log_interval' => 1800, 'cooldown' => 7200],
            'command_version' => 5,
        ], $attributes));
    }

    public function test_full_command_is_sent_while_device_version_differs(): void
    {
        $this->control();

        $this->sendTelemetry($this->telemetry(4))
            ->assertStatus(201)
            ->assertJsonPath('device_command.version', 5)
            ->assertJsonPath('device_command.system_mode', 1)
            ->assertJsonPath('device_command.manual_mode_type', 1)
            ->assertJsonPath('device_command.manual_duration', 30)
            ->assertJsonPath('device_command.sensor_interval', 20)
            ->assertJsonPath('device_command.log_interval', 1800)
            ->assertJsonPath('device_command.cooldown', 7200)
            ->assertJsonCount(3, 'device_command.schedules');

        // Konfigurasi web belum diterapkan alat -> tidak boleh tertimpa kondisi alat
        $this->assertSame(1, DeviceControl::first()->system_mode);
        $this->assertSame(4, DeviceState::first()->reported_version);
    }

    public function test_no_command_when_device_is_up_to_date(): void
    {
        $this->control();

        $this->sendTelemetry($this->telemetry(5, ['system_mode' => 1, 'config' => ['manualMode' => 1, 'manualDuration' => 30, 'sensorInterval' => 20, 'logInterval' => 1800, 'cooldown' => 7200]]))
            ->assertStatus(201)
            ->assertJsonPath('device_command', null);
    }

    public function test_firmware_without_version_always_gets_full_command(): void
    {
        $this->control();
        $payload = $this->telemetry(0);
        unset($payload['command_version'], $payload['config'], $payload['schedules']);

        $this->sendTelemetry($payload)->assertJsonPath('device_command.version', 5);
        $this->assertNull(DeviceState::first()->reported_version);
    }

    public function test_changes_from_local_dashboard_are_copied_to_web(): void
    {
        $user = User::factory()->create();
        $control = $this->control(['updated_by_user_id' => $user->id]);
        $updatedAt = $control->updated_at;
        $this->travel(1)->minutes();

        $schedules = DeviceControl::defaultSchedules();
        $schedules[0]['slots'][0] = ['active' => true, 'hour' => 5, 'minute' => 45, 'duration' => 90];

        // Alat sudah di versi 5, lalu mode & jadwal diubah lewat dashboard lokal (WiFi AP)
        $this->sendTelemetry($this->telemetry(5, [
            'system_mode' => 2,
            'config' => ['sensorInterval' => 15, 'manualMode' => 0, 'manualDuration' => 45, 'logInterval' => 900, 'cooldown' => 3600],
            'schedules' => $schedules,
        ]))->assertJsonPath('device_command', null);

        $control->refresh();
        $this->assertSame(2, $control->system_mode);
        $this->assertSame(0, $control->manual_mode_type);
        $this->assertSame(45, $control->manual_duration);
        $this->assertSame(['sensor_interval' => 15, 'log_interval' => 900, 'cooldown' => 3600], $control->sys_config);
        $this->assertSame(['active' => true, 'hour' => 5, 'minute' => 45, 'duration' => 90], $control->schedules_json[0]['slots'][0]);
        $this->assertSame(5, $control->command_version, 'Perubahan dari alat tidak menaikkan versi');
        $this->assertNull($control->updated_by_user_id, 'Diubah oleh perangkat, bukan user web');
        $this->assertTrue($control->updated_at->greaterThan($updatedAt));
    }

    public function test_telemetry_without_changes_does_not_touch_updated_at(): void
    {
        $control = $this->control();
        $updatedAt = $control->updated_at->toDateTimeString();
        $this->travel(1)->minutes();

        $this->sendTelemetry($this->telemetry(5, ['system_mode' => 1, 'config' => ['manualMode' => 1, 'manualDuration' => 30, 'sensorInterval' => 20, 'logInterval' => 1800, 'cooldown' => 7200]]));

        $this->assertSame($updatedAt, $control->fresh()->updated_at->toDateTimeString());
    }

    public function test_invalid_device_config_does_not_reject_telemetry(): void
    {
        $this->control();

        $this->sendTelemetry($this->telemetry(5, ['config' => ['sensorInterval' => 99999]]))
            ->assertStatus(201)
            ->assertJsonPath('device_command.version', 5);

        $this->assertDatabaseCount('sensor_logs', 1);
    }

    public function test_relay_command_is_sent_once(): void
    {
        $user = User::factory()->create();
        $this->control();

        $this->actingAs($user)->postJson('/api/toggle-relay', ['relay' => 2, 'on' => true])->assertOk();
        $this->actingAs($user)->postJson('/api/toggle-relay', ['relay' => 'main', 'on' => true])->assertOk();

        $this->sendTelemetry($this->telemetry(4))
            ->assertJsonPath('device_command.relays', [['relay' => 2, 'on' => true], ['relay' => 'main', 'on' => true]]);

        $this->sendTelemetry($this->telemetry(5, ['system_mode' => 1, 'config' => ['manualMode' => 1, 'manualDuration' => 30, 'sensorInterval' => 20, 'logInterval' => 1800, 'cooldown' => 7200]]))
            ->assertJsonPath('device_command', null);

        $this->assertDatabaseHas('activity_logs', ['action' => 'Menyalakan Valve Relay Zona 2']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'Menyalakan Pompa Utama']);
    }

    public function test_expired_relay_command_is_dropped(): void
    {
        $user = User::factory()->create();
        $this->control();

        $this->actingAs($user)->postJson('/api/toggle-relay', ['relay' => 1, 'on' => true])->assertOk();
        $this->travel(DeviceControl::RELAY_COMMAND_TTL_SECONDS + 5)->seconds();

        $this->sendTelemetry($this->telemetry(4))->assertJsonMissingPath('device_command.relays');
    }

    public function test_relay_is_rejected_outside_manual_mode(): void
    {
        $user = User::factory()->create();
        $this->control(['system_mode' => 0]);

        $this->actingAs($user)->postJson('/api/toggle-relay', ['relay' => 1, 'on' => true])
            ->assertStatus(409)
            ->assertJson(['status' => 'error']);

        $this->assertNull(DeviceControl::first()->pending_relays);
    }

    public function test_unknown_relay_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->control();

        $this->actingAs($user)->postJson('/api/toggle-relay', ['relay' => 4, 'on' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('relay');
    }

    public function test_changing_mode_bumps_version_and_clears_pending_relays(): void
    {
        $user = User::factory()->create();
        $this->control();

        $this->actingAs($user)->postJson('/api/toggle-relay', ['relay' => 1, 'on' => true])->assertOk();
        $this->actingAs($user)->postJson('/api/update-mode', ['system_mode' => 0])->assertOk();

        $control = DeviceControl::first();
        $this->assertSame(6, $control->command_version);
        $this->assertSame($user->id, $control->updated_by_user_id);
        $this->assertNull($control->pending_relays);
    }

    public function test_sys_config_is_validated_and_sent_to_device(): void
    {
        $user = User::factory()->create();
        $this->control();

        $this->actingAs($user)->postJson('/api/sys-config', ['sensor_interval' => 1, 'log_interval' => 30, 'cooldown' => 90000])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sensor_interval', 'log_interval', 'cooldown']);

        $this->actingAs($user)->postJson('/api/sys-config', ['sensor_interval' => 30, 'log_interval' => 900, 'cooldown' => 3600])
            ->assertOk();

        $this->sendTelemetry($this->telemetry(5))
            ->assertJsonPath('device_command.version', 6)
            ->assertJsonPath('device_command.sensor_interval', 30)
            ->assertJsonPath('device_command.log_interval', 900)
            ->assertJsonPath('device_command.cooldown', 3600);
    }

    public function test_status_reports_device_sync_state(): void
    {
        $user = User::factory()->create();
        $this->control();

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('device.online', false)
            ->assertJsonPath('device.synced', null);

        $this->sendTelemetry($this->telemetry(4));
        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('device.online', true)
            ->assertJsonPath('device.synced', false);

        $this->sendTelemetry($this->telemetry(5));
        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('device.synced', true);

        $this->travel(DeviceState::OFFLINE_AFTER_SECONDS + 1)->seconds();
        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('device.online', false);
    }

    public function test_status_reports_device_clock_drift(): void
    {
        $user = User::factory()->create();
        $this->control();

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('device.clock', null);

        // Jam alat 5 menit lebih lambat dari jam server
        $this->travelTo(Carbon::parse('2026-10-11 12:05:00'));
        $this->sendTelemetry($this->telemetry(5, ['recorded_at' => '2026-10-11 12:00:00']));

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('device.clock.valid', true)
            ->assertJsonPath('device.clock.device_time', '11/10/2026 12:00:00')
            ->assertJsonPath('device.clock.received_at', '11/10/2026 12:05:00')
            ->assertJsonPath('device.clock.drift_seconds', -300);
    }

    public function test_status_reports_unset_device_clock(): void
    {
        $user = User::factory()->create();
        $this->control();

        // Firmware mengirim recorded_at null jika jam alat belum valid
        $this->sendTelemetry($this->telemetry(5, ['recorded_at' => null]))->assertStatus(201);

        $this->actingAs($user)->getJson('/api/dashboard/status')
            ->assertJsonPath('device.clock.valid', false)
            ->assertJsonPath('device.clock.device_time', null)
            ->assertJsonPath('device.clock.drift_seconds', null);
    }

    public function test_config_tab_keeps_values_outside_option_list(): void
    {
        $user = User::factory()->create();
        $this->control(['sys_config' => ['sensor_interval' => 10, 'log_interval' => 600, 'cooldown' => 5400]]);

        // Nilai yang tidak ada di daftar pilihan tetap terpilih (tidak berubah diam-diam saat disimpan)
        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('<option value="600" selected>10m 0d</option>', false)
            ->assertSee('<option value="5400" selected>1j 30m</option>', false)
            ->assertSee('Sinkronisasi Waktu RTC')
            ->assertDontSee('Parameter Fuzzy Mamdani');
    }
}
