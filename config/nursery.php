<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Smart Nursery
    |--------------------------------------------------------------------------
    |
    | Nilai di sini harus sama dengan firmware (include/config.h).
    |
    */

    // Jumlah zona irigasi (firmware: NUM_ZONES)
    'zones' => (int) env('NURSERY_ZONES', 3) ?: 3,

    // Jumlah slot jadwal per zona (firmware: MAX_SCHEDULES)
    'schedule_slots' => 3,

    // Jadwal dilewati jika kelembaban tanah zona di atas nilai ini (firmware: SCHEDULE_SOIL_MAX)
    'schedule_soil_max' => 60,

    // Jeda serap air setelah zona selesai menyiram, dalam detik (firmware: SOAK_DELAY_SEC)
    'soak_delay' => 15,

    // Token perangkat, dikirim ESP32 lewat header X-Device-Token (firmware: DEVICE_KEY)
    'device_token' => env('DEVICE_TOKEN'),

    // Telemetry live masuk tiap 5 detik. Riwayat (sensor_logs) hanya disimpan tiap
    // interval ini, atau saat status relay / zona / mode berubah. Data offline selalu disimpan.
    'history_interval' => (int) env('NURSERY_HISTORY_INTERVAL', 60),

    // Riwayat lebih lama dari ini dihapus oleh `php artisan model:prune` (terjadwal harian)
    'history_retention_days' => (int) env('NURSERY_HISTORY_RETENTION_DAYS', 90),

    // Kode pendaftaran bersama. Situs ini publik dan setiap akun bisa mengendalikan pompa, jadi
    // halaman "Daftar Akun" hanya aktif jika REGISTRATION_CODE diisi minimal 8 karakter
    // (kode pendek mudah ditebak). Kosong / terlalu pendek = pendaftaran mati (null).
    // Admin membagikan kode ini sendiri (mis. lewat WhatsApp). Kode bocor? Ganti nilainya di .env.
    // Akun juga tetap bisa dibuat admin lewat `php artisan nursery:user`.
    'registration_code' => (function () {
        $code = trim((string) env('REGISTRATION_CODE', ''));
        return strlen($code) >= 8 ? $code : null;
    })(),

    // Hanya email dengan domain ini yang boleh login / dibuat (login, register, `nursery:user`, seeder).
    'email_domain' => env('ALLOWED_EMAIL_DOMAIN', 'gmail.com'),

    // Akun admin pertama yang dibuat seeder, wajib alamat @email_domain.
    // Jika ADMIN_PASSWORD kosong, seeder membuat password acak.
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

];
