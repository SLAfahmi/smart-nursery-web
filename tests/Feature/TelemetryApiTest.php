<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\DeviceControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_device_command_returns_control_data(): void
    {
        $user = User::factory()->create(['name' => 'Admin Test']);
        DeviceControl::create([
            'device_key' => 'NURSERY_01',
            'system_mode' => 0,
            'manual_mode_type' => 0,
            'manual_duration' => 15,
            'schedules_json' => [],
            'updated_by_user_id' => $user->id,
        ]);

        $response = $this->getJson('/api/v1/device-command?device_key=NURSERY_01');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'device_key' => 'NURSERY_01',
                'system_mode' => 0,
                'manual_duration' => 15,
                'updated_by' => 'Admin Test',
            ]);
    }

    public function test_post_telemetry_saves_sensor_and_zone_logs(): void
    {
        $payload = [
            'device_key' => 'NURSERY_01',
            'temperature' => 28.5,
            'humidity' => 75.0,
            'light_level' => 35000,
            'system_mode' => 0,
            'zones' => [
                ['zone_id' => 1, 'soil_moisture' => 45, 'zone_mode' => 0, 'duration' => 10, 'relay_status' => true],
                ['zone_id' => 2, 'soil_moisture' => 50, 'zone_mode' => 0, 'duration' => 0, 'relay_status' => false],
                ['zone_id' => 3, 'soil_moisture' => 55, 'zone_mode' => 0, 'duration' => 0, 'relay_status' => false],
                ['zone_id' => 4, 'soil_moisture' => 40, 'zone_mode' => 0, 'duration' => 0, 'relay_status' => false],
                ['zone_id' => 5, 'soil_moisture' => 60, 'zone_mode' => 0, 'duration' => 0, 'relay_status' => false],
            ]
        ];

        $response = $this->postJson('/api/v1/telemetry', $payload);

        $response->assertStatus(201)
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('sensor_logs', [
            'temperature' => 28.5,
            'humidity' => 75.0,
            'light_level' => 35000,
        ]);

        $this->assertDatabaseCount('zone_logs', 5);
    }

    public function test_post_sync_batch_saves_offline_buffered_data(): void
    {
        $payload = [
            'device_key' => 'NURSERY_01',
            'batch' => [
                [
                    'temperature' => 29.0,
                    'humidity' => 70.0,
                    'light_level' => 40000,
                    'system_mode' => 0,
                    'recorded_at' => '2026-07-30 14:00:00',
                    'zones' => [
                        ['zone_id' => 1, 'soil_moisture' => 40, 'relay_status' => false],
                        ['zone_id' => 2, 'soil_moisture' => 42, 'relay_status' => false],
                        ['zone_id' => 3, 'soil_moisture' => 44, 'relay_status' => false],
                        ['zone_id' => 4, 'soil_moisture' => 46, 'relay_status' => false],
                        ['zone_id' => 5, 'soil_moisture' => 48, 'relay_status' => false],
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/v1/telemetry/sync-batch', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'success', 'synced_count' => 1]);

        $this->assertDatabaseHas('sensor_logs', [
            'is_offline_data' => true,
            'temperature' => 29.0,
        ]);
    }
}
