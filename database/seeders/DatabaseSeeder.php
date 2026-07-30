<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DeviceControl;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@nursery.com'],
            [
                'name' => 'Admin Nursery',
                'password' => Hash::make('admin123'),
            ]
        );

        $defaultSchedules = [];
        for ($i = 0; $i < 5; $i++) {
            $defaultSchedules[] = [
                'schedules' => [
                    ['active' => false, 'hour' => 7, 'minute' => 0, 'duration' => 10],
                    ['active' => false, 'hour' => 12, 'minute' => 0, 'duration' => 10],
                    ['active' => false, 'hour' => 17, 'minute' => 0, 'duration' => 10],
                ]
            ];
        }

        DeviceControl::firstOrCreate(
            ['device_key' => 'NURSERY_01'],
            [
                'system_mode' => 0, // Auto Fuzzy
                'manual_mode_type' => 0, // Toggle Direct
                'manual_duration' => 10,
                'schedules_json' => $defaultSchedules,
                'updated_by_user_id' => $admin->id,
            ]
        );
    }
}
