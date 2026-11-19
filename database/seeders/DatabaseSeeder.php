<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DeviceControl;
use App\Rules\AllowedEmailDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Login hanya menerima @gmail.com, jadi akun admin juga wajib alamat Gmail
        $email = AllowedEmailDomain::normalize(config('nursery.admin_email'));
        if (Validator::make(['email' => $email], ['email' => AllowedEmailDomain::rules()])->fails()) {
            throw new RuntimeException('ADMIN_EMAIL di .env wajib diisi alamat @' . AllowedEmailDomain::domain());
        }

        // Password dari ADMIN_PASSWORD. Jika kosong, dibuat acak dan ditampilkan sekali di terminal.
        $password = config('nursery.admin_password') ?: Str::password(16, symbols: false);

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin Nursery',
                'password' => Hash::make($password),
            ]
        );

        if ($admin->wasRecentlyCreated && !config('nursery.admin_password')) {
            $this->command?->warn("Akun admin dibuat: {$admin->email} / {$password}");
            $this->command?->warn('Simpan password ini. Ganti kapan saja dengan: php artisan nursery:user ' . $admin->email);
        }

        DeviceControl::firstOrCreate(
            ['device_key' => 'NURSERY_01'],
            [
                'system_mode' => 0, // Auto Fuzzy
                'manual_mode_type' => 0, // Toggle Direct
                'manual_duration' => 10,
                'schedules_json' => DeviceControl::defaultSchedules(),
                'updated_by_user_id' => $admin->id,
            ]
        );
    }
}
