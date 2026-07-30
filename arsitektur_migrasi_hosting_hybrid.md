# Panduan Migrasi Architecture & Sistem Hybrid Smart Oil Palm Nursery (Revisi v4)

Dokumen ini berisi spesifikasi sistem **Smart Oil Palm Nursery** berbasis **Hybrid Cloud Hosting & Database (Laravel + MySQL)** yang dilengkapi fitur **Login Pengguna, Audit Log (Pencatatan Aksi User Terakhir), & Offline Storage Buffer**.

---

## 1. Fitur Utama: Login & Tracking User (Siapa yang Mengubah Aksi)

Sesuai kebutuhan Anda, sistem menggunakan **Fitur Login & User Audit Trail**:

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│                       SISTEM AKSEK & AUDIT USER                             │
├─────────────────────────────────────────────────────────────────────────────┤
│ 1. LOGIN PENGGUNA:                                                          │
│    Setiap operator/admin harus Login dengan Email & Password masing-masing. │
│                                                                             │
│ 2. PENCATATAN AKSI (AUDIT TRAIL):                                           │
│    Ketika User A (misal: "Budi") mengubah mode ke Manual / menekan tombol   │
│    Relay, Laravel otomatis menyimpan ID & Nama User tersebut.              │
│                                                                             │
│ 3. INFORMASI DI DASHBOARD:                                                  │
│    Dashboard akan menampilkan informasi realtime:                           │
│    "Mode diubah ke Manual oleh: Budi (30 Jul 2026, 14:10 WIB)"              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Acuan Desain UI / UX Dashboard (Diadopsi dari `data/index.html` & `data/style.css`)

Tampilan Web Dashboard Laravel (Repo 2) menggunakan tema modern yang sama dengan file lokal [data/index.html](file:///c:/HOSTING_SMART%20NURSERY_TELU/Smart-Oil-Palm-Nursery-Using-Fuzzy-Logic-and-RTOS-/data/index.html) & [data/style.css](file:///c:/HOSTING_SMART%20NURSERY_TELU/Smart-Oil-Palm-Nursery-Using-Fuzzy-Logic-and-RTOS-/data/style.css), ditambah komponen informasi User:

```text
┌─────────────────────────────────────────────────────────────────────────┐
│ [Icon] Smart Nursery                [Pengguna: Budi (Logout)] [● Live]  │
├─────────────────────────────────────────────────────────────────────────┤
│ Selamat Datang - Smart Nursery (Monitoring & Irigasi Otomatis)          │
├─────────────────────────────────────────────────────────────────────────┤
│ Kondisi Lingkungan:                                                     │
│ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ ┌───────────────┐ │
│ │ Suhu (°C)     │ │ Cahaya (Lux)  │ │ Lembab Udara  │ │ Lembab Tanah  │ │
│ └───────────────┘ └───────────────┘ └───────────────┘ └───────────────┘ │
├─────────────────────────────────────────────────────────────────────────┤
│ Sistem Irigasi:                                                         │
│ Mode Badge: [ MANUAL ]                     Dropdown Mode: [ Manual ]    │
│ ℹ Terakhir Diubah Oleh: Budi pada 14:10 WIB                             │
│ ── [ Dynamic Settings: Timer Manual / Form Schedule (Max 3 Zona) ] ─── │
├─────────────────────────────────────────────────────────────────────────┤
│ Sub-Blok / Zona (5 Total Card Zona dengan Nilai Soil & Toggle Relay)    │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 3. Konsep Utama Sistem Hybrid (Resilience Mode)

```text
                  ┌─────────────────────────────────────────┐
                  │          ESP32 Controller               │
                  │ (Fuzzy, Schedule, Manual & RTC Lokal)   │
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
           │ 1. Kirim data sensor live │ │ 1. ESP32 BERJALAN SESUAI  │
           │    ke Laravel MySQL DB.   │ │    MODE TERAKHIR USER     │
           │ 2. Kirim DATA BUFFER      │ │    (Auto Fuzzy / Manual / │
           │    offline dari Flash.    │ │    Schedule).             │
           │ 3. Terima perintah baru   │ │ 2. Simpan data log baru   │
           │    dari Web Dashboard.    │ │    ke Buffer Flash.       │
           └───────────────────────────┘ └───────────────────────────┘
```

---

## 4. Rancangan Database MySQL (Dengan User Tracking & Audit Logs)

### A. Tabel `users` (Data Akun Login)
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt (PK) | ID User |
| `name` | String | Nama Lengkap User (misal: "Budi", "Andi") |
| `email` | String (Unique)| Email Login |
| `password` | String | Password Hash |

### B. Tabel `device_controls` (Mode & Perintah Terkini)
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt (PK) | ID Control |
| `device_key` | String | Token Unik ESP32 (misal `"NURSERY_01"`) |
| `system_mode` | TinyInt | Mode Terpilih: `0`=Auto Fuzzy, `1`=Manual, `2`=Schedule |
| `manual_mode_type` | TinyInt | `0`=Toggle Direct, `1`=Timer |
| `manual_duration` | Int | Durasi Timer Manual (detik) |
| `schedules_json` | JSON | Konfigurasi Jadwal Penyiraman per Zona |
| `updated_by_user_id`| BigInt (FK) | Relasi ke `users.id` (User yang terakhir mengubah) |
| `updated_at` | Timestamp | Waktu Perubahan Terakhir oleh User |

### C. Tabel `activity_logs` (Histori Semua Aksi User)
Catatan riwayat setiap kali user mengubah mode atau menekan tombol relay.
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt (PK) | ID Activity Log |
| `user_id` | BigInt (FK) | Relasi ke `users.id` |
| `action` | String | Deskripsi aksi (misal: "Mengubah Mode ke Manual", "Menyalakan Valve Zona 1") |
| `details_json` | JSON | Detail data sebelum & sesudah diubah |
| `created_at` | Timestamp | Waktu Aksi Dilakukan |

### D. Tabel `sensor_logs` (Data Sensor dari ESP32)
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt (PK) | ID Log |
| `temperature` | Float | Suhu Udara (°C) |
| `humidity` | Float | Kelembaban Udara (%) |
| `light_level` | Int | Intensitas Cahaya (Lux) |
| `system_mode` | TinyInt | Mode aktif saat dicatat (`0`=Auto, `1`=Manual, `2`=Schedule) |
| `is_offline_data` | Boolean | `true` jika dikirim dari buffer offline ESP32 |
| `recorded_at` | Timestamp | Waktu dicatat (RTC DS3231 ESP32) |
| `created_at` | Timestamp | Waktu masuk server |

### E. Tabel `zone_logs` (Data 5 Zona Pembibitan)
| Column | Type | Description |
| :--- | :--- | :--- |
| `id` | BigInt (PK) | ID Log Zona |
| `sensor_log_id` | BigInt (FK) | Relasi ke `sensor_logs.id` |
| `zone_id` | TinyInt | Nomor Zona (1 - 5) |
| `soil_moisture` | Int | Kelembaban Tanah (%) |
| `zone_mode` | TinyInt | Mode Zona saat itu |
| `duration` | Int | Durasi Penyiraman (Detik) |
| `relay_status` | Boolean | Status Relay Valve (ON/OFF) |

---

## 5. Struktur Repository Git (Dua Repo Terpisah)

1. **Repo 1: `smart-nursery-firmware` (PlatformIO ESP32)**
   * Berisi kode C++ PlatformIO ESP32 dengan fungsi pengiriman HTTP POST, penyimpan buffer LittleFS saat offline, & eksekusi Fuzzy/Schedule/Manual.
2. **Repo 2: `smart-nursery-web` (Laravel Web Application)**
   * Berisi proyek Laravel dengan Halaman Login, Dashboard UI/UX, Tracking User (`activity_logs`), & REST API Receiver.

---

## 6. Tahapan Langkah Eksekusi (Roadmap Implementation)

1. **Langkah 1: Inisialisasi Project Laravel (Repo 2) & Auth System**
   * Buat repo & project Laravel `smart-nursery-web`.
   * Install Laravel Breeze/Fortify untuk fitur **Login, Register, & Profile User**.
   * Buat migration database MySQL (`users`, `device_controls`, `activity_logs`, `sensor_logs`, `zone_logs`).
   * Adaptasi UI/UX dari `data/index.html` & `data/style.css` ke Blade View dengan tambahan informasi *"Terakhir diubah oleh [User]"*.

2. **Langkah 2: Pembuatan REST API Laravel**
   * `POST /api/v1/telemetry` (Menerima data sensor live dari ESP32).
   * `POST /api/v1/telemetry/sync-batch` (Menerima log offline buffer dari ESP32).
   * `GET /api/v1/device-command` (Memberikan status mode ke ESP32).

3. **Langkah 3: Pembaruan Firmware PlatformIO ESP32 (Repo 1)**
   * Tambahkan pengiriman data HTTP POST ke Laravel Cloud.
   * Buat modul buffer penyimpanan offline LittleFS saat Wi-Fi putus.

4. **Langkah 4: Deploy ke Cloud Hosting & Testing**
   * Deploy Laravel ke Cloud Hosting dengan SSL HTTPS.
   * Uji coba login dengan 2 akun user berbeda (misal Admin & Budi).
   * Uji coba Budi mengubah mode, lalu cek di dashboard apakah tercatat *"Diubah oleh Budi"*.
