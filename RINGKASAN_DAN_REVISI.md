# Smart Nursery Web: Ringkasan & Daftar Revisi

Penyelarasan **Smart Nursery Web** (Laravel, repo ini) dengan **Smart Nursery Firmware** (ESP32-S3, `C:\HOSTING_SMART_NURSERY_FIRMWARE_TA`).

- **Hosting:** https://smartnursery-tel-u.srs-ssms.com/
- **Tanggal analisis:** 11 Oktober 2026
- **Dasar analisis:** kode web pada commit `d6a78e0`; kode firmware pada commit `dd66f40`, ditambah perubahan yang belum di-commit (deteksi sensor tanah yang tidak terpasang)

---

## Daftar Isi

1. [Kesimpulan Singkat](#1-kesimpulan-singkat)
2. [Ringkasan Project Web](#2-ringkasan-project-web)
3. [Ringkasan Project Firmware](#3-ringkasan-project-firmware)
4. [Hasil Uji Integrasi](#4-hasil-uji-integrasi)
5. [Daftar Revisi](#5-daftar-revisi)
6. [Kontrak Data Firmware ↔ Web](#6-kontrak-data-firmware--web)
7. [Parameter Fuzzy Firmware (Acuan)](#7-parameter-fuzzy-firmware-acuan)
8. [Urutan Pengerjaan](#8-urutan-pengerjaan)
9. [Uji Lokal: ESP32 → Laravel di Laptop](#9-uji-lokal-esp32--laravel-di-laptop)

---

## 1. Kesimpulan Singkat

Saat ini **tidak ada data dari ESP32 yang bisa masuk ke web**. Penyebabnya:

- URL endpoint berbeda.
- Nama field JSON berbeda.
- Format batch berbeda.
- Web menolak nilai `null` yang dikirim firmware saat sensor error.

Ada juga beberapa fitur di web yang terlihat jalan tetapi **tidak pernah sampai ke alat**: tombol relay, konfigurasi sistem, konfigurasi fuzzy, dan jadwal.

**Pendekatan yang disarankan:** web menyesuaikan format firmware. Format di `README.md` firmware dijadikan acuan, karena firmware sudah terdokumentasi dan sudah diuji di alat. Perubahan di firmware cukup kecil (lihat [5.F](#f-perubahan-di-sisi-firmware)).

---

## 2. Ringkasan Project Web

**Stack:** Laravel 13, PHP 8.3+, Blade, MySQL/SQLite.

### 2.1 Fitur

| Bagian | Isi |
|---|---|
| **Autentikasi** | Login, register, dan logout berbasis session ([AuthController.php](app/Http/Controllers/AuthController.php)). Setiap aksi tercatat di `activity_logs`. |
| **Audit user** | Dashboard menampilkan "Terakhir diubah oleh [nama] pada [waktu]". Riwayat 10 aksi terakhir tampil di tab Log. |
| **Dashboard** | Satu halaman dengan 4 tab ([dashboard.blade.php](resources/views/dashboard.blade.php)). Data diperbarui tiap 3 detik dari `/api/dashboard/status`. |
| Tab Dashboard | Kartu suhu, cahaya, kelembaban udara, rata-rata kelembaban tanah, dan kartu per zona. |
| Tab Kontrol | Pilih mode (Auto/Manual/Schedule), setelan manual (toggle/timer), jadwal 3 slot per zona, tombol relay per zona. |
| Tab Log | Tabel audit log aktivitas user. |
| Tab Config | Parameter fuzzy, interval sensor, interval log, cooldown, export CSV, hapus log. |

### 2.2 Route

**Web** (wajib login, [routes/web.php](routes/web.php)):

| Route | Fungsi |
|---|---|
| `GET /`, `GET /dashboard` | Halaman dashboard |
| `GET /api/dashboard/status` | Data live untuk polling dashboard |
| `POST /api/update-mode` | Ubah mode dan setelan manual |
| `POST /api/toggle-relay` | Toggle relay (saat ini hanya mencatat log) |
| `POST /api/save-schedule` | Simpan jadwal |
| `GET/POST /api/fuzzy-config`, `POST /api/fuzzy-config/reset` | Parameter fuzzy |
| `POST /api/sys-config` | Interval sensor/log dan cooldown |
| `GET /api/export-csv`, `POST /api/reset-log` | Export dan hapus log sensor |

**API untuk ESP32** (tanpa autentikasi, [routes/api.php](routes/api.php)):

| Route | Fungsi |
|---|---|
| `POST /api/v1/telemetry` | Terima data live dan balas dengan `device_command` |
| `POST /api/v1/telemetry/sync-batch` | Terima data dari buffer offline |
| `GET /api/v1/device-command` | Baca perintah terkini (tidak dipakai firmware) |

### 2.3 Database

| Tabel | Isi |
|---|---|
| `users` | Akun login |
| `device_controls` | Satu baris `NURSERY_01`: `system_mode`, `manual_mode_type`, `manual_duration`, `schedules_json`, `fuzzy_config`, `sys_config`, `updated_by_user_id` |
| `activity_logs` | Riwayat aksi user (`action`, `details_json`) |
| `sensor_logs` | Suhu, kelembaban, cahaya, mode, `is_offline_data`, `recorded_at` |
| `zone_logs` | Per zona: `soil_moisture`, `zone_mode`, `duration`, `relay_status` |

### 2.4 Test

[TelemetryApiTest.php](tests/Feature/TelemetryApiTest.php) berisi 3 test API. Ketiganya lulus, tetapi memakai format payload yang **berbeda dari firmware asli**.

---

## 3. Ringkasan Project Firmware

**Hardware:** ESP32-S3 N16R8.

| Komponen | Jumlah / jenis |
|---|---|
| Zona | 3 |
| Sensor | DHT22 (suhu/kelembaban udara), BH1750 (cahaya), RTC DS3231, 3 sensor kelembaban tanah |
| Relay | 1 pompa utama + 3 valve zona |

### 3.1 Mode Operasi

| Mode | Perilaku |
|---|---|
| **Auto (0)** | Fuzzy Mamdani dengan input tanah, suhu, dan cahaya, menghasilkan durasi siram 0–222 detik. Ada cooldown per zona (default 4 jam). Zona tanpa sensor tanah tidak disiram. |
| **Manual (1)** | Tipe *Toggle* (nyala/mati langsung) atau *Timer* (mati otomatis, default 60 detik). Relay hanya bisa dikontrol di mode ini. |
| **Schedule (2)** | 3 slot per zona. Jadwal dilewati jika kelembaban tanah > 60%. |

Siklus irigasi tiap zona: `idle → queued → watering → soaking (15 detik) → idle`. Pompa utama menyala otomatis mengikuti valve.

### 3.2 Konektivitas

- **WiFi AP** `SmartNursery-TelU`: dashboard lokal di `http://192.168.4.1`, dengan REST API `/api/data`, `/api/override`, `/api/relay`, `/api/config`, `/api/schedule`, `/api/log`, `/api/sync-time`.
- **WiFi STA**, lewat `CloudSyncTask`:
  - Kirim telemetry tiap **5 detik** dengan header `X-Device-Token`.
  - Jika gagal atau offline, simpan snapshot tiap 30 detik ke buffer LittleFS (maksimal 50 data).
  - Saat online lagi, kirim isi buffer sekaligus ke URL batch.
  - Terapkan `device_command` dari balasan server.
  - Sinkronkan waktu dari NTP ke RTC.

---

## 4. Hasil Uji Integrasi

Payload disusun persis seperti `buildTelemetry()` di `src/tasks/cloud_task.cpp` firmware, lalu dikirim ke API web lewat test Laravel lokal:

```
POST /api/v1/telemetry             → 422  error: light_level, zones.*.zone_id, zones.*.soil_moisture
POST /api/v1/telemetry/sync-batch  → 422  error: batch
POST /api/telemetry (URL firmware) → 404
```

Akibatnya di alat:

1. Telemetry ditolak, lalu firmware menyimpannya ke buffer offline.
2. Batch dari buffer juga ditolak.
3. Buffer penuh di 50 data, dan data terlama dibuang.
4. Database web tetap kosong.

---

## 5. Daftar Revisi

Keterangan prioritas: 🔴 kritis (integrasi tidak jalan) · 🟠 penting (fitur tidak berfungsi) · 🟡 perbaikan

### A. Integrasi Data (🔴 Kritis)

- [x] **A1. Samakan URL endpoint.** *(selesai: URL sudah diisi di `config.h` firmware)*
  `include/config.h` firmware masih berisi placeholder. Isi dengan:
  ```cpp
  #define CLOUD_URL       "https://smartnursery-tel-u.srs-ssms.com/api/v1/telemetry"
  #define CLOUD_BATCH_URL "https://smartnursery-tel-u.srs-ssms.com/api/v1/telemetry/sync-batch"
  ```

- [x] **A2. Samakan nama field telemetry.** *(selesai: kolom `zone_mode` dan `duration` di `zone_logs` dihapus karena tidak dikirim firmware)*
  Ubah validasi dan penyimpanan di [TelemetryController.php](app/Http/Controllers/Api/TelemetryController.php#L22-L36):

  | Dikirim firmware | Saat ini diharapkan web | Revisi web |
  |---|---|---|
  | `light` | `light_level` | terima `light` |
  | `relay_main` | — | simpan (kolom baru) |
  | `zones[].zone` | `zones[].zone_id` | terima `zone` |
  | `zones[].soil` | `zones[].soil_moisture` | terima `soil` |
  | `zones[].relay` | `zones[].relay_status` | terima `relay` |
  | `zones[].state` (teks) | `zone_mode` (angka) | simpan `state` (kolom teks baru) |
  | `zones[].fuzzy_duration` | `duration` (bulat) | simpan sebagai desimal |

- [x] **A3. Terima nilai `null` dan desimal.** *(selesai: dashboard menampilkan `--` untuk nilai `null`)*
  Firmware mengirim `null` saat DHT22/BH1750 error atau sensor tanah tidak terpasang. Kelembaban tanah juga berupa desimal (mis. `45.3`). Yang perlu diubah:
  - Validasi `temperature`, `humidity`, `light`, `soil` menjadi `nullable|numeric`.
  - Migration: kolom tersebut dibuat `nullable`, dan `soil_moisture` diubah dari `integer` ke `float`.

- [x] **A4. Samakan format batch.**
  Firmware mengirim `{"data":[...]}`. Web saat ini mengharapkan `{"batch":[...]}`. Ganti ke `data`, dan pakai validasi item yang sama dengan A2–A3.
  *(selesai: item yang tidak valid dilewati dan dicatat di log, tidak lagi menolak seluruh batch. Balasan berisi `synced_count` dan `skipped_count`.)*

- [x] **A5. Samakan format jadwal.** *(selesai: migration mengonversi jadwal lama secara otomatis. Validasi rentang sama dengan firmware, dan jam/durasi wajib diisi untuk slot aktif.)*
  Firmware membaca `[{"zone":1,"slots":[...]}]`, sedangkan web menyimpan `[{"schedules":[...]}]`. Saat ini firmware tidak menemukan `slots`, jadi jadwal dari web **diabaikan tanpa pesan error**. Yang perlu diubah:
  - Struktur `schedules_json`.
  - Seeder.
  - Default di `DashboardController::index()`.
  - JavaScript `saveSchedule()` dan `populateScheduleInputs()`.

- [x] **A6. Jumlah zona dari 5 menjadi 3.** *(selesai: diatur lewat `config/nursery.php` → `NURSERY_ZONES`)*
  Lokasi yang perlu diubah:
  - [dashboard.blade.php:483](resources/views/dashboard.blade.php#L483) (`NUM_ZONES = 5`)
  - Label "5 Total Zona"
  - [DashboardController.php:176](app/Http/Controllers/DashboardController.php#L176) (`size:5`)
  - Validasi `max:5`
  - Seeder
  - Kolom CSV

  Sebaiknya jumlah zona disimpan di satu tempat, misalnya `config/nursery.php` atau `.env`.

### B. Perintah dari Web ke Alat (🟠 Penting)

- [x] **B1. Tombol relay di web belum mengirim perintah.** *(selesai: perintah dikirim sekali dan kedaluwarsa setelah 30 detik. Ada kontrol pompa utama dan tanda "MENUNGGU" sampai alat melapor.)*
  [toggleRelay()](app/Http/Controllers/DashboardController.php#L137) hanya mencatat activity log. Yang perlu ditambahkan:
  - **Web:** simpan perintah relay yang belum diterima alat, lalu kirim lewat `device_command.relays`.
  - **Web:** tambah kontrol relay pompa utama.
  - **Web:** nonaktifkan tombol relay di luar mode Manual, karena firmware menolak perintah relay di mode lain.
  - **Firmware:** di `applyDeviceCommand()`, baca `relays` dan panggil `manualSet()`.

- [x] **B2. Config sistem belum dikirim ke alat.** *(selesai: satu tombol "Simpan Konfigurasi Sistem", isian terisi nilai tersimpan)*
  `sys_config` (interval sensor, interval log, cooldown) disimpan di database tetapi tidak ikut di `device_command`. Yang perlu ditambahkan:
  - **Web:** tambah `sensor_interval`, `log_interval`, `cooldown` ke `device_command`.
  - **Firmware:** petakan ke `sensorInterval`, `logInterval`, `cooldown` di `applyDeviceCommand()`.
  - **Rentang validasi** disamakan dengan firmware:

    | Field | Rentang |
    |---|---|
    | sensor | 2–3600 detik (web saat ini mulai dari 1) |
    | log | 60–86400 detik |
    | cooldown | 0–86400 detik |

  - **Form:** isi dengan nilai yang tersimpan saat dibuka (sekarang selalu tampil nilai default).

- [x] **B3. Konfigurasi Fuzzy di web tidak berpengaruh.** *(selesai: route, method, dan kolom `fuzzy_config` dihapus. Panel read-only sempat ditampilkan, lalu dihapus (17 Oktober 2026) agar tab Config web sama dengan tab Config dashboard lokal firmware. Parameter acuan tetap ada di [bagian 7](#7-parameter-fuzzy-firmware-acuan).)*
  Parameter fuzzy di firmware tertulis tetap di `src/fuzzy_logic.cpp` dan tidak bisa diubah dari luar. Selain itu, nilai default di web berbeda dan arahnya terbalik:

  | | Web | Firmware |
  |---|---|---|
  | Kelembaban tanah | "basah" 30–50%, "kering" 60–80% | kering ≤30–45%, basah ≥50–65% |
  | Output durasi | maks 30 detik | 0–222 detik |

  **Saran:** ubah panel menjadi tampilan **read-only** berisi parameter asli firmware (lihat [bagian 7](#7-parameter-fuzzy-firmware-acuan)), lalu hapus route simpan dan reset.

- [x] **B4. Perubahan di alat bisa tertimpa server.**
  Web mengirim `device_command` di **setiap** balasan telemetry. Jika mode diubah lewat dashboard lokal (AP) saat offline, server mengembalikannya ke nilai lama dalam ±5 detik begitu alat online. Solusi:
  - Tambah kolom `command_pending` (atau nomor versi) di `device_controls`. Kolom ini di-set saat user mengubah sesuatu di web.
  - Kirim `device_command` hanya jika `command_pending = true`, lalu set kembali ke `false`.
  - Jika tidak ada perintah tertunda, perbarui `device_controls.system_mode` dari telemetry, supaya web menampilkan mode yang sebenarnya berjalan di alat.

  *(Selesai dengan **nomor versi**, bukan flag, supaya perintah tidak hilang jika satu balasan gagal sampai:)*
  - *Setiap perubahan dari web menaikkan `device_controls.command_version`.*
  - *Firmware melaporkan versi yang sudah ia terapkan (`command_version` di telemetry). Selama versinya berbeda, server mengirim konfigurasi penuh.*
  - *Setelah alat restart, versinya kembali 0. Server lalu mengirim ulang konfigurasi web, termasuk mode, yang memang tidak disimpan firmware.*
  - *Jika versinya sama, konfigurasi yang dilaporkan alat (mode, config, jadwal) disalin ke web. Perubahan dari dashboard lokal ikut tampil sebagai "diubah oleh System".*
  - *Status alat (versi, waktu terakhir terlihat) disimpan di tabel baru `device_states`. Tab Kontrol menampilkan "Menunggu alat menerapkan perubahan..." atau "✓ Tersinkron dengan alat".*

### C. Data & Tampilan (🟡 Perbaikan)

- [x] **C1. Zona waktu.** *(selesai, dimajukan ke Tahap 1 agar data baru tidak memakai acuan waktu campur)*
  [config/app.php:68](config/app.php#L68) masih `UTC`, padahal firmware mengirim `recorded_at` dalam waktu lokal WIB tanpa offset. Ganti ke `'Asia/Jakarta'`.

- [x] **C2. Tampilkan data firmware yang sekarang belum ditampilkan:** *(selesai: badge header "Alat Online/Offline" dengan batas 20 detik, ditambah peringatan sensor error di tab Dashboard)*
  - Status pompa utama (`relay_main`).
  - Status zona: menyiram, antre, jeda serap, idle.
  - Durasi hasil fuzzy per zona.
  - Tanda sensor error/tidak terpasang (tampil `--`).
  - **Status perangkat online/offline**, berdasarkan waktu telemetry terakhir (mis. offline jika lebih dari 15 detik). Badge "Live" sekarang selalu tampil.

- [x] **C3. Dashboard bisa menampilkan data lama.** *(selesai: kartu "kondisi terkini" dibaca dari `device_states.state`, dengan cadangan riwayat live terakhir. Data batch offline **tetap disimpan seluruhnya ke riwayat** (`is_offline_data = true`) dan ikut di export CSV dengan Sumber = Offline. Data itu hanya tidak dipakai sebagai "kondisi terkini" karena sudah lama.)*
  `getStatus()` memakai `SensorLog::latest()`, yang mengurutkan berdasarkan waktu data masuk ke server (`created_at`). Begitu batch offline dikirim, data lama itu tercatat paling baru masuk, sehingga tampil sebagai data terbaru. Urutkan berdasarkan `recorded_at`, atau abaikan `is_offline_data = true`.

- [x] **C4. Jumlah data membengkak.** *(selesai: riwayat disimpan tiap 60 detik (`NURSERY_HISTORY_INTERVAL`) **atau** saat relay/status zona/mode berubah, jadi penyiraman singkat tetap tercatat. Pembersihan harian lewat `model:prune` menghapus data > 90 hari (`NURSERY_HISTORY_RETENTION_DAYS`) dan **butuh cron di hosting**.)*
  Kirim tiap 5 detik berarti sekitar **17.280 baris `sensor_logs` + 51.840 baris `zone_logs` per hari**. Saran:
  - Simpan status terkini di satu baris (upsert), misalnya tabel `device_states`, untuk tampilan live.
  - Simpan riwayat hanya per interval, misalnya 1 menit atau mengikuti `log_interval`.
  - Data dari batch offline tetap disimpan semua.
  - Tambah perintah terjadwal untuk menghapus data lama (mis. lebih dari 90 hari).

- [x] **C5. Export CSV.** *(selesai: export **per hari** (pilih tanggal) atau rentang tanggal tanpa batas maksimal; data ditulis bertahap per 500 baris. Kolom per zona: tanah, valve, status; ditambah pompa, mode, dan sumber Live/Offline.)*
  Export saat ini hanya mengambil 100 baris terakhir (±8 menit data jika kirim tiap 5 detik) dan memakai `created_at`. Yang perlu diubah:
  - Tambah filter rentang tanggal.
  - Pakai `recorded_at`.
  - Sesuaikan kolom dengan 3 zona.
  - Tambah BOM UTF-8 agar Excel membaca simbol `°`.

### D. Keamanan (🟠 Penting, karena situs sudah publik)

- [x] **D1. Autentikasi API perangkat.** *(selesai: middleware `VerifyDeviceToken` + `throttle:60,1`. Jika `DEVICE_TOKEN` kosong, semua request ditolak.)*
  Firmware sudah mengirim header `X-Device-Token`, tetapi web tidak pernah memeriksanya. Siapa pun bisa mengirim data palsu atau membaca perintah. Yang perlu ditambahkan:
  - Middleware yang mencocokkan header dengan `DEVICE_TOKEN` di `.env`, dengan nilai yang sama seperti `DEVICE_KEY` di firmware.
  - Pengaman ini dipasang juga di `GET /api/v1/device-command`.
  - Rate limiting di API.

- [x] **D2. Registrasi terbuka.** *(selesai: register mati secara default lewat `ALLOW_REGISTRATION=false` (route mengembalikan 404 dan link di halaman login disembunyikan). Akun baru dibuat admin dengan `php artisan nursery:user <email> --name="..."`.)*
  Siapa pun bisa membuat akun lalu mengendalikan pompa. Matikan halaman register, atau buat persetujuan akun oleh admin.

- [x] **D3. Akun bawaan.** *(selesai di kode: seeder memakai `ADMIN_EMAIL`/`ADMIN_PASSWORD`, atau password acak yang ditampilkan sekali. **Masih manual di server:** akun lama tetap memakai `admin123` sampai diganti dengan `php artisan nursery:user admin@nursery.com`.)*
  Seeder membuat `admin@nursery.com` dengan password `admin123`. Ganti password di server produksi.

- [x] **D4. Pesan error internal ikut terkirim.** *(selesai, dikerjakan bersama A2)*
  Respons API menyertakan `$e->getMessage()`. Hapus dari respons, cukup dicatat di log.

- [ ] **D5. Mode debug.** *(tidak ada perubahan kode. Langkah ini masuk checklist [README.md § Deploy](README.md#deploy-ke-hosting) dan harus dicek langsung di `.env` server.)*
  Pastikan `.env` di hosting berisi `APP_ENV=production` dan `APP_DEBUG=false`.

### E. Dokumentasi & Test (🟡 Perbaikan)

- [x] **E1.** *(selesai: berisi fitur, instalasi, variabel `.env`, akun, API, checklist deploy, dan daftar test)* Ganti [README.md](README.md) (masih bawaan Laravel) dengan dokumentasi project: instalasi, `.env`, endpoint, dan format data.
- [x] **E2.** *(selesai: revisi v5, 3 zona, struktur tabel terkini termasuk `device_states`, alur `command_version`, status implementasi)* Perbarui [arsitektur_migrasi_hosting_hybrid.md](arsitektur_migrasi_hosting_hybrid.md). Dokumen ini masih menulis 5 zona dan struktur tabel lama.
- [x] **E3.** *(selesai: keempat test di bawah ada di [TelemetryApiTest.php](tests/Feature/TelemetryApiTest.php). Fitur Tahap 2–3 diuji di `DeviceSyncTest` dan `HistoryAndExportTest`, D2–D3 di `AccountSecurityTest`. Total 49 test lulus, 18 Oktober 2026.)* Perbarui [TelemetryApiTest.php](tests/Feature/TelemetryApiTest.php) agar memakai payload asli firmware. Tambah test untuk:
  - Nilai `null` dari sensor.
  - Batch `{"data":[...]}`.
  - Token salah (harus 401).
  - Format `device_command`.

### F. Perubahan di Sisi Firmware

Perubahan yang dibutuhkan di firmware:

| # | File | Perubahan | Wajib? |
|---|---|---|---|
| F1 | `include/config.h` | Isi `CLOUD_URL`, `CLOUD_BATCH_URL`, `DEVICE_KEY`, `STA_SSID`/`STA_PASSWORD`. *URL sudah diisi. `DEVICE_KEY` dan `STA_PASSWORD` diisi manual karena bersifat rahasia.* | Wajib |
| F2 | — | Commit perubahan yang masih tertunda (deteksi sensor tanah yang tidak terpasang). Web harus bisa menerima `soil: null` dari perubahan ini. | Wajib |
| F3 | `src/tasks/cloud_task.cpp` | Di `applyDeviceCommand()`, tambah pembacaan `sensor_interval`, `log_interval`, `cooldown`. *Selesai, ditambah laporan `command_version`/`config`/`schedules` di telemetry (B4).* | ✅ Selesai |
| F4 | `src/tasks/cloud_task.cpp` | Di `applyDeviceCommand()`, tambah pembacaan `relays` lalu panggil `manualSet()`. *Selesai, sudah di-compile (`pio run` SUCCESS).* | ✅ Selesai |
| F5 | `README.md` | Perbarui bagian "Format cloud" sesuai [bagian 6](#6-kontrak-data-firmware--web). *Selesai, termasuk `command_version`/`config`/`schedules` dan isi `device_command` terbaru. Belum di-commit di repo firmware.* | ✅ Selesai |
| F6 | `include/config.h`, `src/tasks/cloud_task.cpp` | **Usulan.** Buffer offline sekarang hanya 50 snapshot × 30 detik = **±25 menit terakhir**; jika alat offline lebih lama, data lama dibuang. Untuk menampung misalnya 24 jam, dibutuhkan: buffer lebih besar, pengiriman batch bertahap (mis. 30 data per request, bukan satu request besar), dan rotasi file supaya file besar tidak dibaca ke RAM. Log kejadian lokal (`logs.bin`) juga belum dikirim ke server. | Menunggu keputusan |

---

## 6. Kontrak Data Firmware ↔ Web

Format ini menjadi acuan bersama, dan sudah diterapkan di web maupun firmware (Tahap 2).

### 6.1 Header (semua request dari ESP32)

```
Content-Type: application/json
Accept: application/json
X-Device-Token: <DEVICE_KEY>
```

### 6.2 Telemetry: `POST /api/v1/telemetry`

```json
{
  "recorded_at": "2026-10-11 12:00:00",
  "temperature": 28.4,
  "humidity": 71.2,
  "light": 12345.5,
  "system_mode": 0,
  "relay_main": false,
  "zones": [
    { "zone": 1, "soil": 45.3, "relay": false, "state": "idle",     "fuzzy_duration": 0 },
    { "zone": 2, "soil": null, "relay": false, "state": "idle",     "fuzzy_duration": 0 },
    { "zone": 3, "soil": 30.1, "relay": true,  "state": "watering", "fuzzy_duration": 95.2 }
  ],
  "command_version": 5,
  "config": { "sensorInterval": 10, "manualMode": 0, "manualDuration": 60, "logInterval": 3600, "cooldown": 14400 },
  "schedules": [ { "zone": 1, "slots": [ ... ] }, ... ]
}
```

`command_version`, `config`, dan `schedules` hanya dikirim pada telemetry live. Ketiganya tidak disimpan di buffer offline, karena berisi data lama dan memperbesar ukuran batch.

| Field | Tipe | Keterangan |
|---|---|---|
| `recorded_at` | string / `null` | Waktu lokal WIB `YYYY-MM-DD HH:MM:SS`. `null` jika jam alat belum valid (server memakai waktu terima). |
| `temperature`, `humidity`, `light` | angka / `null` | `null` jika sensor error |
| `system_mode` | 0 / 1 / 2 | Auto / Manual / Schedule |
| `relay_main` | boolean | Status pompa utama |
| `zones[].soil` | angka / `null` | Persen 0–100. `null` jika sensor tidak terpasang. |
| `zones[].state` | string | `idle`, `queued`, `watering`, `soaking` |
| `zones[].fuzzy_duration` | angka | Rekomendasi fuzzy terakhir (detik) |
| `command_version` | angka | Versi `device_command` terakhir yang diterapkan alat (0 setelah boot) |
| `config` | objek | Pengaturan yang berlaku di alat (format `GET /api/config` lokal) |
| `schedules` | array | Jadwal yang berlaku di alat (format `GET /api/schedule` lokal) |

### 6.3 Batch offline: `POST /api/v1/telemetry/sync-batch`

```json
{ "data": [ <telemetry>, <telemetry>, ... ] }
```

### 6.4 Balasan server

Balasan dengan kode HTTP 2xx dianggap sukses oleh firmware. `device_command` berisi konfigurasi penuh jika `command_version` alat ≠ versi web, dan berisi `relays` jika ada perintah relay. Jika tidak ada keduanya, bernilai `null`.

```json
{
  "status": "success",
  "device_command": {
    "version": 6,
    "system_mode": 1,
    "manual_mode_type": 0,
    "manual_duration": 60,
    "sensor_interval": 10,
    "log_interval": 3600,
    "cooldown": 14400,
    "relays": [ { "relay": "main", "on": true }, { "relay": 1, "on": false } ],
    "schedules": [
      { "zone": 1, "slots": [ { "active": true,  "hour": 6,  "minute": 0, "duration": 60 },
                              { "active": false, "hour": 12, "minute": 0, "duration": 60 },
                              { "active": false, "hour": 17, "minute": 0, "duration": 60 } ] },
      { "zone": 2, "slots": [ ... ] },
      { "zone": 3, "slots": [ ... ] }
    ]
  }
}
```

| Field | Rentang | Catatan |
|---|---|---|
| `version` | angka | Dicatat alat setelah semua isi perintah diterapkan |
| `system_mode` | 0–2 | Diterapkan pertama |
| `manual_mode_type` | 0 (Toggle) / 1 (Timer) | |
| `manual_duration` | 1–3600 detik | |
| `sensor_interval` | 2–3600 detik | |
| `log_interval` | 60–86400 detik | |
| `cooldown` | 0–86400 detik | |
| `schedules` | 3 zona × 3 slot. `hour` 0–23, `minute` 0–59, `duration` 1–3600 | |
| `relays` | `relay`: `"main"` atau 1–3. `on`: boolean | Diterapkan terakhir, hanya pada mode Manual. Dikirim sekali, kedaluwarsa 30 detik. |

---

## 7. Parameter Fuzzy Firmware (Acuan)

Nilai ini diambil dari `src/fuzzy_logic.cpp` firmware. Inilah parameter yang benar-benar dipakai alat, dan yang seharusnya tampil di web (B3).

**Input: Kelembaban Tanah (%)**

| Himpunan | Fungsi keanggotaan |
|---|---|
| Kering | 1 jika ≤ 30, turun linier sampai 0 di 45 |
| Lembab | naik 30 → 45, bernilai 1 di 45–50, turun 50 → 65 |
| Basah | naik 50 → 65, bernilai 1 jika ≥ 65 |

**Input: Suhu Udara (°C)**

| Himpunan | Fungsi keanggotaan |
|---|---|
| Dingin | 1 jika ≤ 22, turun sampai 0 di 26 |
| Normal | naik 24 → 28, turun 28 → 32 |
| Panas | naik 30 → 35, bernilai 1 jika ≥ 35 |

**Input: Cahaya (lux)**

| Himpunan | Fungsi keanggotaan |
|---|---|
| Redup | 1 jika ≤ 5.000, turun sampai 0 di 25.000 |
| Sedang | naik 5.000 → 35.000, turun 35.000 → 65.000 |
| Terang | naik 45.000 → 65.000, bernilai 1 jika ≥ 65.000 |

**Output: Durasi Penyiraman (detik)**

| Himpunan | Fungsi keanggotaan |
|---|---|
| Mati | 1 di 0, turun sampai 0 di 10 |
| Pendek | naik 10 → 50, turun 50 → 90 |
| Sedang | naik 70 → 130, turun 130 → 190 |
| Lama | naik 170 → 222, bernilai 1 jika ≥ 222 |

**Inferensi:**
- Mamdani dengan 27 aturan: Mati 9, Pendek 5, Sedang 7, Lama 6.
- Defuzzifikasi centroid pada rentang 0–222 detik dengan langkah 1 detik.
- Hasil di bawah 10 detik dibulatkan menjadi 0 (tidak menyiram).

---

## 8. Urutan Pengerjaan

| Tahap | Revisi | Hasil |
|---|---|---|
| **1** | A1–A6, D1, F1, F2 | Data dari alat masuk ke hosting dengan aman, jadwal dari web diterima alat |
| **2** | B1–B4, F3, F4 | Semua kontrol di web benar-benar mengendalikan alat, tanpa saling menimpa dengan dashboard lokal |
| **3** | C1–C5 | Waktu benar, tampilan lengkap, database tidak membengkak |
| **4** | D2–D5, E1–E3, F5 | Situs aman untuk publik, dokumentasi dan test sesuai kondisi akhir |

Setiap selesai satu tahap: jalankan `php artisan test`, deploy ke hosting (`php artisan migrate`), upload firmware, lalu pantau Serial Monitor. Telemetry yang berhasil tidak menampilkan baris `[CLOUD] Kirim telemetry gagal`.

### 8.1 Status Tahap 1 (11 Oktober 2026)

Kode web selesai dan teruji:

- `php artisan test`: 16 test lulus.
- Migration diuji di MySQL 8.4 lokal (Laragon, port 3306, database `smart_nursery_srs`) dengan data asli: 643 `sensor_logs` dan jadwal format lama. Urutan uji: up → rollback → up. Data tetap utuh, dan jadwal dikonversi ke format firmware.
- Payload firmware dikirim ke aplikasi yang terhubung ke MySQL lokal. Hasil: token salah → 401, telemetry → 201, batch → 200, nilai `null` tersimpan.

Yang masih harus dikerjakan manual:

| Langkah | Di mana | Perintah / isi |
|---|---|---|
| 1. Backup database server | Hosting | `mysqldump` atau export lewat phpMyAdmin |
| 2. Upload/pull kode terbaru | Hosting | — |
| 3. Tambah token ke `.env` server | Hosting | `DEVICE_TOKEN=<token acak>`. Buat dengan `php -r "echo bin2hex(random_bytes(24));"` |
| 4. Jalankan migration | Hosting | `php artisan migrate --force` |
| 5. Muat ulang konfigurasi | Hosting | `php artisan config:clear`, atau `php artisan config:cache` jika server memakai cache config |
| 6. Isi token & WiFi di firmware | `include/config.h` | `DEVICE_KEY` = token yang sama dengan langkah 3, lalu `STA_PASSWORD` |
| 7. Commit perubahan firmware (F2) | Repo firmware | Perubahan yang tertunda belum di-commit |
| 8. Upload firmware & pantau | PlatformIO | `pio run -t upload`, lalu `pio device monitor` |

> Konfigurasi database (host/port/nama DB) hanya dibaca dari `.env` masing-masing, tidak ada yang tertulis di kode. `.env` tidak ikut ter-push (ada di `.gitignore`), jadi lokal (Laragon 3306) dan server tidak saling menimpa.

---

## 9. Uji Lokal: ESP32 → Laravel di Laptop

> ⚠️ **Pengingat:** sebelum upload firmware final ke alat, pastikan baris `#define USE_LOCAL_SERVER` di `include/config.h` **dimatikan lagi** (diberi `//`). Jika tidak, alat mengirim data ke laptop, bukan ke hosting.

**Jangan pakai `127.0.0.1`.** Di ESP32, `127.0.0.1` berarti ESP32 itu sendiri. ESP harus memanggil laptop lewat IP laptop di WiFi yang sama.

### 9.1 Persiapan (sekali saja)

1. **Satukan jaringan.**
   - Laptop dan ESP harus tersambung ke WiFi yang sama. Paling mudah: **hotspot HP**.
   - Isi `STA_SSID` dan `STA_PASSWORD` di `include/config.h` dengan nama dan password hotspot.
   - WiFi kampus (TelU-Connect) biasanya memblokir koneksi antar perangkat, jadi tidak bisa dipakai.
   - Jangan pakai IP `192.168.56.1`, karena itu adaptor VirtualBox.
2. **Cari IP laptop** dengan `ipconfig`, lihat bagian *Wi-Fi*. Biasanya `192.168.43.x` atau `172.20.10.x`.
3. **Buka firewall untuk port 8000.** Jalankan di PowerShell (Run as Administrator):
   ```
   New-NetFirewallRule -DisplayName "Laravel dev 8000" -Direction Inbound -Protocol TCP -LocalPort 8000 -Action Allow -Profile Private
   ```
   Lalu ubah profil jaringan hotspot menjadi **Private**: Settings → Wi-Fi → nama hotspot → *Network profile type*.
4. **Isi token di `.env` lokal:** `DEVICE_TOKEN=<token>`. Nilainya harus sama dengan `DEVICE_KEY` di firmware.

### 9.2 Setiap kali uji

1. Jalankan Laravel supaya bisa diakses perangkat lain:
   ```
   php artisan serve --host=0.0.0.0 --port=8000
   ```
2. **Cek dari HP dulu.** Buka `http://<IP-laptop>:8000/login`. Kalau halaman muncul, ESP juga bisa menjangkau laptop.
3. Di `include/config.h`:
   - Aktifkan `#define USE_LOCAL_SERVER` (hapus `//`).
   - Isi IP laptop di `CLOUD_BASE_URL`.
4. Upload firmware: `pio run -t upload`, lalu `pio device monitor`.
5. Pantau tiga tempat:

   | Tempat | Tanda berhasil |
   |---|---|
   | Serial Monitor | Tidak ada baris `[CLOUD] Kirim telemetry gagal` |
   | Terminal `php artisan serve` | Muncul `POST /api/v1/telemetry` tiap ±5 detik |
   | Dashboard `http://127.0.0.1:8000` | Angka sensor dan zona ikut berubah |

6. **Selesai uji:** matikan lagi `USE_LOCAL_SERVER`, lalu upload ulang firmware.

### 9.3 Arti kode error di Serial Monitor

| Muncul | Penyebab |
|---|---|
| `HTTP -1` | ESP tidak bisa menjangkau laptop: beda jaringan, firewall, atau lupa `--host=0.0.0.0` |
| `HTTP 401` | `DEVICE_KEY` di firmware ≠ `DEVICE_TOKEN` di `.env` |
| `HTTP 404` | URL salah, misalnya kurang `/api/v1` |
| `HTTP 422` | Isi data ditolak; detailnya ada di `storage/logs/laravel.log` |
| `HTTP 429` | Terlalu banyak request (lebih dari 60/menit) |
