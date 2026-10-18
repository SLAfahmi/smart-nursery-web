# Persiapan MQTT untuk Smart Nursery

Dokumen persiapan sebelum memindahkan komunikasi ESP32 ↔ web dari HTTP polling ke MQTT.

- **Web:** Laravel (repo ini), hosting https://smartnursery-tel-u.srs-ssms.com/
- **Firmware:** ESP32-S3 (`C:\HOSTING_SMART_NURSERY_FIRMWARE_TA`)
- **Status:** persiapan. Belum ada kode yang diubah. Keputusan arsitektur menunggu jawaban staff hosting.
- **Acuan format data:** [RINGKASAN_DAN_REVISI.md](RINGKASAN_DAN_REVISI.md) bagian 6

---

## Daftar Isi

1. [Kenapa MQTT](#1-kenapa-mqtt)
2. [Pertanyaan untuk Staff Hosting](#2-pertanyaan-untuk-staff-hosting)
3. [Pilihan Arsitektur](#3-pilihan-arsitektur)
4. [Rancangan Topik](#4-rancangan-topik)
5. [Checklist Persiapan](#5-checklist-persiapan)
6. [Perubahan yang Dibutuhkan](#6-perubahan-yang-dibutuhkan)
7. [Urutan Pengerjaan](#7-urutan-pengerjaan)
8. [Uji dan Verifikasi](#8-uji-dan-verifikasi)
9. [Masalah Umum](#9-masalah-umum)

---

## 1. Kenapa MQTT

Saat ini ESP32 mengirim telemetry lewat `POST /api/v1/telemetry` tiap 5 detik, dan perintah dari web baru sampai lewat balasan request berikutnya.

| | HTTP (sekarang) | MQTT |
|---|---|---|
| Pola | ESP32 bertanya, server menjawab | Pub/Sub lewat perantara (broker) |
| Perintah web → alat | Menunggu telemetry berikutnya (±5 detik) | Didorong langsung (<1 detik) |
| Koneksi | Buka-tutup tiap request | Satu koneksi TCP tetap terbuka |
| Alat putus mendadak | Server menebak dari "tidak ada kiriman >20 detik" | Broker langsung mengumumkan lewat LWT |
| Data saat alat offline | Buffer LittleFS + `sync-batch` | **Tidak disimpan broker**, buffer tetap dibutuhkan |

**MQTT selalu butuh broker.** ESP32 dan Laravel tidak berbicara langsung; keduanya terhubung ke broker.

```
ESP32 ──publish──▶  [ BROKER ]  ──▶ Laravel (subscribe / webhook)
ESP32 ◀─subscribe── [ BROKER ]  ◀── Laravel (publish)
```

---

## 2. Pertanyaan untuk Staff Hosting

Penentu utama: apakah hosting bisa menjalankan proses yang hidup terus (subscriber MQTT).

- [ ] Apakah ada akses **SSH**?
- [ ] Apakah boleh menjalankan proses permanen (**supervisor / systemd / `screen`**)?
- [ ] Apakah **cron** bisa dijalankan tiap menit (`schedule:run`)?
- [ ] Apakah port **1883 / 8883** terbuka (outbound dari server, dan inbound jika broker dipasang sendiri)?
- [ ] Apakah boleh memasang **Mosquitto** di server?
- [ ] Versi **PHP** dan ekstensi yang aktif (`sockets`, `pcntl`, `openssl`)?
- [ ] Apakah ada batas **memori / CPU** untuk proses yang berjalan lama?
- [ ] Apakah situs memakai **HTTPS** valid (dibutuhkan jika broker cloud memanggil webhook)?

---

## 3. Pilihan Arsitektur

### Opsi A: Shared hosting (tanpa proses permanen)

Broker cloud (HiveMQ Cloud, EMQX Cloud, dsb.) meneruskan pesan telemetry ke API Laravel lewat webhook/rule engine.

```
ESP32 → Broker cloud → (webhook) POST /api/v1/telemetry → Laravel
Laravel → (HTTP API broker) publish topik command → Broker cloud → ESP32
```

- Endpoint, validasi, dan `X-Device-Token` yang sudah ada dipakai ulang.
- Tidak butuh proses permanen di hosting.
- Ketergantungan pada layanan pihak ketiga; fitur webhook berbeda tiap penyedia.

### Opsi B: VPS / SSH penuh

Mosquitto dipasang sendiri; Laravel menjalankan subscriber permanen.

```
ESP32 ⇄ Mosquitto ⇄ php artisan mqtt:listen (paket php-mqtt/client, dijaga supervisor)
```

- Kontrol penuh, tanpa biaya broker.
- Anda yang merawat server, TLS, dan broker.

### Perbandingan

| | Opsi A | Opsi B |
|---|---|---|
| Syarat hosting | Hanya PHP + HTTPS | SSH + proses permanen |
| Kode Laravel baru | Sedikit (publish command) | Command `mqtt:listen` + publish |
| Biaya | Tier gratis broker cloud (ada batas) | Gratis, tapi perlu server |
| Perawatan | Rendah | Sedang–tinggi |
| Risiko | Batas layanan, format webhook | Proses mati, patch keamanan |

**Rekomendasi:** pilih A jika staff menjawab tidak ada SSH/proses permanen, pilih B jika ada.

---

## 4. Rancangan Topik

ID perangkat mengikuti `DeviceControl::DEVICE_KEY` (`NURSERY_01`).

| Topik | Arah | Isi | QoS | Retained |
|---|---|---|---|---|
| `nursery/NURSERY_01/telemetry` | ESP32 → server | JSON telemetry (kontrak §6.2) | 1 | Tidak |
| `nursery/NURSERY_01/command` | server → ESP32 | `device_command` (kontrak §6.4) | 1 | **Ya** |
| `nursery/NURSERY_01/status` | ESP32 → server | `online` / `offline` | 1 | **Ya** |

- **Retained pada `command`:** alat yang baru menyala langsung menerima perintah terakhir. Cocok dengan mekanisme `command_version`.
- **LWT (Last Will):** saat connect, ESP32 mendaftarkan pesan `offline` ke `status`. Jika alat putus tanpa pamit, broker menyiarkannya. Lalu alat publish `online` setelah tersambung.
- **Relay sekali pakai:** perintah `relays` kedaluwarsa 30 detik di web. Jangan di-retain; kirim di topik terpisah `nursery/NURSERY_01/command/relay` (tidak retained) atau sertakan di `command` hanya saat dikirim.
- Batch offline tetap lewat HTTP `POST /api/v1/telemetry/sync-batch`.

---

## 5. Checklist Persiapan

### Broker
- [ ] Pilih opsi (A atau B) setelah jawaban staff.
- [ ] Buat akun broker / pasang Mosquitto.
- [ ] Aktifkan **TLS** (port 8883). Jangan memakai 1883 tanpa enkripsi di internet.
- [ ] Buat kredensial terpisah: satu untuk ESP32 (`NURSERY_01`), satu untuk Laravel.
- [ ] Batasi izin (ACL): ESP32 hanya publish `telemetry`/`status` dan subscribe `command`; Laravel kebalikannya.

### Alat bantu uji
- [ ] Pasang **MQTT Explorer** di laptop.
- [ ] (Opsi B) Pasang klien `mosquitto_pub` / `mosquitto_sub`.
- [ ] Coba publish JSON telemetry contoh secara manual ke topik `telemetry`.

### Web
- [ ] Siapkan variabel `.env`:
  ```
  MQTT_HOST=
  MQTT_PORT=8883
  MQTT_USERNAME=
  MQTT_PASSWORD=
  MQTT_TLS=true
  MQTT_TOPIC_PREFIX=nursery
  ```
- [ ] Buat `config/mqtt.php` yang membaca variabel di atas (gaya sama dengan [config/nursery.php](config/nursery.php)).
- [ ] (Opsi B) `composer require php-mqtt/client`.

### Firmware
- [ ] Pilih library: PubSubClient atau AsyncMqttClient.
- [ ] Siapkan sertifikat CA broker (untuk TLS) atau mode insecure hanya saat uji lokal.
- [ ] Tambah ke `include/config.h`: `MQTT_HOST`, `MQTT_PORT`, `MQTT_USER`, `MQTT_PASSWORD` (rahasia, diisi manual seperti `DEVICE_KEY`).

---

## 6. Perubahan yang Dibutuhkan

### Firmware (`src/tasks/cloud_task.cpp`)
- `buildTelemetry()` tetap dipakai; hasilnya di-publish, bukan di-POST.
- Subscribe `command`, teruskan payload ke `applyDeviceCommand()` yang sudah ada (termasuk `version`, `relays`, `schedules`).
- Daftarkan LWT dan publish `online` setelah connect.
- Reconnect otomatis dengan backoff.
- **Pertahankan buffer offline + HTTP `sync-batch`** sebagai cadangan.

### Web
- Refactor [TelemetryController](app/Http/Controllers/Api/TelemetryController.php): pindahkan logika `store()` (validasi, `syncDevice`, `shouldStoreHistory`, `saveTelemetry`) ke satu service yang dipanggil dari HTTP maupun MQTT.
- Saat user mengubah mode / jadwal / relay (di `DashboardController`), selain menaikkan `command_version`, publish `device_command` ke topik `command`.
- Opsi A: endpoint webhook memakai middleware `VerifyDeviceToken` yang sama.
- Opsi B: command `php artisan mqtt:listen` yang subscribe `telemetry` dan `status`.
- Status online/offline di badge header dibaca dari topik `status` (LWT), dengan cadangan `last_seen_at`.

### Tidak berubah
- Struktur database, `command_version`, riwayat per 60 detik, ekspor CSV, tampilan dashboard.
- Jalur REST tetap hidup (cadangan dan batch offline).

---

## 7. Urutan Pengerjaan

| Tahap | Pekerjaan | Hasil |
|---|---|---|
| **0** | Tanya staff hosting (bagian 2), pilih Opsi A/B | Keputusan arsitektur |
| **1** | Broker + kredensial + TLS + ACL, uji dengan MQTT Explorer | Broker siap |
| **2** | Telemetry lewat MQTT (firmware publish, web terima). HTTP tetap aktif | Data masuk lewat MQTT |
| **3** | Command web → alat lewat MQTT (publish saat `command_version` naik) | Kontrol real-time |
| **4** | LWT / status online, uji putus daya dan putus WiFi | Badge online akurat |
| **5** | Pantau stabil beberapa hari, lalu kurangi HTTP live (pertahankan `sync-batch`) | Migrasi selesai |

Setiap tahap: `php artisan test`, deploy, upload firmware, pantau Serial Monitor.

---

## 8. Uji dan Verifikasi

- `mosquitto_sub -h <host> -p 8883 --cafile ca.crt -u <user> -P <pass> -t "nursery/#" -v` → telemetry muncul tiap ±5 detik.
- Ubah mode di dashboard → pesan muncul di `command`, Serial Monitor mencatat `applyDeviceCommand`, tab Kontrol berubah ke "✓ Tersinkron".
- Cabut daya alat → `status` berubah `offline` dalam hitungan detik (sesuai keepalive).
- Matikan WiFi lalu nyalakan → buffer offline terkirim lewat `sync-batch`, data muncul dengan `is_offline_data = true`.
- Tambah test di `tests/Feature` untuk service telemetry yang dipakai bersama.

---

## 9. Masalah Umum

| Gejala | Penyebab umum |
|---|---|
| Koneksi ditolak (rc=5) | Username/password salah atau ACL menolak |
| Gagal TLS handshake | Sertifikat CA salah atau jam ESP32 belum valid (NTP/RTC) |
| Putus-sambung terus | `clientId` kembar (dua perangkat memakai ID sama) atau keepalive terlalu pendek |
| Pesan tidak sampai ke Laravel | Subscriber mati (Opsi B) atau webhook gagal 401/422 (Opsi A) |
| Alat tidak menerima command | Belum subscribe, ACL salah, atau payload melebihi buffer library |
| Data ganda | HTTP dan MQTT aktif bersamaan untuk telemetry yang sama; matikan salah satu jalur live |
