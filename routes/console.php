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

// Registrasi publik mati (ALLOW_REGISTRATION=false), jadi akun dibuat / password diganti dari server.
// Ganti email akun lama (mis. non-Gmail) tanpa kehilangan riwayat aktivitasnya:
//   php artisan nursery:user admin@nursery.com --email-baru=nama@gmail.com
Artisan::command('nursery:user {email} {--name= : Nama tampilan (hanya untuk akun baru)} {--email-baru= : Ganti email akun yang sudah ada}', function (string $email) {
    $emailRules = AllowedEmailDomain::rules();
    $email = AllowedEmailDomain::normalize($email);

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
    } else {
        User::create([
            'name' => $this->option('name') ?: strstr($email, '@', true),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $this->info("Akun {$email} dibuat.");
    }

    return 0;
})->purpose('Buat akun dashboard, ganti password, atau ganti email (--email-baru) akun yang sudah ada');

// Hapus riwayat sensor lebih lama dari NURSERY_HISTORY_RETENTION_DAYS (SensorLog::prunable).
// Di hosting butuh cron: * * * * * php /path/ke/project/artisan schedule:run
Schedule::command('model:prune')->daily();
