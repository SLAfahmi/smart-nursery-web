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

    // Halaman register. Mati secara default: situs publik, dan setiap akun bisa mengendalikan pompa.
    // Akun baru dibuat admin lewat `php artisan nursery:user`.
    'allow_registration' => (bool) env('ALLOW_REGISTRATION', false),

    // Akun admin pertama yang dibuat seeder. Jika ADMIN_PASSWORD kosong, seeder membuat password acak.
    'admin_email' => env('ADMIN_EMAIL', 'admin@nursery.com'),
    'admin_password' => env('ADMIN_PASSWORD'),

];
