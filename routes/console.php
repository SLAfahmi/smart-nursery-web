<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Registrasi publik mati (ALLOW_REGISTRATION=false), jadi akun dibuat / password diganti dari server.
Artisan::command('nursery:user {email} {--name= : Nama tampilan (hanya untuk akun baru)}', function (string $email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Format email tidak valid.');
        return 1;
    }

    $user = User::where('email', $email)->first();

    $password = $this->secret('Password baru (minimal 8 karakter)');
    if (strlen((string) $password) < 8) {
        $this->error('Password minimal 8 karakter.');
        return 1;
    }
    if ($this->secret('Ulangi password') !== $password) {
        $this->error('Password tidak sama.');
        return 1;
    }

    if ($user) {
        $user->update(['password' => Hash::make($password)]);
        $this->info("Password {$email} diganti.");
    } else {
        User::create([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $this->info("Akun {$email} dibuat.");
    }

    return 0;
})->purpose('Buat akun dashboard atau ganti password akun yang sudah ada');

// Hapus riwayat sensor lebih lama dari NURSERY_HISTORY_RETENTION_DAYS (SensorLog::prunable).
// Di hosting butuh cron: * * * * * php /path/ke/project/artisan schedule:run
Schedule::command('model:prune')->daily();
