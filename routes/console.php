<?php

use App\Models\User;
use App\Rules\AllowedEmailDomain;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Buat akun / ganti password dari server (pendaftaran web butuh REGISTRATION_CODE, lihat config/nursery.php).
// Ganti email akun lama (mis. non-Gmail) tanpa kehilangan riwayat aktivitasnya:
//   php artisan nursery:user admin@nursery.com --email-baru=nama@gmail.com
// Cabut / pulihkan akses operator (akun tidak dihapus, riwayat aktivitasnya tetap ada):
//   php artisan nursery:user budi@gmail.com --cabut
//   php artisan nursery:user budi@gmail.com --pulihkan
Artisan::command('nursery:user {email} {--name= : Nama tampilan (hanya untuk akun baru)} {--email-baru= : Ganti email akun yang sudah ada} {--cabut : Cabut akses akun (tidak bisa login, sesi aktif dikeluarkan)} {--pulihkan : Aktifkan lagi akun yang dicabut}', function (string $email) {
    $emailRules = AllowedEmailDomain::rules();
    $email = AllowedEmailDomain::normalize($email);

    $actions = array_filter([
        '--email-baru' => $this->option('email-baru') !== null,
        '--cabut' => $this->option('cabut'),
        '--pulihkan' => $this->option('pulihkan'),
    ]);
    if (count($actions) > 1) {
        $this->error('Pilih salah satu saja: ' . implode(', ', array_keys($actions)) . '.');
        return 1;
    }

    if ($this->option('cabut') || $this->option('pulihkan')) {
        // Akun lama berdomain apa pun juga bisa dicabut
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            $this->error("Akun {$email} tidak ditemukan.");
            return 1;
        }

        if ($this->option('cabut')) {
            if ($user->isDisabled()) {
                $this->info("Akses {$user->email} sudah dicabut sejak {$user->disabled_at->format('d-m-Y H:i')}.");
                return 0;
            }
            $user->forceFill(['disabled_at' => now()])->save();
            $this->info("Akses {$user->email} dicabut. Akun ini tidak bisa login, dan sesi yang aktif dikeluarkan pada request berikutnya.");
            $this->warn('Jika orang ini tahu kode pendaftaran, ganti juga REGISTRATION_CODE di .env agar ia tidak membuat akun baru.');
            return 0;
        }

        if (!$user->isDisabled()) {
            $this->info("Akun {$user->email} sudah aktif.");
            return 0;
        }
        $user->forceFill(['disabled_at' => null])->save();
        $this->info("Akses {$user->email} dipulihkan. Bisa login lagi dengan password lamanya.");
        return 0;
    }

    if (($newEmail = $this->option('email-baru')) !== null) {
        // Akun lama boleh berdomain apa pun, justru itu yang dipindahkan
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user) {
            $this->error("Akun {$email} tidak ditemukan.");
            return 1;
        }

        $newEmail = AllowedEmailDomain::normalize($newEmail);
        $validator = Validator::make(
            ['email' => $newEmail],
            ['email' => [...$emailRules, 'unique:users,email']],
            ['email.unique' => 'Email baru sudah dipakai akun lain.'],
        );
        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));
            return 1;
        }

        $user->update(['email' => $newEmail]);
        $this->info("Email akun diganti: {$email} -> {$newEmail}");
        return 0;
    }

    $validator = Validator::make(['email' => $email], ['email' => $emailRules]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first('email'));
        return 1;
    }

    $user = User::where('email', $email)->first();

    $password = $this->secret('Password baru (minimal 8 karakter)');
    $length = strlen((string) $password);
    if ($length < 8 || $length > 72) {
        $this->error('Password minimal 8 dan maksimal 72 karakter.');
        return 1;
    }
    if ($this->secret('Ulangi password') !== $password) {
        $this->error('Password tidak sama.');
        return 1;
    }

    if ($user) {
        $user->update(['password' => Hash::make($password)]);
        $this->info("Password {$email} diganti.");
        if ($user->isDisabled()) {
            $this->warn("Catatan: akses akun ini sedang dicabut. Aktifkan lagi dengan: php artisan nursery:user {$email} --pulihkan");
        }
    } else {
        User::create([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $this->info("Akun {$email} dibuat.");
    }

    return 0;
})->purpose('Buat akun dashboard, ganti password, ganti email (--email-baru), atau cabut/pulihkan akses (--cabut / --pulihkan)');

Artisan::command('nursery:users', function () {
    $users = User::orderBy('id')->get();
    if ($users->isEmpty()) {
        $this->info('Belum ada akun.');
        return 0;
    }

    $this->table(
        ['ID', 'Nama', 'Email', 'Status', 'Dibuat'],
        $users->map(fn (User $user) => [
            $user->id,
            $user->name,
            $user->email,
            $user->isDisabled() ? 'Dicabut ' . $user->disabled_at->format('d-m-Y H:i') : 'Aktif',
            $user->created_at?->format('d-m-Y H:i') ?? '-',
        ])->all(),
    );

    return 0;
})->purpose('Daftar semua akun dashboard beserta status aksesnya');

// Hapus riwayat sensor lebih lama dari NURSERY_HISTORY_RETENTION_DAYS (SensorLog::prunable).
// Di hosting butuh cron: * * * * * php /path/ke/project/artisan schedule:run
Schedule::command('model:prune')->daily();
