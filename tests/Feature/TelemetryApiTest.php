<?php

namespace Tests\Feature;

use App\Models\DeviceControl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Payload di sini mengikuti firmware apa adanya (src/tasks/cloud_task.cpp: buildTelemetry).
 */
class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-device-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['nursery.device_token' => self::TOKEN]);
    }

    private function device(): static
    {
        return $this->withHeaders(['X-Device-Token' => self::TOKEN]);
    }

    private function firmwarePayload(array $overrides = []): array
    {
        return array_merge([
            'recorded_at' => '2026-10-11 12:00:00',
            'temperature' => 28.4,
            'humidity' => 71.2,
            'light' => 12345.5,
            'system_mode' => 0,
            'relay_main' => true,
            'zones' => [
                ['zone' => 1, 'soil' => 45.3, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 0],
                ['zone' => 2, 'soil' => null, 'relay' => false, 'state' => 'idle', 'fuzzy_duration' => 0],
                ['zone' => 3, 'soil' => 30.1, 'relay' => true, 'state' => 'watering', 'fuzzy_duration' => 95.2],
            ],
        ], $overrides);
    }

    public function test_requests_without_valid_device_token_are_rejected(): void
    {
        $this->postJson('/api/v1/telemetry', $this->firmwarePayload())->assertStatus(401);

        $this->withHeaders(['X-Device-Token' => 'salah'])
            ->postJson('/api/v1/telemetry', $this->firmwarePayload())
            ->assertStatus(401);

        $this->postJson('/api/v1/telemetry/sync-batch', ['data' => [$this->firmwarePayload()]])->assertStatus(401);
        $this->getJson('/api/v1/device-command')->assertStatus(401);

        $this->assertDatabaseCount('sensor_logs', 0);
    }

    public function test_requests_are_rejected_when_server_token_is_not_configured(): void
    {
        config(['nursery.device_token' => null]);

        $this->withHeaders(['X-Device-Token' => ''])
            ->postJson('/api/v1/telemetry', $this->firmwarePayload())
            ->assertStatus(401);
    }

    public function test_firmware_telemetry_is_saved(): void
    {
        $this->device()->postJson('/api/v1/telemetry', $this->firmwarePayload())
            ->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sensor_logs', [
            'device_key' => 'NURSERY_01',
            'temperature' => 28.4,
            'humidity' => 71.2,
            'light_level' => 12345.5,
            'relay_main' => true,
            'is_offline_data' => false,
        ]);
        $this->assertDatabaseCount('zone_logs', 3);
        $this->assertDatabaseHas('zone_logs', ['zone_id' => 1, 'soil_moisture' => 45.3, 'relay_status' => false, 'state' => 'idle']);
        $this->assertDatabaseHas('zone_logs', ['zone_id' => 2, 'soil_moisture' => null]);
        $this->assertDatabaseHas('zone_logs', ['zone_id' => 3, 'relay_status' => true, 'state' => 'watering', 'fuzzy_duration' => 95.2]);
    }

    public function test_telemetry_with_sensor_errors_and_invalid_clock_is_accepted(): void
    {
        $payload = $this->firmwarePayload([
            'recorded_at' => null,
            'temperature' => null,
            'humidity' => null,
            'light' => null,
        ]);

        $this->device()->postJson('/api/v1/telemetry', $payload)->assertStatus(201);

        $this->assertDatabaseHas('sensor_logs', ['temperature' => null, 'humidity' => null, 'light_level' => null]);
    }

    public function test_telemetry_with_unknown_zone_is_rejected(): void
    {
        $payload = $this->firmwarePayload(['zones' => [['zone' => 4, 'soil' => 50]]]);

        $this->device()->postJson('/api/v1/telemetry', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('zones.0.zone');
    }

    public function test_telemetry_response_contains_device_command_in_firmware_format(): void
    {
        DeviceControl::create([
            'device_key' => 'NURSERY_01',
            'system_mode' => 2,
            'manual_mode_type' => 1,
            'manual_duration' => 30,
            'schedules_json' => DeviceControl::defaultSchedules(),
        ]);

        $response = $this->device()->postJson('/api/v1/telemetry', $this->firmwarePayload())
            ->assertStatus(201)
            ->assertJsonPath('device_command.version', 0)
            ->assertJsonPath('device_command.system_mode', 2)
            ->assertJsonPath('device_command.manual_mode_type', 1)
            ->assertJsonPath('device_command.manual_duration', 30)
            ->assertJsonCount(3, 'device_command.schedules')
            ->assertJsonPath('device_command.schedules.0.zone', 1)
            ->assertJsonCount(3, 'device_command.schedules.0.slots');

        $this->assertSame(
            ['active', 'hour', 'minute', 'duration'],
            array_keys($response->json('device_command.schedules.0.slots.0'))
        );
    }

    public function test_sync_batch_saves_offline_data_and_skips_invalid_items(): void
    {
        $payload = ['data' => [
            $this->firmwarePayload(['recorded_at' => '2026-10-11 08:00:00']),
            $this->firmwarePayload(['zones' => 'rusak']),
            $this->firmwarePayload(['recorded_at' => '2026-10-11 08:00:30', 'temperature' => null]),
        ]];

        $this->device()->postJson('/api/v1/telemetry/sync-batch', $payload)
            ->assertStatus(200)
            ->assertJson(['status' => 'success', 'synced_count' => 2, 'skipped_count' => 1]);

        $this->assertDatabaseCount('sensor_logs', 2);
        $this->assertDatabaseCount('zone_logs', 6);
        $this->assertDatabaseHas('sensor_logs', ['is_offline_data' => true, 'recorded_at' => '2026-10-11 08:00:00']);
    }

    public function test_get_device_command_returns_control_data(): void
    {
        $user = User::factory()->create(['name' => 'Admin Test']);
        DeviceControl::create([
            'device_key' => 'NURSERY_01',
            'system_mode' => 0,
            'manual_mode_type' => 0,
            'manual_duration' => 15,
            'schedules_json' => DeviceControl::defaultSchedules(),
            'updated_by_user_id' => $user->id,
        ]);

        $this->device()->getJson('/api/v1/device-command')
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'device_key' => 'NURSERY_01',
                'system_mode' => 0,
                'manual_duration' => 15,
                'updated_by' => 'Admin Test',
            ]);
    }
}
