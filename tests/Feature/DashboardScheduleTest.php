<?php

namespace Tests\Feature;

use App\Models\DeviceControl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function schedules(): array
    {
        $schedules = DeviceControl::defaultSchedules();
        $schedules[0]['slots'][0] = ['active' => true, 'hour' => 6, 'minute' => 30, 'duration' => 60];

        return $schedules;
    }

    public function test_schedule_is_saved_in_firmware_format(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/save-schedule', ['schedules' => $this->schedules()])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $saved = DeviceControl::where('device_key', 'NURSERY_01')->first()->schedules_json;
        $this->assertCount(3, $saved);
        $this->assertSame(1, $saved[0]['zone']);
        $this->assertSame(['active' => true, 'hour' => 6, 'minute' => 30, 'duration' => 60], $saved[0]['slots'][0]);
    }

    public function test_schedule_outside_firmware_range_is_rejected(): void
    {
        $user = User::factory()->create();
        $schedules = $this->schedules();
        $schedules[1]['slots'][0] = ['active' => true, 'hour' => 24, 'minute' => 0, 'duration' => 0];

        $this->actingAs($user)
            ->postJson('/api/save-schedule', ['schedules' => $schedules])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedules.1.slots.0.hour', 'schedules.1.slots.0.duration']);
    }

    public function test_active_schedule_without_time_is_rejected(): void
    {
        $user = User::factory()->create();
        $schedules = $this->schedules();
        $schedules[2]['slots'][1] = ['active' => true, 'hour' => null, 'minute' => null, 'duration' => 60];

        $this->actingAs($user)
            ->postJson('/api/save-schedule', ['schedules' => $schedules])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['schedules.2.slots.1.hour', 'schedules.2.slots.1.minute']);
    }

    public function test_schedule_with_five_zones_is_rejected(): void
    {
        $user = User::factory()->create();
        $schedules = $this->schedules();
        $schedules[] = ['zone' => 4, 'slots' => $schedules[0]['slots']];
        $schedules[] = ['zone' => 5, 'slots' => $schedules[0]['slots']];

        $this->actingAs($user)
            ->postJson('/api/save-schedule', ['schedules' => $schedules])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedules');
    }
}
