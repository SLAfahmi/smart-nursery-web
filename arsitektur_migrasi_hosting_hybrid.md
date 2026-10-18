# Arsitektur Sistem Hybrid Smart Oil Palm Nursery (Revisi v5)

Spesifikasi sistem **Smart Oil Palm Nursery**: ESP32-S3 yang tetap bekerja mandiri, ditambah web **Laravel + MySQL** di hosting untuk monitoring, kontrol jarak jauh, audit user, dan riwayat data.

> **Revisi v5 (18 Oktober 2026):** disesuaikan dengan kode akhir. Jumlah zona 3, struktur tabel setelah migration Oktober 2026, sinkronisasi dua arah berbasis `command_version`, registrasi publik ditutup. Detail format JSON ada di [RINGKASAN_DAN_REVISI.md bagian 6](RINGKASAN_DAN_REVISI.md#6-kontrak-data-firmware--web).

---

## 1. Login & Audit User

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                         SISTEM AKSES & AUDIT USER                           │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. LOGIN PENGGUNA:                                                          │
│    Setiap operator/admin login dengan email & password masing-masing.       │
│    Registrasi publik ditutup; akun dibuat admin (php artisan nursery:user). │
│                                                                             │
│ 2. PENCATATAN AKSI (AUDIT TRAIL):                                           │
│    Saat user mengubah mode, jadwal, config, atau menekan relay, Laravel     │
│    menyimpan ID user dan detail perubahan di activity_logs.                 │
│                                                                             │
│ 3. INFORMASI DI DASHBOARD:                                                  │
│    "Mode diubah ke Manual oleh: Budi (30 Jul 2026, 14:10 WIB)"              │
│    Perubahan dari dashboard lokal alat tampil sebagai "oleh System".        │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Tampilan Dashboard Web

Tema mengikuti dashboard lokal firmware (`data/www/index.html` & `style.css` di repo firmware), ditambah informasi user dan status alat.

```text
┌─────────────────────────────────────────────────────────────────────────┐
│ [Icon] Smart Nursery        [Budi (Logout)] [● Alat Online / Offline]   │
├─────────────────────────────────────────────────────────────────────────┤
│ Tab: Dashboard | Kontrol | Log | Config                                 │
├─────────────────────────────────────────────────────────────────────────┤
│ Kondisi Lingkungan:                                                     │
│ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ │
│ │ Suhu (°C)     │ │ Cahaya (Lux)  │ │ Lembab Udara  │ │ Rata2 Tanah   │ │
│ └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘ │
│ Pompa utama: ON/OFF          ⚠ Peringatan sensor error (jika ada)       │
├─────────────────────────────────────────────────────────────────────────┤
│ Zona 1..3: kelembaban tanah, valve, status (idle/antre/menyiram/jeda),  │
│            durasi fuzzy terakhir                                        │
├─────────────────────────────────────────────────────────────────────────┤
│ Kontrol: Mode [Auto|Manual|Schedule], setelan manual, jadwal 3 slot,    │
│          tombol relay (hanya Manual), "✓ Tersinkron dengan alat"        │
└─────────────────────────────────────────────────────────────────────────┘
```

Nilai sensor yang error / tidak terpasang tampil `--`. Status online dihitung dari telemetry terakhir (offline jika lebih dari 20 detik).

---

## 3. Konsep Hybrid (Resilience Mode)

```text
                  ┌─────────────────────────────────────────┐
                  │          ESP32-S3 Controller            │
                  │ (Fuzzy, Schedule, Manual & RTC DS3231)  │
                  └────────────────────┬────────────────────┘
                                       │
                  ┌────────────────────┴────────────────────┐
                  │     Cek Koneksi Internet / Wi-Fi        │
                  └──────────┬───────────────────┬──────────┘
                             │                   │
                     [ Ada Internet ]     [ Internet Putus ]
                             │                   │
                             ▼                   ▼
           ┌───────────────────────────┐ ┌───────────────────────────┐
           │    SINKRONISASI CLOUD     │ │      STANDALONE MODE      │
           ├───────────────────────────┤ ├───────────────────────────┤
           │ 1. POST telemetry tiap 5s │ │ 1. Tetap berjalan sesuai  │
           │ 2. Kirim buffer offline   │ │    mode & jadwal terakhir │
           │    (sync-batch).          │ │ 2. Snapshot tiap 30 detik │
           │ 3. Terapkan device_command│ │    ke buffer LittleFS     │
           │    dari balasan server.   │ │    (maks. 50 data).       │
           │ 4. Sinkron jam NTP → RTC. │ │ 3. Dashboard lokal AP     │
           └───────────────────────────┘ │    tetap bisa dipakai.    │
                                         └───────────────────────────┘
```

### Sinkronisasi dua arah

```text
Web ubah mode/jadwal/config ──▶ device_controls.command_version + 1
                                        │
ESP32 POST telemetry (command_version = versi yang sudah diterapkan)
                                        │
          versi alat ≠ versi web ──▶ balasan berisi device_command lengkap
          versi alat = versi web ──▶ config/jadwal dari alat disalin ke web
                                     (perubahan dari dashboard lokal ikut tampil)
```

Perintah relay dikirim sekali lewat `device_command.relays` dan kedaluwarsa setelah 30 detik.

---

## 4. Rancangan Database MySQL

### A. `users`
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt (PK) | ID user |
| `name` | String | Nama tampilan |
| `email` | String (unique) | Email login |
| `password` | String | Password hash |

### B. `device_controls` (konfigurasi yang diinginkan web)
| Column | Type | Description |
| :--- | :--- | :--- |
| `device_key` | String (unique) | `NURSERY_01` |
| `system_mode` | TinyInt | `0`=Auto Fuzzy, `1`=Manual, `2`=Schedule |
| `manual_mode_type` | TinyInt | `0`=Toggle, `1`=Timer |
| `manual_duration` | Int | Durasi timer manual (detik) |
| `schedules_json` | JSON | `[{"zone":1,"slots":[{"active","hour","minute","duration"} ×3]}, ...]` |
| `sys_config` | JSON | `sensor_interval`, `log_interval`, `cooldown` |
| `command_version` | UInt | Naik setiap konfigurasi diubah dari web |
| `pending_relays` | JSON | Perintah relay yang belum terkirim (kedaluwarsa 30 detik) |
| `updated_by_user_id` | BigInt (FK) | User terakhir yang mengubah (`null` = System) |
| `updated_at` | Timestamp | Waktu perubahan terakhir |

### C. `device_states` (kondisi yang dilaporkan alat)
| Column | Type | Description |
| :--- | :--- | :--- |
| `device_key` | String (unique) | `NURSERY_01` |
| `reported_version` | UInt (nullable) | `command_version` yang sudah diterapkan alat |
| `last_seen_at` | Timestamp | Telemetry live terakhir (dasar status online) |
| `state` | JSON | Telemetry live terakhir, untuk kartu "kondisi terkini" |

### D. `activity_logs`
| Column | Type | Description |
| :--- | :--- | :--- |
| `user_id` | BigInt (FK) | Relasi ke `users.id` |
| `action` | String | Mis. "Mengubah Mode ke Manual", "Menyalakan Valve Zona 1" |
| `details_json` | JSON | Detail sebelum & sesudah |
| `created_at` | Timestamp | Waktu aksi |

### E. `sensor_logs` (riwayat)
Disimpan tiap `NURSERY_HISTORY_INTERVAL` (60 detik) atau saat relay/status zona/mode berubah. Data batch offline selalu disimpan. Dihapus otomatis setelah 90 hari (`model:prune`).

| Column | Type | Description |
| :--- | :--- | :--- |
| `device_key` | String | `NURSERY_01` |
| `temperature` | Float (nullable) | Suhu udara (°C), `null` jika DHT22 error |
| `humidity` | Float (nullable) | Kelembaban udara (%) |
| `light_level` | Float (nullable) | Cahaya (lux), `null` jika BH1750 error |
| `system_mode` | TinyInt | Mode aktif saat dicatat |
| `relay_main` | Boolean | Status pompa utama |
| `is_offline_data` | Boolean | `true` jika dari buffer offline |
| `recorded_at` | Timestamp | Waktu alat (WIB); waktu terima jika jam alat belum valid |
| `created_at` | Timestamp | Waktu masuk server |

### F. `zone_logs` (3 zona per `sensor_logs`)
| Column | Type | Description |
| :--- | :--- | :--- |
| `sensor_log_id` | BigInt (FK) | Relasi ke `sensor_logs.id` |
| `zone_id` | TinyInt | Nomor zona (1–3) |
| `soil_moisture` | Float (nullable) | Kelembaban tanah (%), `null` jika sensor tidak terpasang |
| `relay_status` | Boolean | Status valve |
| `state` | String | `idle`, `queued`, `watering`, `soaking` |
| `fuzzy_duration` | Float | Rekomendasi fuzzy terakhir (detik) |

---

## 5. Struktur Repository

1. **Firmware** (`C:\HOSTING_SMART_NURSERY_FIRMWARE_TA`, PlatformIO ESP32-S3): task FreeRTOS, fuzzy Mamdani, dashboard lokal AP, `CloudSyncTask` (telemetry HTTPS, buffer LittleFS, NTP).
2. **Web** (repo ini, Laravel): login, dashboard, audit user, REST API untuk ESP32. Lihat [README.md](README.md).

---

## 6. Status Implementasi

| Langkah | Isi | Status |
|---|---|---|
| 1 | Project Laravel, login, migration, dashboard Blade | ✅ |
| 2 | REST API `telemetry`, `sync-batch`, `device-command` dengan `X-Device-Token` | ✅ |
| 3 | Firmware: kirim telemetry, buffer offline, terapkan `device_command` | ✅ |
| 4 | Sinkronisasi dua arah, relay dari web, riwayat hemat, export CSV | ✅ |
| 5 | Deploy ke hosting HTTPS dan uji dengan dua akun (Admin & Budi) | Manual, lihat [README.md § Deploy](README.md#deploy-ke-hosting) |
| 6 | MQTT (opsional, menggantikan polling HTTP) | Ditunda, lihat [PERSIAPAN_MQTT.md](PERSIAPAN_MQTT.md) |
