# Smart Nursery Web

Dashboard monitoring dan kontrol irigasi **Smart Oil Palm Nursery**. Web ini menerima data dari ESP32-S3 (firmware di repo terpisah) dan mengirim balik mode, jadwal, konfigurasi, serta perintah relay.

- **Hosting:** https://smartnursery-tel-u.srs-ssms.com/
- **Stack:** Laravel 13, PHP 8.3+, Blade, MySQL (SQLite untuk test)
- **Firmware:** ESP32-S3, 3 zona, 1 pompa utama + 3 valve, fuzzy Mamdani
- **Kontrak data & riwayat revisi:** [RINGKASAN_DAN_REVISI.md](RINGKASAN_DAN_REVISI.md)

## Fitur

| Tab | Isi |
|---|---|
| Dashboard | Suhu, kelembaban udara, cahaya, kelembaban tanah per zona, status pompa/valve, status zona (idle/antre/menyiram/jeda serap), durasi fuzzy, peringatan sensor error, badge **Alat Online/Offline** |
| Kontrol | Mode Auto / Manual / Schedule, setelan manual (toggle/timer), jadwal 3 slot per zona, tombol relay (hanya mode Manual), status sinkron dengan alat |
| Log | Audit aksi user ("diubah oleh ...") |
| Config | Interval sensor, interval log, cooldown, export CSV per hari / rentang tanggal, hapus log |

Perubahan dari web dikirim ke alat lewat balasan telemetry berikutnya (±5 detik). Perubahan dari dashboard lokal alat (WiFi AP) ikut tampil di web. Lihat [bagian B4](RINGKASAN_DAN_REVISI.md#b-perintah-dari-web-ke-alat--penting).

## Instalasi lokal

```bash
composer install
cp .env.example .env
php artisan key:generate
# isi DB_* dan DEVICE_TOKEN di .env
php artisan migrate --seed
php artisan serve
```

Seeder membuat akun admin dari `ADMIN_EMAIL` / `ADMIN_PASSWORD`. Jika `ADMIN_PASSWORD` kosong, password acak dibuat dan **ditampilkan sekali** di terminal.

Untuk uji dengan ESP32 sungguhan di jaringan lokal, ikuti [bagian 9](RINGKASAN_DAN_REVISI.md#9-uji-lokal-esp32--laravel-di-laptop).

## Variabel `.env`

| Variabel | Default | Keterangan |
|---|---|---|
| `DEVICE_TOKEN` | — | **Wajib.** Sama dengan `DEVICE_KEY` di `include/config.h` firmware. Jika kosong, semua request alat ditolak (401). Buat dengan `php -r "echo bin2hex(random_bytes(24));"` |
| `NURSERY_ZONES` | `3` | Jumlah zona (sama dengan `NUM_ZONES` firmware) |
| `NURSERY_HISTORY_INTERVAL` | `60` | Riwayat live disimpan tiap N detik, atau langsung saat relay/status zona/mode berubah |
| `NURSERY_HISTORY_RETENTION_DAYS` | `90` | Riwayat lebih lama dihapus oleh `model:prune` (harian) |
| `ALLOW_REGISTRATION` | `false` | Halaman register. Biarkan `false` di server publik |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD` | `admin@nursery.com`, acak | Akun admin pertama dari seeder |

## Akun

Registrasi publik mati. Akun dibuat, atau password diganti, dari server:

```bash
php artisan nursery:user budi@nursery.com --name="Budi"   # akun baru
php artisan nursery:user admin@nursery.com                 # ganti password
```

Password diminta lewat prompt (minimal 8 karakter), tidak ditulis di command.

## API perangkat

Semua request wajib header `X-Device-Token`, dibatasi 60 request/menit.

| Endpoint | Fungsi |
|---|---|
| `POST /api/v1/telemetry` | Data live tiap 5 detik. Balasan berisi `device_command` jika ada perubahan dari web |
| `POST /api/v1/telemetry/sync-batch` | Data dari buffer offline, format `{"data":[...]}`. Item tidak valid dilewati |
| `GET /api/v1/device-command` | Baca konfigurasi terkini (tidak dipakai firmware) |

Format JSON lengkap: [bagian 6](RINGKASAN_DAN_REVISI.md#6-kontrak-data-firmware--web).

## Deploy ke hosting

1. Backup database.
2. Upload/pull kode, lalu `composer install --no-dev --optimize-autoloader`.
3. Pastikan `.env` server berisi:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://smartnursery-tel-u.srs-ssms.com
   DEVICE_TOKEN=<token acak, sama dengan firmware>
   ALLOW_REGISTRATION=false
   ```
4. `php artisan migrate --force`
5. `php artisan config:cache` (ulangi setiap `.env` diubah)
6. Ganti password akun bawaan: `php artisan nursery:user admin@nursery.com`
7. Pasang cron untuk pembersihan riwayat harian:
   ```
   * * * * * php /path/ke/project/artisan schedule:run >> /dev/null 2>&1
   ```

## Test

```bash
php artisan test
```

Payload test mengikuti `buildTelemetry()` firmware apa adanya ([TelemetryApiTest.php](tests/Feature/TelemetryApiTest.php)).

| File | Cakupan |
|---|---|
| `TelemetryApiTest` | Token, payload firmware, nilai `null`, batch, format `device_command` |
| `DeviceSyncTest` | Versi perintah, relay sekali kirim, config sistem, sinkron dari dashboard lokal |
| `DashboardScheduleTest` | Format dan validasi jadwal |
| `HistoryAndExportTest` | Interval riwayat, data offline, pembersihan, export CSV |
| `AccountSecurityTest` | Registrasi tertutup, akun admin, `nursery:user` |
