@extends('layouts.app')

@section('title', 'Dashboard - Smart Nursery')

@section('content')

@php
    $modeNames = [0 => 'AUTO (FUZZY)', 1 => 'MANUAL', 2 => 'SCHEDULE'];
    $modeClasses = [0 => 'active', 1 => 'manual', 2 => 'schedule'];
    $systemMode = $deviceControl->system_mode ?? 0;
    $sysConfig = $deviceControl->sysConfig();
@endphp

<!-- ===== TAB PANEL: DASHBOARD ===== -->
<div class="tab-panel tab-panel--active" id="tab-dashboard">

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-text">
            <div class="hero-greeting">Selamat Datang, {{ Auth::user()->name }}</div>
            <div class="hero-title">Smart Nursery</div>
            <div class="hero-sub">Monitoring &amp; Irigasi Otomatis Pembibitan Kelapa Sawit</div>
        </div>
        {{-- Jam seperti dashboard lokal firmware, zona waktu aplikasi (sama dengan waktu log & jadwal) --}}
        <div class="hero-meta">
            <div class="hero-clock" id="hero-clock">{{ now()->format('H:i') }}</div>
            <div class="hero-date" id="hero-date"></div>
        </div>
    </div>

    <!-- Audit User Notice Banner -->
    <div class="user-audit-notice">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <polyline points="12 6 12 12 16 14"/>
        </svg>
        <span>
            <strong>Terakhir Diubah Oleh:</strong>
            <span id="audit-user-name" style="color:var(--clr-primary); font-weight:800;">
                {{ $deviceControl->updatedBy ? $deviceControl->updatedBy->name : 'System' }}
            </span>
            pada <span id="audit-user-time" style="font-weight:700;">
                {{ $deviceControl->updated_at ? $deviceControl->updated_at->format('d M Y, H:i T') : '-' }}
            </span>
        </span>
    </div>

    <!-- Sensor Cards -->
    <div class="section-label">Kondisi Lingkungan</div>
    <div class="sensor-grid">

        <div class="sensor-card temp-card">
            <div class="sensor-icon-wrap primary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z" />
                </svg>
            </div>
            <div>
                <div class="sensor-label">Suhu Udara</div>
                <div class="sensor-value"><span id="temp">{{ isset($latest['temperature']) ? number_format($latest['temperature'], 1) : '--' }}</span><span class="sensor-unit">°C</span></div>
            </div>
        </div>

        <div class="sensor-card light-card">
            <div class="sensor-icon-wrap secondary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
            </div>
            <div>
                <div class="sensor-label">Cahaya</div>
                <div class="sensor-value"><span id="light">{{ isset($latest['light_level']) ? number_format($latest['light_level']) : '--' }}</span><span class="sensor-unit">lx</span></div>
            </div>
        </div>

        <div class="sensor-card hum-card">
            <div class="sensor-icon-wrap tertiary">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                </svg>
            </div>
            <div>
                <div class="sensor-label">Lembab Udara</div>
                <div class="sensor-value"><span id="hum">{{ isset($latest['humidity']) ? number_format($latest['humidity'], 1) : '--' }}</span><span class="sensor-unit">%</span></div>
            </div>
        </div>

        <div class="sensor-card soil-card">
            <div class="sensor-icon-wrap soil">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                </svg>
            </div>
            <div>
                <div class="sensor-label">Rata Lembab Tanah</div>
                <div class="sensor-value">
                    @php $avgSoil = collect($latest['zones'] ?? [])->avg('soil_moisture'); @endphp
                    <span id="soil">{{ $avgSoil !== null ? number_format($avgSoil, 0) : '--' }}</span>
                    <span class="sensor-unit">%</span>
                </div>
            </div>
        </div>

    </div><!-- /sensor-grid -->

    <div class="alert-warning" id="sensor-alert" style="display:none; margin-bottom:1.25rem;"></div>

    <!-- Valve Zones -->
    <div class="section-header">
        <h3 class="section-title">Sub-Blok / Zona Pembibitan</h3>
        <span class="count-badge">{{ config('nursery.zones') }} Total Zona</span>
    </div>

    <div class="valve-grid" id="valve-container">
        <!-- Rendered by JS -->
    </div>

    <div class="last-update">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 4 23 10 17 10" />
            <polyline points="1 20 1 14 7 14" />
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
        </svg>
        Data alat terakhir:&nbsp;<span id="timestamp" style="font-weight:700;color:var(--clr-primary);">--:--:--</span>
        <span id="device-last-seen" class="form-hint" style="margin:0 0 0 0.5rem;"></span>
    </div>

</div><!-- /tab-panel dashboard -->


<!-- ===== TAB PANEL: KONTROL ===== -->
<div class="tab-panel" id="tab-control">

    <div class="section-label">Sistem Irigasi &amp; Kontrol Mode</div>

    <div class="irrigation-bar">
        <div class="irrigation-info">
            <h4>Status Mode Terpilih</h4>
            <div class="irrigation-status-row">
                <span class="mode-badge {{ $modeClasses[$systemMode] ?? 'active' }}" id="irrig-mode-badge">{{ $modeNames[$systemMode] ?? 'AUTO (FUZZY)' }}</span>
                <span class="duration-text" id="irrig-detail"></span>
            </div>
            <div class="sync-notice" id="sync-notice"></div>
        </div>

        <div class="override-control">
            <div>
                <div class="override-label-title">Mode Sistem</div>
                <div class="override-label-sub">Pilih mode operasional</div>
            </div>
            <select id="systemModeSelect" onchange="changeSystemMode()">
                <option value="0" {{ $systemMode == 0 ? 'selected' : '' }}>Auto (Fuzzy)</option>
                <option value="1" {{ $systemMode == 1 ? 'selected' : '' }}>Manual</option>
                <option value="2" {{ $systemMode == 2 ? 'selected' : '' }}>Schedule</option>
            </select>
        </div>

        <!-- MANUAL SETTINGS -->
        <div id="manualSettings" class="mode-settings" style="display: {{ $systemMode == 1 ? 'flex' : 'none' }};">
            <div class="mode-settings-title">Pengaturan Mode Manual</div>
            <div class="mode-settings-field">
                <label class="form-label" for="cfg-manual-mode">Tipe Mode Manual</label>
                <select id="cfg-manual-mode" onchange="changeSystemMode()" class="form-input">
                    <option value="0" {{ ($deviceControl->manual_mode_type ?? 0) == 0 ? 'selected' : '' }}>Toggle (Langsung On/Off)</option>
                    <option value="1" {{ ($deviceControl->manual_mode_type ?? 0) == 1 ? 'selected' : '' }}>Timer (Mati Otomatis)</option>
                </select>
            </div>
            <div class="mode-settings-field">
                <label class="form-label" for="cfg-manual-dur">Durasi Timer (detik)</label>
                <input type="number" id="cfg-manual-dur" min="1" max="3600" value="{{ $deviceControl->manual_duration ?? 10 }}" onchange="changeSystemMode()" class="form-input">
            </div>
        </div>

        <!-- AUTO: STATUS ZONA -->
        <div id="autoSettings" class="mode-settings mode-settings-column zone-panel" style="display: {{ $systemMode == 0 ? 'flex' : 'none' }};">
            <div class="zone-panel-head">
                <div>
                    <div class="mode-settings-title">Status Zona</div>
                    <div class="form-hint" style="margin:0.15rem 0 0;">Durasi siram dihitung otomatis dari kelembaban tanah, suhu, dan cahaya.</div>
                </div>
                <span class="pump-chip" id="auto-pump">Pompa Utama --</span>
            </div>
            <div class="alert-warning" id="auto-alert" style="display:none;"></div>
            <div id="auto-zone-container" class="schedule-grid"></div>
            <div class="form-hint" id="auto-updated" style="margin:0;"></div>
        </div>

        <!-- SCHEDULE SETTINGS -->
        <div id="scheduleSettings" class="mode-settings mode-settings-column zone-panel" style="display: {{ $systemMode == 2 ? 'flex' : 'none' }};">
            <div>
                <div class="mode-settings-title">Jadwal Penyiraman</div>
                <div class="form-hint" style="margin:0.15rem 0 0;">Maksimal {{ config('nursery.schedule_slots') }} slot per zona. Jadwal dilewati jika kelembaban tanah zona di atas {{ config('nursery.schedule_soil_max') }}%.</div>
            </div>
            <div id="schedule-container" class="schedule-grid"></div>
            <button class="btn-cta btn-cta--inline btn-cta--pill" onclick="saveSchedule(this)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.1rem;height:1.1rem;">
                    <path d="M8 7H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3" />
                    <polyline points="9 11 12 14 15 11" />
                    <line x1="12" y1="4" x2="12" y2="14" />
                </svg>
                Simpan Jadwal
            </button>
        </div>
    </div><!-- /irrigation-bar -->

    <!-- Relay Control (hanya mode Manual; firmware menolak perintah relay di mode lain) -->
    <div id="relaySection" style="display: {{ $systemMode == 1 ? 'block' : 'none' }};">
    <div class="section-header" style="margin-bottom:0.25rem;">
        <h3 class="section-title">Kontrol Relay Manual</h3>
        <span class="count-badge">Pompa + {{ config('nursery.zones') }} Zona</span>
    </div>
    <p class="form-hint" style="margin:0 0 0.75rem;">Perintah sampai ke alat dalam ±5 detik. Pompa utama ikut menyala selama ada valve terbuka.</p>
    <div class="alert-warning" id="relay-hint" style="display:none; margin-bottom:0.75rem;">
        Relay hanya bisa dikontrol pada mode <strong>Manual</strong>. Ubah mode sistem terlebih dahulu.
    </div>
    <div class="valve-grid valve-grid--relay" id="valve-container-ctrl">
        <!-- Rendered by JS -->
    </div>
    </div><!-- /relaySection -->

</div><!-- /tab-panel kontrol -->


<!-- ===== TAB PANEL: STATISTIK ===== -->
<div class="tab-panel" id="tab-stats">

    {{-- Satu filter rentang untuk semua isi di bawahnya; data dimuat saat tab dibuka (StatisticsController) --}}
    <div class="stats-toolbar">
        <div class="seg-control" role="group" aria-label="Rentang waktu statistik">
            <button type="button" class="seg-btn active" data-range="24h" aria-pressed="true" onclick="setStatsRange('24h')">24 Jam</button>
            <button type="button" class="seg-btn" data-range="7d" aria-pressed="false" onclick="setStatsRange('7d')">7 Hari</button>
            <button type="button" class="seg-btn" data-range="30d" aria-pressed="false" onclick="setStatsRange('30d')">30 Hari</button>
        </div>
        <span class="stats-meta" id="stats-meta">Memuat...</span>
    </div>

    <div class="stats-body" id="stats-body">

        <div class="section-label">Ringkasan Sensor</div>
        <div class="sensor-grid">
            <div class="sensor-card temp-card">
                <div class="sensor-icon-wrap primary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z" />
                    </svg>
                </div>
                <div>
                    <div class="sensor-label">Rata Suhu Udara</div>
                    <div class="sensor-value"><span id="stat-temp">--</span><span class="sensor-unit">°C</span></div>
                </div>
            </div>

            <div class="sensor-card light-card">
                <div class="sensor-icon-wrap secondary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="4" />
                        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                    </svg>
                </div>
                <div>
                    <div class="sensor-label">Rata Cahaya</div>
                    <div class="sensor-value"><span id="stat-light">--</span><span class="sensor-unit">lx</span></div>
                </div>
            </div>

            <div class="sensor-card hum-card">
                <div class="sensor-icon-wrap tertiary">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                    </svg>
                </div>
                <div>
                    <div class="sensor-label">Rata Lembab Udara</div>
                    <div class="sensor-value"><span id="stat-hum">--</span><span class="sensor-unit">%</span></div>
                </div>
            </div>

            <div class="sensor-card soil-card">
                <div class="sensor-icon-wrap soil">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                    </svg>
                </div>
                <div>
                    <div class="sensor-label">Rata Lembab Tanah</div>
                    <div class="sensor-value"><span id="stat-soil">--</span><span class="sensor-unit">%</span></div>
                </div>
            </div>
        </div><!-- /sensor-grid -->

        <div class="section-label">Tren Iklim</div>
        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-head"><div class="chart-title">Suhu Udara <span class="chart-unit">°C</span></div></div>
                <div class="chart-box" id="chart-temp"></div>
            </div>
            <div class="chart-card">
                <div class="chart-head"><div class="chart-title">Kelembaban Udara <span class="chart-unit">%</span></div></div>
                <div class="chart-box" id="chart-hum"></div>
            </div>
            <div class="chart-card">
                <div class="chart-head"><div class="chart-title">Cahaya <span class="chart-unit">lx</span></div></div>
                <div class="chart-box" id="chart-light"></div>
            </div>
        </div>

        <div class="section-label">Kelembaban Tanah per Zona</div>
        <div class="chart-card chart-card--solo">
            <div class="chart-head">
                <div class="chart-title">Kelembaban Tanah <span class="chart-unit">%</span></div>
                <div class="chart-legend" id="soil-legend"></div>
            </div>
            <div class="chart-box chart-box--tall" id="chart-soil"></div>
        </div>

        <details class="stats-details">
            <summary>Lihat tabel data per waktu</summary>
            <div class="table-scroll">
                <table class="audit-table">
                    <thead id="stats-data-head"></thead>
                    <tbody id="stats-data-body"></tbody>
                </table>
            </div>
        </details>

        <div class="section-label">Rekap Penyiraman</div>
        <div class="audit-table-card" style="margin-top:0; margin-bottom:1.5rem;">
            <div class="table-scroll">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Zona</th>
                            <th class="num">Penyiraman</th>
                            <th class="num">Total Durasi</th>
                            <th class="num">Rata Durasi</th>
                            <th class="num">Rata Tanah</th>
                        </tr>
                    </thead>
                    <tbody id="irrigation-body">
                        <tr><td colspan="5" class="table-empty">Memuat...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /stats-body -->

    <div class="section-label">Riwayat Aktivitas</div>

    <div class="audit-table-card" style="margin-top:0;">
        <div class="audit-card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Riwayat Aktivitas Pengguna (Audit Log)
        </div>
        <div class="table-scroll">
            <table class="audit-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pengguna</th>
                        <th>Aksi / Perubahan</th>
                    </tr>
                </thead>
                <tbody id="audit-log-body">
                    @forelse($recentLogs as $log)
                        <tr>
                            {{-- Tanggal & jam boleh turun baris di ponsel agar kolom aksi tetap lega --}}
                            <td class="log-time"><span>{{ $log->created_at->format('d/m/Y') }}</span> <span>{{ $log->created_at->format('H:i:s') }}</span></td>
                            <td><strong>{{ $log->user ? $log->user->name : 'System' }}</strong></td>
                            <td>{{ $log->action }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" style="text-align:center; color:var(--clr-on-surface-var);">Belum ada aktivitas tercatat.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /tab-panel statistik -->


<!-- ===== TAB PANEL: CONFIG ===== -->
<div class="tab-panel" id="tab-config">

    {{-- Isi & urutan mengikuti tab Config dashboard lokal firmware (web/index.html) --}}
    @php
        // Label durasi seperti formatDuration() di firmware app.js
        $durationLabel = fn (int $s) => $s >= 3600
            ? intdiv($s, 3600) . 'j ' . intdiv($s % 3600, 60) . 'm'
            : ($s >= 60 ? intdiv($s, 60) . 'm ' . ($s % 60) . 'd' : $s . 'd');
        $logIntervalOptions = [300 => '5 Menit', 900 => '15 Menit', 1800 => '30 Menit', 3600 => '1 Jam', 7200 => '2 Jam', 10800 => '3 Jam', 21600 => '6 Jam', 43200 => '12 Jam', 86400 => '1 Hari (24 Jam)'];
        $cooldownOptions = [3600 => '1 Jam', 7200 => '2 Jam', 14400 => '4 Jam', 21600 => '6 Jam', 43200 => '12 Jam', 86400 => '24 Jam'];
        // Nilai di luar daftar (mis. diubah dari alat) tetap ditampilkan, seperti setSelectValue() firmware,
        // supaya tidak diam-diam berganti ke opsi pertama saat disimpan
        $logIntervalOptions += [$sysConfig['log_interval'] => $durationLabel($sysConfig['log_interval'])];
        $cooldownOptions += [$sysConfig['cooldown'] => $durationLabel($sysConfig['cooldown'])];
    @endphp

    <div class="section-label">Sistem &amp; Log</div>

    <div class="config-grid config-grid--3">
    <!-- Interval Sensor -->
    <div class="config-item">
        <div class="config-item-header">
            <div class="config-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:0;">Interval Sensor</div>
                <div class="form-hint" style="margin:0;">Waktu antar baca sensor (detik, 2 - 3600)</div>
            </div>
        </div>
        <input type="number" id="cfg-interval" class="form-input" min="2" max="3600" placeholder="10" value="{{ $sysConfig['sensor_interval'] }}">
    </div>

    <!-- Interval Log -->
    <div class="config-item">
        <div class="config-item-header">
            <div class="config-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:0;">Interval Log Data</div>
                <div class="form-hint" style="margin:0;">Frekuensi pencatatan otomatis (log di alat)</div>
            </div>
        </div>
        <select id="cfg-log-interval" class="form-input">
            @foreach ($logIntervalOptions as $seconds => $label)
                <option value="{{ $seconds }}" {{ $sysConfig['log_interval'] == $seconds ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <!-- Cooldown Auto -->
    <div class="config-item">
        <div class="config-item-header">
            <div class="config-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:0;">Cooldown (Auto)</div>
                <div class="form-hint" style="margin:0;">Jeda minimum antar penyiraman otomatis per zona</div>
            </div>
        </div>
        <select id="cfg-cooldown" class="form-input">
            @foreach ($cooldownOptions as $seconds => $label)
                <option value="{{ $seconds }}" {{ $sysConfig['cooldown'] == $seconds ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    </div><!-- /config-grid -->

    <button class="btn-cta btn-cta--inline" style="margin-top:0; margin-bottom:1.5rem;" onclick="saveSysConfig(this)">Simpan Konfigurasi</button>

    <div class="config-grid config-grid--3" style="margin-bottom:2rem;">
    <!-- Sinkronisasi Waktu RTC (read-only: jam alat disinkronkan NTP oleh firmware saat online) -->
    <div class="config-item">
        <div class="config-item-header">
            <div class="config-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:0;">Sinkronisasi Waktu RTC</div>
                <div class="form-hint" style="margin:0;">Otomatis dari internet saat alat online</div>
            </div>
        </div>
        <div class="rtc-status">
            <div>Jam perangkat: <strong id="rtc-time">--</strong></div>
            <div>Status jam: <span class="status-chip" id="rtc-chip">--</span></div>
            <div>Diterima server: <strong id="rtc-received">--</strong></div>
        </div>
    </div>

    <!-- Export CSV -->
    <div class="config-item">
        <div class="config-item-header">
            <div class="config-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:0;">Export Log Data</div>
                <div class="form-hint" style="margin:0;">Unduh riwayat sensor sebagai file Excel (.xlsx) atau .csv</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="csv-date">Tanggal</label>
                <input type="date" id="csv-date" class="form-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
            </div>
        </div>
        <label class="form-hint" style="display:flex; align-items:center; gap:0.4rem; margin:0.25rem 0;">
            <input type="checkbox" id="csv-use-range" onchange="toggleCsvRange()"> Beberapa hari (rentang tanggal)
        </label>
        <div class="form-row" id="csv-range" style="display:none;">
            <div class="form-group">
                <label class="form-label" for="csv-from">Dari</label>
                <input type="date" id="csv-from" class="form-input" value="{{ now()->subDays(6)->toDateString() }}" max="{{ now()->toDateString() }}">
            </div>
            <div class="form-group">
                <label class="form-label" for="csv-to">Sampai</label>
                <input type="date" id="csv-to" class="form-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
            </div>
        </div>
        <div class="form-row">
            <button class="btn btn-primary" style="flex:1;" onclick="exportLog('xlsx')">Export Excel</button>
            <button class="btn btn-secondary" style="flex:1;" onclick="exportLog('csv')">Export CSV</button>
        </div>
    </div>

    <!-- Hapus Log Data -->
    <div class="config-item">
        <div class="config-item-header">
            <div class="config-icon danger">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </div>
            <div>
                <div class="form-label text-danger" style="margin-bottom:0;">Hapus Data Log</div>
                <div class="form-hint" style="margin:0;">Menghapus semua riwayat log sensor di server (permanen)</div>
            </div>
        </div>
        <button class="btn btn-danger btn-block" onclick="resetLogData(this)">Hapus Log</button>
    </div>
    </div><!-- /config-grid -->

    <div class="section-label">Firmware</div>

    <!-- Update Firmware (OTA): alat mengambil rilis aktif lewat balasan telemetry, dipasang saat semua relay mati -->
    <div class="config-item" style="margin-bottom:2rem;">
        <div class="config-item-header">
            <div class="config-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:0;">Update Firmware (OTA)</div>
                <div class="form-hint" style="margin:0;">Alat memasang rilis aktif saat online dan semua relay mati</div>
            </div>
        </div>
        <div class="rtc-status fw-status">
            <div>Versi di alat: <strong id="fw-device">--</strong></div>
            <div>Rilis aktif: <strong id="fw-release">--</strong></div>
            <div>Status: <span class="status-chip" id="fw-chip">--</span></div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="fw-version">Versi</label>
                {{-- Hanya angka dan titik (X.Y.Z), sama dengan validasi server di FirmwareController::upload --}}
                <input type="text" id="fw-version" class="form-input" placeholder="1.0.2" maxlength="11"
                    inputmode="decimal" pattern="\d{1,3}\.\d{1,3}\.\d{1,3}" autocomplete="off" spellcheck="false"
                    oninput="this.value = this.value.replace(/[^0-9.]/g, '')">
            </div>
            <div class="form-group">
                <label class="form-label" for="fw-file">File firmware (.bin)</label>
                <input type="file" id="fw-file" class="form-input" accept=".bin" onchange="checkFirmwareFile(this)">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="fw-notes" style="display:flex; justify-content:space-between;">
                <span>Catatan (opsional)</span>
                <span class="form-hint" id="fw-notes-count" style="margin:0; font-weight:400;">0/500</span>
            </label>
            <input type="text" id="fw-notes" class="form-input" maxlength="500" placeholder="Perubahan di versi ini"
                oninput="document.getElementById('fw-notes-count').innerText = this.value.length + '/500'">
        </div>
        <div class="form-hint" style="margin:0;">Versi angka <strong>X.Y.Z</strong> (mis. 1.0.2). File .bin maksimal 6,25 MB.</div>
        <div class="form-row">
            <button class="btn btn-primary" style="flex:1;" onclick="uploadFirmware(this)">Rilis</button>
            <button class="btn btn-danger" style="flex:1; display:none;" id="fw-cancel" onclick="cancelFirmware(this)">Batalkan</button>
        </div>
    </div>

</div><!-- /tab-panel config -->

@endsection

@section('scripts')
<script>
    const NUM_ZONES = @json(config('nursery.zones'));
    const SCHEDULE_SLOTS = @json(config('nursery.schedule_slots'));
    const POLL_INTERVAL_MS = 3000;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const MODE_BADGES = { 0: ['AUTO (FUZZY)', 'active'], 1: ['MANUAL', 'manual'], 2: ['SCHEDULE', 'schedule'] };
    const STATE_LABELS = { idle: 'Idle', queued: 'Antre', watering: 'Menyiram', soaking: 'Jeda Serap' };
    let currentSchedules = @json($deviceControl->schedules_json ?? []);
    let desiredMode = @json($systemMode);

    // ============ HELPERS ============
    // Firmware mengirim null saat sensor error / tidak terpasang
    function fmt(value, digits = 1) {
        return (value === null || value === undefined || Number.isNaN(Number(value))) ? '--' : Number(value).toFixed(digits);
    }

    function esc(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    async function api(url, options = {}) {
        const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken };
        // FormData (upload file): browser sendiri yang mengisi Content-Type multipart beserta boundary
        if (options.body && !(options.body instanceof FormData)) headers['Content-Type'] = 'application/json';
        const res = await fetch(url, { ...options, headers });
        // Sesi habis / akses dicabut admin: kembali ke halaman login
        if (res.status === 401) {
            window.location.href = "{{ route('login') }}";
            throw new Error('Sesi berakhir, silakan login kembali.');
        }
        const data = await res.json().catch(() => ({}));
        if (!res.ok || data.status === 'error') throw new Error(data.message || ('HTTP ' + res.status));
        return data;
    }

    function postJson(url, body) {
        return api(url, { method: 'POST', body: JSON.stringify(body) });
    }

    // ============ RENDER ZONES ============
    // Kartu monitoring (tab Dashboard)
    function renderZones(zones, systemMode) {
        let html = '';
        for (let i = 1; i <= NUM_ZONES; i++) {
            const z = zones.find(item => item.zone_id === i) || { soil_moisture: null, relay_status: false, state: null };
            const on = !!z.relay_status;
            const stateLabel = STATE_LABELS[z.state] || (on ? 'Menyiram' : 'Idle');
            const fuzzy = (systemMode === 0 && z.fuzzy_duration > 0)
                ? `<div class="valve-metric">Fuzzy: <span>${fmt(z.fuzzy_duration, 0)} dtk</span></div>` : '';
            html += `<div class="valve-card ${on ? 'active-zone' : ''}">
                <div class="valve-card-info">
                    <div class="valve-name-row">
                        <div class="valve-name">Zona ${i}</div>
                        <span class="valve-mode-badge ${on ? 'on' : ''}">${stateLabel}</span>
                    </div>
                    <div class="valve-metrics">
                        <div class="valve-metric">Tanah: <span>${z.soil_moisture === null ? 'sensor --' : fmt(z.soil_moisture) + '%'}</span></div>
                        <div class="valve-metric">Valve: <span>${on ? 'ON' : 'OFF'}</span></div>
                        ${fuzzy}
                    </div>
                </div>
            </div>`;
        }
        document.getElementById('valve-container').innerHTML = html;
    }

    // Kartu kontrol relay (tab Kontrol). Toggle aktif hanya di mode Manual.
    function renderRelayControls(sensor, pending, systemMode) {
        const enabled = systemMode === 1;
        const relays = [{ id: 'main', name: 'Pompa Utama', desc: 'Relay pompa air', on: !!sensor?.relay_main }];
        for (let i = 1; i <= NUM_ZONES; i++) {
            const z = (sensor?.zones || []).find(item => item.zone_id === i);
            relays.push({ id: i, name: `Valve Zona ${i}`, desc: `Soil: ${fmt(z?.soil_moisture)}%`, on: !!z?.relay_status });
        }

        document.getElementById('relay-hint').style.display = enabled ? 'none' : 'block';
        document.getElementById('valve-container-ctrl').innerHTML = relays.map(r => {
            const isPending = Object.prototype.hasOwnProperty.call(pending, String(r.id));
            const checked = isPending ? pending[String(r.id)] : r.on;
            // Status ON/OFF sudah tampil di badge; label di toggle hanya untuk perintah yang belum diterima alat
            const label = isPending ? '<span class="valve-toggle-label pending">Menunggu</span>' : '';
            return `<div class="valve-card ${r.on ? 'active-zone' : ''}">
                <div class="valve-card-info">
                    <div class="valve-name-row">
                        <div class="valve-name">${r.name}</div>
                        <span class="valve-mode-badge ${r.on ? 'on' : ''}">${r.on ? 'ON' : 'OFF'}</span>
                    </div>
                    <div class="valve-desc">${r.desc}</div>
                </div>
                <div class="valve-card-toggle">
                    ${label}
                    <label class="toggle-wrap" title="${r.name}"><input type="checkbox" class="toggle-input" ${checked ? 'checked' : ''} ${enabled ? '' : 'disabled'} onchange="toggleRelay('${r.id}', this.checked)"><div class="toggle-track"></div></label>
                </div>
            </div>`;
        }).join('');
    }

    function renderSensorAlert(sensor) {
        const problems = [];
        if (sensor) {
            if (sensor.temperature === null || sensor.humidity === null) problems.push('DHT22 (suhu/kelembaban udara) tidak terbaca');
            if (sensor.light_level === null) problems.push('BH1750 (cahaya) tidak terbaca');
            (sensor.zones || []).filter(z => z.soil_moisture === null).forEach(z => problems.push(`Sensor tanah zona ${z.zone_id} tidak terpasang`));
        }
        const el = document.getElementById('sensor-alert');
        el.style.display = problems.length ? 'block' : 'none';
        el.innerHTML = problems.length ? '⚠ ' + problems.join(' · ') + '. Zona tanpa sensor tidak disiram otomatis.' : '';
    }

    // Badge Online/Offline di header + status sinkronisasi di tab Kontrol
    function renderDeviceStatus(device) {
        const badge = document.getElementById('conn-badge');
        if (badge) {
            badge.classList.toggle('offline', !device.online);
            document.getElementById('conn-text').innerText = device.online ? 'Alat Online' : 'Alat Offline';
            badge.title = device.last_seen ? 'Data terakhir ' + device.last_seen : 'Belum ada data dari alat';
        }
        document.getElementById('device-last-seen').innerText = device.last_seen ? `(${device.last_seen})` : '';

        const notice = document.getElementById('sync-notice');
        if (!device.online) {
            notice.className = 'sync-notice warn';
            notice.innerText = 'Alat offline. Perubahan akan diterapkan saat alat tersambung kembali.';
        } else if (device.synced === false) {
            notice.className = 'sync-notice warn';
            notice.innerText = 'Menunggu alat menerapkan perubahan...';
        } else if (device.synced === true) {
            notice.className = 'sync-notice ok';
            notice.innerText = '✓ Tersinkron dengan alat';
        } else {
            notice.className = 'sync-notice';
            notice.innerText = '';
        }
    }

    // Keterangan di samping badge mode, sesuai mode yang berjalan
    function renderModeDetail(control) {
        let html;
        if (control.system_mode === 1) {
            html = control.manual_mode_type === 1
                ? `Timer: <strong>${esc(control.manual_duration)} dtk</strong>`
                : 'Toggle (langsung on/off)';
        } else if (control.system_mode === 2) {
            const active = (control.schedules || []).reduce((sum, z) => sum + (z.slots || []).filter(s => s.active).length, 0);
            html = active ? `<strong>${active}</strong> jadwal aktif` : 'Belum ada jadwal aktif';
        } else {
            html = 'Durasi siram dihitung fuzzy';
        }
        document.getElementById('irrig-detail').innerHTML = html;
    }

    // ============ SYSTEM MODE ============
    // Bagian tab Kontrol yang tampil sesuai mode terpilih
    function showModeSections(mode) {
        document.getElementById('autoSettings').style.display = (mode === 0) ? 'flex' : 'none';
        document.getElementById('manualSettings').style.display = (mode === 1) ? 'flex' : 'none';
        document.getElementById('scheduleSettings').style.display = (mode === 2) ? 'flex' : 'none';
        document.getElementById('relaySection').style.display = (mode === 1) ? 'block' : 'none';
    }

    // ============ AUTO: STATUS ZONA ============
    // Siklus zona di firmware (irrigation_task.cpp): idle -> queued -> watering -> soaking -> idle
    const SOAK_DELAY_SEC = @json(config('nursery.soak_delay'));
    const ZONE_STATES = {
        watering: 'Menyiram',
        queued: 'Antre',
        soaking: 'Jeda Serap',
        idle: 'Standby',
    };

    // Himpunan fuzzy dominan (fuzzy_logic.cpp): kering/lembab berpotongan di 37,5%, lembab/basah di 57,5%
    function fuzzySoilInfo(soil) {
        if (soil === null || soil === undefined) return { cls: 'none', label: 'Sensor tidak terbaca' };
        if (soil <= 37.5) return { cls: 'dry', label: 'Kering' };
        if (soil <= 57.5) return { cls: 'ok', label: 'Lembab' };
        return { cls: 'wet', label: 'Basah' };
    }

    // Penjelasan status, mengikuti urutan pengecekan di fuzzy_task.cpp
    function autoZoneDesc(z, fuzzyRuns) {
        if (z.state === 'watering') return 'Valve terbuka, pompa menyala.';
        if (z.state === 'queued') return 'Menunggu giliran pompa.';
        if (z.state === 'soaking') return `Selesai menyiram, menunggu air meresap ${SOAK_DELAY_SEC} detik.`;
        if (z.soil_moisture === null) return 'Sensor tanah tidak terbaca, zona tidak disiram otomatis.';
        if (!fuzzyRuns) return 'Evaluasi fuzzy dilewati, data suhu/cahaya tidak valid.';
        if (z.fuzzy_duration > 0) return 'Perlu disiram, menunggu jeda cooldown selesai.';
        return 'Tanah cukup lembab, tidak perlu disiram.';
    }

    function renderAutoZones(sensor, device) {
        const container = document.getElementById('auto-zone-container');
        // Firmware melewati evaluasi fuzzy jika DHT22 atau BH1750 error
        const fuzzyRuns = !!sensor && sensor.temperature !== null && sensor.humidity !== null && sensor.light_level !== null;

        let html = '';
        for (let i = 1; i <= NUM_ZONES; i++) {
            const z = (sensor?.zones || []).find(item => item.zone_id === i)
                || { soil_moisture: null, relay_status: false, state: null, fuzzy_duration: 0 };
            const state = sensor ? (ZONE_STATES[z.state] ? z.state : (z.relay_status ? 'watering' : 'idle')) : null;
            const soil = z.soil_moisture;
            const info = fuzzySoilInfo(soil);
            const width = soil === null ? 0 : Math.max(0, Math.min(100, soil));
            const fuzzy = soil === null ? '--' : (z.fuzzy_duration > 0 ? fmt(z.fuzzy_duration, 0) + ' dtk' : 'Tidak siram');
            const on = !!z.relay_status;

            html += `<div class="schedule-zone zone-status ${state || ''}">
                <div class="schedule-zone-header">
                    <div class="schedule-zone-title"><div class="zone-dot"></div>Zona ${i}</div>
                    <span class="zone-state-badge ${state || ''}"><span class="zone-state-dot"></span>${state ? ZONE_STATES[state] : '--'}</span>
                </div>
                <div class="soil-box ${info.cls}">
                    <div class="soil-box-row">
                        <span class="soil-box-label">Kelembaban Tanah</span>
                        <span class="soil-box-value">${soil === null ? '--' : fmt(soil, 0) + '%'} <small>(${info.label})</small></span>
                    </div>
                    <div class="soil-bar"><div class="soil-bar-fill" style="width:${width}%"></div></div>
                    <div class="soil-box-row soil-box-foot">
                        <span>Rekomendasi fuzzy</span>
                        <span class="zone-fuzzy">${fuzzy}</span>
                    </div>
                </div>
                <div class="zone-status-body">
                    <div class="schedule-slot-header">
                        <span class="schedule-slot-title">Valve Zona ${i}</span>
                        <span class="valve-pill ${on ? 'on' : ''}">${sensor ? (on ? 'ON' : 'OFF') : '--'}</span>
                    </div>
                    <p class="zone-status-desc">${sensor ? autoZoneDesc(z, fuzzyRuns) : 'Belum ada data dari alat.'}</p>
                </div>
            </div>`;
        }
        container.innerHTML = html;

        const pump = document.getElementById('auto-pump');
        const pumpOn = !!sensor?.relay_main;
        pump.className = 'pump-chip' + (pumpOn ? ' on' : '');
        pump.innerText = sensor ? `Pompa Utama ${pumpOn ? 'ON' : 'OFF'}` : 'Pompa Utama --';

        const alert = document.getElementById('auto-alert');
        alert.style.display = (sensor && !fuzzyRuns) ? 'block' : 'none';
        alert.innerText = '⚠ Data DHT22/BH1750 tidak valid. Evaluasi fuzzy dilewati, tidak ada zona yang mulai disiram otomatis.';

        document.getElementById('auto-updated').innerText = !sensor ? ''
            : `Data alat: ${sensor.recorded_at}` + (device && !device.online ? ' · alat offline, status bisa sudah berubah' : '');
    }

    async function changeSystemMode() {
        const modeVal = parseInt(document.getElementById('systemModeSelect').value);
        showModeSections(modeVal);
        try {
            const data = await postJson("{{ route('dashboard.update-mode') }}", {
                system_mode: modeVal,
                manual_mode_type: parseInt(document.getElementById('cfg-manual-mode').value),
                manual_duration: parseInt(document.getElementById('cfg-manual-dur').value),
            });
            updateAuditNotice(data.updated_by, data.updated_at);
            toast(data.message);
            fetchStatus();
        } catch (e) { toast(e.message, 'error'); }
    }

    // ============ TOGGLE RELAY ============
    async function toggleRelay(relay, on) {
        try {
            const data = await postJson("{{ route('dashboard.toggle-relay') }}", { relay: relay === 'main' ? 'main' : parseInt(relay), on });
            updateAuditNotice(data.updated_by, data.updated_at);
            toast(data.message);
        } catch (e) { toast(e.message, 'error'); }
        fetchStatus();
    }

    // ============ SCHEDULE ============
    function renderScheduleConfig() {
        const container = document.getElementById('schedule-container');
        if (!container) return;
        let html = '';
        for (let i = 0; i < NUM_ZONES; i++) {
            let slotsHtml = '';
            let countOptions = '';
            for (let j = 0; j < SCHEDULE_SLOTS; j++) {
                countOptions += `<option value="${j+1}">${j+1}x Sehari</option>`;
                slotsHtml += `<div id="sch-row-${i}-${j}" class="schedule-slot" ${j === 0 ? '' : 'hidden'}>
                    <div class="schedule-slot-header">
                        <span class="schedule-slot-title">Jadwal Slot ${j+1}</span>
                        <label class="toggle-wrap" title="Aktifkan slot"><input type="checkbox" class="toggle-input" id="sch-active-${i}-${j}" aria-label="Aktifkan slot ${j+1} zona ${i+1}"><div class="toggle-track"></div></label>
                    </div>
                    <div class="schedule-slot-fields">
                        <label class="slot-field">
                            <span class="slot-field-label">Jam Mulai</span>
                            <input type="time" id="sch-time-${i}-${j}" class="form-input sch-time" aria-label="Jam slot ${j+1} zona ${i+1}">
                        </label>
                        <label class="slot-field">
                            <span class="slot-field-label">Durasi</span>
                            <span class="input-suffix">
                                <input type="number" id="sch-dur-${i}-${j}" min="1" max="3600" placeholder="60" class="sch-dur" aria-label="Durasi slot ${j+1} zona ${i+1} (detik)">
                                <span class="slot-unit">dtk</span>
                            </span>
                        </label>
                    </div>
                </div>`;
            }
            html += `<div class="schedule-zone">
                <div class="schedule-zone-header">
                    <div class="schedule-zone-title"><div class="zone-dot"></div>Zona ${i+1}</div>
                    <select id="sch-count-${i}" onchange="updateScheduleCount(${i})" class="form-input form-input--sm schedule-count" aria-label="Jumlah jadwal zona ${i+1}">
                        ${countOptions}
                    </select>
                </div>
                <div class="soil-box" id="sch-soil-${i}"></div>
                <div id="sch-slots-${i}" class="schedule-slots">${slotsHtml}</div>
            </div>`;
        }
        container.innerHTML = html;
        populateScheduleInputs(currentSchedules);
        renderScheduleSoil([]);
    }
    function updateScheduleCount(zoneIndex) {
        const count = parseInt(document.getElementById('sch-count-' + zoneIndex).value);
        for (let j = 0; j < SCHEDULE_SLOTS; j++) { document.getElementById('sch-row-'+zoneIndex+'-'+j).hidden = j >= count; }
    }

    // Kelembaban tanah per zona di kartu jadwal. Aturan sama dengan firmware (schedule_task.cpp):
    // jadwal dilewati jika tanah > SCHEDULE_SOIL_MAX; sensor tidak terbaca (null) tetap disiram.
    const SCHEDULE_SOIL_MAX = @json(config('nursery.schedule_soil_max'));
    const SOIL_DRY_MAX = 45;   // fuzzy firmware: himpunan "kering" berakhir di 45%
    function soilInfo(soil) {
        if (soil === null || soil === undefined) return { cls: 'none', label: 'Sensor tidak terbaca', status: 'Jadwal Tetap Jalan' };
        if (soil > SCHEDULE_SOIL_MAX) return { cls: 'wet', label: 'Basah', status: 'Lewati Penyiraman' };
        if (soil <= SOIL_DRY_MAX) return { cls: 'dry', label: 'Kering', status: 'Perlu Disiram' };
        return { cls: 'ok', label: 'Optimal', status: 'Siap Disiram' };
    }
    function renderScheduleSoil(zones) {
        for (let i = 1; i <= NUM_ZONES; i++) {
            const el = document.getElementById('sch-soil-' + (i - 1));
            if (!el) continue;
            const soil = zones.find(z => z.zone_id === i)?.soil_moisture ?? null;
            const info = soilInfo(soil);
            const width = soil === null ? 0 : Math.max(0, Math.min(100, soil));
            el.className = 'soil-box ' + info.cls;
            el.innerHTML = `<div class="soil-box-row">
                    <span class="soil-box-label">Kelembaban Tanah</span>
                    <span class="soil-box-value">${soil === null ? '--' : fmt(soil, 0) + '%'} <small>(${info.label})</small></span>
                </div>
                <div class="soil-bar"><div class="soil-bar-fill" style="width:${width}%"></div></div>
                <div class="soil-box-row soil-box-foot">
                    <span>Siram jika ≤ ${SCHEDULE_SOIL_MAX}%</span>
                    <span class="soil-box-status">${info.status}</span>
                </div>`;
        }
    }
    // Format firmware: [{"zone":1,"slots":[{"active","hour","minute","duration"}, ...]}, ...]
    function populateScheduleInputs(data) {
        if (!data || !Array.isArray(data)) return;
        data.forEach(z => {
            const i = z.zone - 1;
            if (!(i >= 0 && i < NUM_ZONES)) return;
            let activeCount = 1;
            (z.slots || []).forEach((s, j) => {
                if (j >= SCHEDULE_SLOTS) return;
                const activeEl = document.getElementById('sch-active-'+i+'-'+j);
                const timeEl = document.getElementById('sch-time-'+i+'-'+j);
                const durEl = document.getElementById('sch-dur-'+i+'-'+j);
                if (activeEl) activeEl.checked = !!s.active;
                if (s.active && j >= activeCount) activeCount = j+1;
                if (timeEl && s.hour!=null && s.minute!=null) timeEl.value = String(s.hour).padStart(2,'0')+':'+String(s.minute).padStart(2,'0');
                if (durEl) durEl.value = s.duration!=null ? s.duration : '';
            });
            const countEl = document.getElementById('sch-count-'+i);
            if (countEl) { countEl.value = activeCount; updateScheduleCount(i); }
        });
    }
    function saveSchedule(button) {
        const payload = [];
        for (let i = 0; i < NUM_ZONES; i++) {
            const count = parseInt(document.getElementById('sch-count-'+i).value);
            const slots = [];
            for (let j = 0; j < SCHEDULE_SLOTS; j++) {
                if (j < count) {
                    const tv = document.getElementById('sch-time-'+i+'-'+j).value;
                    const dv = document.getElementById('sch-dur-'+i+'-'+j).value;
                    let hour=null, minute=null;
                    if (tv) { const p=tv.split(':'); hour=parseInt(p[0]); minute=parseInt(p[1]); }
                    slots.push({ active: document.getElementById('sch-active-'+i+'-'+j).checked, hour, minute, duration: dv===''?null:parseInt(dv) });
                } else { slots.push({ active:false, hour:0, minute:0, duration:10 }); }
            }
            payload.push({ zone: i + 1, slots });
        }
        return withBusy(button, async () => {
            const data = await postJson("{{ route('dashboard.save-schedule') }}", { schedules: payload });
            toast(data.message);
            currentSchedules = payload;
            updateAuditNotice(data.updated_by, data.updated_at);
            fetchStatus();
        });
    }

    // ============ AUDIT NOTICE ============
    function updateAuditNotice(userName, timeStr) {
        document.getElementById('audit-user-name').innerText = userName;
        document.getElementById('audit-user-time').innerText = timeStr;
    }

    // ============ FETCH LIVE STATUS ============
    let pollInFlight = false;
    async function fetchStatus() {
        if (pollInFlight) return;
        pollInFlight = true;
        try {
            const data = await api("{{ route('dashboard.status') }}");
            const control = data.device_control;
            const sensor = data.latest_sensor;
            desiredMode = control.system_mode;

            if (sensor) {
                document.getElementById('temp').innerText = fmt(sensor.temperature);
                document.getElementById('hum').innerText = fmt(sensor.humidity);
                document.getElementById('light').innerText = fmt(sensor.light_level, 0);
                document.getElementById('timestamp').innerText = sensor.recorded_at;
                const soils = (sensor.zones || []).map(z => z.soil_moisture).filter(v => v !== null);
                document.getElementById('soil').innerText = soils.length ? fmt(soils.reduce((s,v)=>s+v,0)/soils.length, 0) : '--';
            }
            renderZones(sensor?.zones || [], sensor?.system_mode);
            renderScheduleSoil(sensor?.zones || []);
            renderAutoZones(sensor, data.device);
            renderRelayControls(sensor, control.pending_relays || {}, control.system_mode);
            renderSensorAlert(sensor);
            renderDeviceStatus(data.device);
            renderClock(data.device.clock);
            renderFirmware(data.firmware);

            updateAuditNotice(control.updated_by, control.updated_at_exact);
            renderModeDetail(control);
            const [badgeText, badgeClass] = MODE_BADGES[control.system_mode] || MODE_BADGES[0];
            const badge = document.getElementById('irrig-mode-badge');
            badge.innerText = badgeText;
            badge.className = 'mode-badge ' + badgeClass;

            // Mode bisa berubah dari dashboard lokal alat; samakan pilihan jika user tidak sedang mengedit
            const select = document.getElementById('systemModeSelect');
            if (document.activeElement !== select && parseInt(select.value) !== control.system_mode) {
                select.value = control.system_mode;
                showModeSections(control.system_mode);
            }

            if (data.recent_logs && data.recent_logs.length > 0) {
                document.getElementById('audit-log-body').innerHTML = data.recent_logs.map(l => {
                    const [date, time] = String(l.time_exact).split(' ');   // "d/m/Y H:i:s"
                    return `<tr><td class="log-time"><span>${esc(date)}</span> <span>${esc(time)}</span></td>`
                        + `<td><strong>${esc(l.user)}</strong></td><td>${esc(l.action)}</td></tr>`;
                }).join('');
            }
        } catch (err) {
            console.error('Fetch error:', err);
        } finally {
            pollInFlight = false;
        }
    }

    // ============ CONFIG (mengikuti tab Config firmware) ============
    // Nonaktifkan tombol selama request berjalan (cegah klik ganda), error tampil sebagai toast
    async function withBusy(button, task) {
        if (button) button.disabled = true;
        try {
            await task();
        } catch (e) {
            toast(e.message, 'error');
        } finally {
            if (button) button.disabled = false;
        }
    }

    function formatDuration(sec) {
        if (sec >= 3600) return Math.floor(sec / 3600) + 'j ' + Math.floor((sec % 3600) / 60) + 'm';
        if (sec >= 60) return Math.floor(sec / 60) + 'm ' + (sec % 60) + 'd';
        return sec + 'd';
    }

    // Kartu RTC: jam alat pada telemetry terakhir vs jam server saat diterima
    const CLOCK_TOLERANCE_SEC = 60;   // selisih kecil wajar karena waktu kirim telemetry
    function renderClock(clock) {
        const chip = document.getElementById('rtc-chip');
        document.getElementById('rtc-time').innerText = !clock ? '--' : (clock.valid ? clock.device_time : 'Belum di-set');
        document.getElementById('rtc-received').innerText = clock ? clock.received_at : '--';

        if (!clock) {
            chip.innerText = 'Belum ada data';
            chip.className = 'status-chip';
        } else if (!clock.valid) {
            chip.innerText = 'Jam alat belum di-set';
            chip.className = 'status-chip bad';
        } else if (Math.abs(clock.drift_seconds) <= CLOCK_TOLERANCE_SEC) {
            chip.innerText = 'Sesuai jam server';
            chip.className = 'status-chip ok';
        } else {
            chip.innerText = 'Selisih ' + formatDuration(Math.abs(clock.drift_seconds)) + (clock.drift_seconds > 0 ? ' lebih cepat' : ' lebih lambat');
            chip.className = 'status-chip bad';
        }
    }

    // Kartu OTA: versi di alat vs rilis aktif, status dari laporan "ota" alat (lihat firmware src/ota.cpp)
    let firmwareReleaseId = null;
    function firmwareChip(fw) {
        const release = fw.release;
        const ota = fw.ota || {};
        if (!release) return ['Tidak ada rilis', ''];
        if (fw.device_version === release.version) return ['Terpasang', 'ok'];
        if (ota.target === release.version) {
            if (ota.state === 'deferred') return ['Menunggu relay mati', ''];
            if (ota.state === 'ready') return ['Menunggu restart', ''];
            if (ota.state === 'failed') return ['Gagal: ' + (ota.error || 'tidak diketahui') + ' (dicoba lagi)', 'bad'];
            if (ota.state === 'rejected') return ['Ditolak alat / rollback', 'bad'];
        }
        if (!fw.device_version) return ['Firmware alat belum mendukung OTA (upload USB sekali)', ''];
        return ['Menunggu alat mengambil', ''];
    }

    function renderFirmware(fw) {
        const release = fw.release;
        firmwareReleaseId = release ? release.id : null;
        document.getElementById('fw-device').innerText = fw.device_version || '--';
        document.getElementById('fw-release').innerText = release
            ? `${release.version} (${(release.size / 1048576).toFixed(2)} MB, ${release.uploaded_at})`
            : 'Tidak ada';
        document.getElementById('fw-cancel').style.display = release ? '' : 'none';

        const [text, cls] = firmwareChip(fw);
        const chip = document.getElementById('fw-chip');
        chip.innerText = text;
        chip.className = 'status-chip' + (cls ? ' ' + cls : '');
    }

    // Batas sama dengan validasi server (FirmwareController::upload); server tetap memeriksa ulang
    const FW_VERSION_RE = /^\d{1,3}\.\d{1,3}\.\d{1,3}$/;
    const FW_MAX_BYTES = 6400 * 1024;

    // Langsung saat file dipilih: selain .bin dikosongkan, jadi tidak bisa ikut dirilis
    function checkFirmwareFile(input) {
        const file = input.files[0];
        if (!file) return;
        if (!/\.bin$/i.test(file.name)) {
            input.value = '';
            toast('File firmware harus berformat .bin.', 'error');
        } else if (file.size === 0 || file.size > FW_MAX_BYTES) {
            input.value = '';
            toast('Ukuran file firmware harus di antara 1 byte dan 6,25 MB.', 'error');
        }
    }

    function uploadFirmware(button) {
        const version = document.getElementById('fw-version').value.trim();
        const file = document.getElementById('fw-file').files[0];
        if (!version || !file) { toast('Isi versi dan pilih file .bin terlebih dahulu.', 'error'); return; }
        if (!FW_VERSION_RE.test(version)) { toast('Versi harus angka dengan format X.Y.Z (mis. 1.0.2).', 'error'); return; }
        if (!/\.bin$/i.test(file.name)) { toast('File firmware harus berformat .bin.', 'error'); return; }
        if (file.size === 0 || file.size > FW_MAX_BYTES) { toast('Ukuran file firmware harus di antara 1 byte dan 6,25 MB.', 'error'); return; }
        if (!confirm(`Rilis firmware ${version}? Alat akan memasangnya saat online dan semua relay mati.`)) return;

        const form = new FormData();
        form.append('version', version);
        form.append('firmware', file);
        form.append('notes', document.getElementById('fw-notes').value.trim());

        return withBusy(button, async () => {
            const data = await api("{{ route('firmware.upload') }}", { method: 'POST', body: form });
            toast(data.message);
            ['fw-version', 'fw-file', 'fw-notes'].forEach(id => { document.getElementById(id).value = ''; });
            document.getElementById('fw-notes-count').innerText = '0/500';
            fetchStatus();
        });
    }

    function cancelFirmware(button) {
        if (!firmwareReleaseId) return;
        if (!confirm('Batalkan rilis firmware ini? Alat yang sudah menunggu restart tetap pindah ke versi tersebut.')) return;
        return withBusy(button, async () => {
            const data = await api("{{ route('firmware.cancel', ['release' => '__ID__']) }}".replace('__ID__', firmwareReleaseId), { method: 'POST' });
            toast(data.message);
            fetchStatus();
        });
    }

    function saveSysConfig(button) {
        return withBusy(button, async () => {
            await postJson("{{ route('dashboard.sys-config') }}", {
                sensor_interval: parseInt(document.getElementById('cfg-interval').value),
                log_interval: parseInt(document.getElementById('cfg-log-interval').value),
                cooldown: parseInt(document.getElementById('cfg-cooldown').value),
            });
            toast('Konfigurasi berhasil disimpan, dikirim ke alat saat online');
            fetchStatus();
        });
    }

    function toggleCsvRange() {
        const useRange = document.getElementById('csv-use-range').checked;
        document.getElementById('csv-range').style.display = useRange ? 'flex' : 'none';
        document.getElementById('csv-date').closest('.form-row').style.display = useRange ? 'none' : 'flex';
    }

    const EXPORT_URLS = { csv: "{{ route('dashboard.export-csv') }}", xlsx: "{{ route('dashboard.export-xlsx') }}" };

    function exportLog(format) {
        let params;
        if (document.getElementById('csv-use-range').checked) {
            const from = document.getElementById('csv-from').value;
            const to = document.getElementById('csv-to').value;
            if (!from || !to || from > to) { toast('Tanggal "Dari" harus sebelum atau sama dengan "Sampai".', 'error'); return; }
            params = new URLSearchParams({ from, to });
        } else {
            const date = document.getElementById('csv-date').value;
            if (!date) { toast('Pilih tanggal terlebih dahulu.', 'error'); return; }
            params = new URLSearchParams({ date });
        }
        window.location.href = EXPORT_URLS[format] + '?' + params;
    }

    function resetLogData(button) {
        if (!confirm('Hapus semua data log sensor? Tindakan ini tidak dapat dibatalkan.')) return;
        return withBusy(button, async () => {
            await api("{{ route('dashboard.reset-log') }}", { method: 'POST' });
            toast('Data log berhasil dihapus');
        });
    }

    // ============ JAM BANNER ============
    const APP_TIMEZONE = @json(config('app.timezone'));
    const heroClockFormat = new Intl.DateTimeFormat('id-ID', { timeZone: APP_TIMEZONE, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' });
    const heroDateFormat = new Intl.DateTimeFormat('id-ID', { timeZone: APP_TIMEZONE, weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });

    function updateHeroClock() {
        const now = new Date();
        const parts = Object.fromEntries(heroClockFormat.formatToParts(now).map(p => [p.type, p.value]));
        document.getElementById('hero-clock').textContent = `${parts.hour}:${parts.minute}`;   // 21:05, bukan 21.05
        document.getElementById('hero-date').textContent = heroDateFormat.format(now);
    }
    updateHeroClock();
    setInterval(updateHeroClock, 1000);

    // ============ STATISTIK ============
    // Data dari StatisticsController; dimuat saat tab Statistik dibuka, bukan lewat polling 3 detik
    const STATS_URL = "{{ route('dashboard.statistics') }}";
    const STATS_RANGES = ['24h', '7d', '30d'];
    const STATS_STALE_MS = 60000;
    const SVG_NS = 'http://www.w3.org/2000/svg';
    const compactNumber = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 });
    let statsRange = '24h';
    let statsData = null;
    let statsLoadedAt = 0;
    let statsRequestId = 0;   // balasan permintaan lama diabaikan jika rentang cepat diganti

    try {
        const saved = localStorage.getItem('statsRange');
        if (STATS_RANGES.includes(saved)) statsRange = saved;
    } catch (e) { /* penyimpanan browser diblokir: pakai 24 jam */ }

    // Warna mengikuti nomor zona (urutan palet tetap, lihat --chart-zone-* di style.css). Firmware maksimal 5 zona.
    function zoneColor(zone) {
        return `var(--chart-zone-${((zone - 1) % 8) + 1})`;
    }

    function svgEl(tag, attrs, parent) {
        const el = document.createElementNS(SVG_NS, tag);
        Object.entries(attrs || {}).forEach(([key, value]) => el.setAttribute(key, value));
        if (parent) parent.appendChild(el);
        return el;
    }

    function lastValueIndex(values) {
        for (let i = values.length - 1; i >= 0; i--) if (values[i] != null) return i;
        return -1;
    }

    // Batas sumbu Y dengan kelipatan bulat (1 / 2 / 2,5 / 5 × 10^n)
    function niceScale(lo, hi, count = 4) {
        if (lo === hi) { lo = lo >= 1 ? lo - 1 : 0; hi += 1; }
        const raw = (hi - lo) / count;
        const mag = Math.pow(10, Math.floor(Math.log10(raw)));
        const step = [1, 2, 2.5, 5, 10].map(m => m * mag).find(s => s >= raw - 1e-9);
        const min = Math.floor(lo / step + 1e-9) * step;
        const max = Math.ceil(hi / step - 1e-9) * step;
        const ticks = [];
        for (let v = min; v <= max + step / 2; v += step) ticks.push(Math.round(v * 1e6) / 1e6);
        return { min, max, ticks };
    }

    function axisNumber(v) {
        if (Math.abs(v) >= 10000) return compactNumber.format(v);
        return Number.isInteger(v) ? String(v) : v.toFixed(1);
    }

    // Digambar ulang saat lebar kartu berubah (putar layar, sidebar, tab dibuka)
    const chartObserver = new ResizeObserver(entries => {
        entries.forEach(entry => { if (entry.target._chart) drawLineChart(entry.target); });
    });

    function renderLineChart(box, options) {
        box._chart = options;
        if (!box._observed) {
            chartObserver.observe(box);
            box._observed = true;
        }
        drawLineChart(box);
    }

    // Grafik garis SVG: satu sumbu Y, garis 2px yang putus pada data kosong (null),
    // crosshair + tooltip semua seri saat hover/sentuh, panah kiri/kanan saat grafik difokus.
    function drawLineChart(box) {
        const width = box.clientWidth;
        if (!width) return;   // tab tersembunyi; ResizeObserver menggambar saat tampil
        const height = box.clientHeight;
        const { label, ticks, tips, series, unit = '', digits = 1, yMin, yMax, directLabels = false } = box._chart;
        box.replaceChildren();

        const values = series.flatMap(s => s.values).filter(v => v != null);
        if (!values.length) {
            const empty = document.createElement('div');
            empty.className = 'chart-empty';
            empty.textContent = 'Belum ada data pada rentang ini.';
            box.appendChild(empty);
            return;
        }

        const pad = { top: 8, right: directLabels ? 64 : 14, bottom: 22, left: 38 };
        const plotW = Math.max(10, width - pad.left - pad.right);
        const plotH = Math.max(10, height - pad.top - pad.bottom);
        // yMin/yMax = rentang minimal (mis. tanah 0-100%); data di luar itu tetap muat
        const scale = niceScale(Math.min(yMin ?? Infinity, ...values), Math.max(yMax ?? -Infinity, ...values));
        const count = ticks.length;
        const x = i => pad.left + (count === 1 ? plotW / 2 : (i * plotW) / (count - 1));
        const y = v => pad.top + plotH - ((v - scale.min) / (scale.max - scale.min)) * plotH;

        const svg = svgEl('svg', {
            width, height, viewBox: `0 0 ${width} ${height}`, tabindex: 0, role: 'img',
            'aria-label': `${label}. Tekan panah kiri atau kanan untuk membaca nilai.`,
        });

        scale.ticks.forEach(v => {
            const yy = Math.round(y(v)) + 0.5;
            svgEl('line', { class: v === scale.min ? 'chart-baseline' : 'chart-grid-line', x1: pad.left, x2: pad.left + plotW, y1: yy, y2: yy }, svg);
            svgEl('text', { class: 'chart-axis-label', x: pad.left - 6, y: yy, 'text-anchor': 'end', 'dominant-baseline': 'middle' }, svg)
                .textContent = axisNumber(v);
        });

        // Label X hanya saat teksnya berganti (7 hari: awal tiap tanggal), dijarangkan minimal 56px
        let lastTickX = -Infinity;
        for (let i = 0; i < count; i++) {
            if ((i > 0 && ticks[i] === ticks[i - 1]) || x(i) - lastTickX < 56) continue;
            lastTickX = x(i);
            svgEl('text', { class: 'chart-axis-label', x: x(i), y: height - 6, 'text-anchor': 'middle' }, svg).textContent = ticks[i];
        }

        // Bucket terakhir masih berjalan (rata-ratanya belum lengkap): segmen ke titik itu digambar putus-putus
        const line = (d, color, dashed) => svgEl('path', {
            d, fill: 'none', 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-linecap': 'round',
            ...(dashed ? { 'stroke-dasharray': '3 4' } : {}), style: `stroke:${color}`,
        }, svg);
        series.forEach(s => {
            let d = '';
            s.values.forEach((v, i) => {
                if (v == null) return;
                const prevMissing = i === 0 || s.values[i - 1] == null;
                const nextMissing = i === count - 1 || s.values[i + 1] == null;
                const point = `${x(i).toFixed(1)},${y(v).toFixed(1)}`;
                if (i === count - 1 && !prevMissing) {
                    line(`M${x(i - 1).toFixed(1)},${y(s.values[i - 1]).toFixed(1)}L${point}`, s.color, true);
                } else {
                    d += `${prevMissing ? 'M' : 'L'}${point}`;
                }
                // Titik tunggal di antara data kosong tidak membentuk garis: tampilkan sebagai titik
                if (prevMissing && nextMissing) svgEl('circle', { cx: x(i), cy: y(v), r: 2.5, style: `fill:${s.color}` }, svg);
            });
            if (d) line(d, s.color, false);
        });

        // Label nama seri di ujung kanan garis, digeser agar tidak bertumpuk
        if (directLabels) {
            const labels = series
                .map(s => { const last = lastValueIndex(s.values); return last < 0 ? null : { s, y: y(s.values[last]) }; })
                .filter(Boolean)
                .sort((a, b) => a.y - b.y);
            for (let k = 1; k < labels.length; k++) labels[k].y = Math.max(labels[k].y, labels[k - 1].y + 13);
            const overflow = labels.length ? labels[labels.length - 1].y - (pad.top + plotH) : 0;
            if (overflow > 0) labels.forEach(l => { l.y -= overflow; });
            const lx = pad.left + plotW + 6;
            labels.forEach(l => {
                svgEl('line', { x1: lx, x2: lx + 8, y1: l.y, y2: l.y, 'stroke-width': 2, style: `stroke:${l.s.color}` }, svg);
                svgEl('text', { class: 'chart-direct-label', x: lx + 12, y: l.y, 'dominant-baseline': 'middle' }, svg).textContent = l.s.name;
            });
        }

        const crosshair = svgEl('line', { class: 'chart-crosshair', y1: pad.top, y2: pad.top + plotH, visibility: 'hidden' }, svg);
        const dots = svgEl('g', {}, svg);
        // Area sentuh selebar grafik: pembaca cukup menunjuk waktu, tidak perlu mengenai garis
        const overlay = svgEl('rect', { x: pad.left - 8, y: 0, width: plotW + 16, height: pad.top + plotH, fill: 'transparent' }, svg);
        const tooltip = document.createElement('div');
        tooltip.className = 'chart-tooltip';
        tooltip.hidden = true;
        box.append(svg, tooltip);

        let active = -1;
        const show = i => {
            active = Math.max(0, Math.min(count - 1, i));
            const cx = x(active);
            crosshair.setAttribute('x1', cx);
            crosshair.setAttribute('x2', cx);
            crosshair.setAttribute('visibility', 'visible');
            dots.replaceChildren();

            const title = document.createElement('div');
            title.className = 'chart-tooltip-title';
            title.textContent = tips[active];
            const rows = series.map(s => {
                const v = s.values[active];
                if (v != null) svgEl('circle', { class: 'chart-dot', cx, cy: y(v), r: 4, style: `fill:${s.color}` }, dots);
                const row = document.createElement('div');
                row.className = 'chart-tooltip-row';
                const key = document.createElement('span');
                key.className = 'chart-key';
                key.style.background = s.color;
                const value = document.createElement('strong');
                value.textContent = v == null ? '--' : fmt(v, digits) + unit;
                const name = document.createElement('span');
                name.textContent = s.name;
                row.append(key, value, name);
                return row;
            });
            tooltip.replaceChildren(title, ...rows);
            tooltip.hidden = false;

            // Di kanan crosshair; pindah ke kiri jika tidak muat
            const tw = tooltip.offsetWidth;
            const left = cx + 12 + tw <= width ? cx + 12 : Math.max(0, cx - 12 - tw);
            tooltip.style.transform = `translate(${Math.round(left)}px, ${pad.top}px)`;
        };
        const hide = () => {
            active = -1;
            crosshair.setAttribute('visibility', 'hidden');
            dots.replaceChildren();
            tooltip.hidden = true;
        };
        const indexAt = clientX => {
            const rect = svg.getBoundingClientRect();
            return Math.round(((clientX - rect.left - pad.left) / plotW) * (count - 1));
        };

        overlay.addEventListener('pointerdown', e => show(indexAt(e.clientX)));
        overlay.addEventListener('pointermove', e => show(indexAt(e.clientX)));
        // Di layar sentuh tooltip tetap tampil setelah jari diangkat; hilang saat menyentuh tempat lain (blur)
        overlay.addEventListener('pointerleave', e => { if (e.pointerType === 'mouse') hide(); });
        svg.addEventListener('focus', () => {
            if (active < 0) show(Math.max(...series.map(s => lastValueIndex(s.values))));
        });
        svg.addEventListener('blur', hide);
        svg.addEventListener('keydown', e => {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            e.preventDefault();
            show((active < 0 ? count - 1 : active) + (e.key === 'ArrowRight' ? 1 : -1));
        });
    }

    function setStatTile(key, stat, digits) {
        document.getElementById(`stat-${key}`).textContent = fmt(stat?.avg, digits);
    }

    function renderStats(data) {
        statsData = data;
        const { ticks, tips } = data.labels;
        const offline = data.offline_count ? ` (${data.offline_count} dari buffer offline)` : '';
        document.getElementById('stats-meta').textContent = `${data.record_count} data tercatat${offline} · diperbarui ${data.generated_at}`;

        setStatTile('temp', data.summary.temperature, 1);
        setStatTile('hum', data.summary.humidity, 1);
        setStatTile('light', data.summary.light_level, 0);
        setStatTile('soil', data.summary.soil, 1);

        // Satu seri per grafik (tanpa sumbu ganda); judul kartu sudah menamai serinya
        const single = (label, name, values, unit, digits) => ({
            label, ticks, tips, unit, digits, series: [{ name, color: 'var(--chart-single)', values }],
        });
        renderLineChart(document.getElementById('chart-temp'), single('Grafik suhu udara', 'Suhu', data.series.temperature, ' °C', 1));
        renderLineChart(document.getElementById('chart-hum'), single('Grafik kelembaban udara', 'Lembab udara', data.series.humidity, '%', 1));
        renderLineChart(document.getElementById('chart-light'), single('Grafik cahaya', 'Cahaya', data.series.light_level, ' lx', 0));

        const soilSeries = [];
        for (let i = 1; i <= NUM_ZONES; i++) {
            soilSeries.push({ name: `Zona ${i}`, color: zoneColor(i), values: data.series.soil[i] || [] });
        }
        renderLineChart(document.getElementById('chart-soil'), {
            label: 'Grafik kelembaban tanah per zona', ticks, tips, unit: '%', digits: 1, yMin: 0, yMax: 100,
            series: soilSeries, directLabels: soilSeries.length <= 4,
        });
        document.getElementById('soil-legend').innerHTML = soilSeries.map(s =>
            `<span class="chart-legend-item"><span class="chart-key" style="background:${s.color}"></span>${esc(s.name)}</span>`
        ).join('');

        renderIrrigationTable(data.irrigation);
        renderStatsDataTable(data, soilSeries);
    }

    function renderIrrigationTable(rows) {
        document.getElementById('irrigation-body').innerHTML = rows.map(r => `<tr>
            <td><span class="chart-legend-item"><span class="chart-key" style="background:${zoneColor(r.zone)}"></span><strong>Zona ${esc(r.zone)}</strong></span></td>
            <td class="num">${esc(r.count)} kali</td>
            <td class="num">${r.count ? esc(formatDuration(r.total_seconds)) : '--'}</td>
            <td class="num">${r.avg_seconds !== null ? esc(formatDuration(r.avg_seconds)) : '--'}</td>
            <td class="num">${r.soil_avg === null ? '--' : fmt(r.soil_avg) + '%'}</td>
        </tr>`).join('');
    }

    // Tampilan tabel untuk semua grafik: satu baris per bucket waktu, terbaru di atas
    function renderStatsDataTable(data, soilSeries) {
        const head = ['Waktu', 'Suhu (°C)', 'Lembab Udara (%)', 'Cahaya (lx)', ...soilSeries.map(s => `${s.name} Tanah (%)`)];
        const digits = [1, 1, 0, ...soilSeries.map(() => 1)];
        document.getElementById('stats-data-head').innerHTML =
            '<tr>' + head.map((h, i) => `<th${i ? ' class="num"' : ''}>${esc(h)}</th>`).join('') + '</tr>';

        const rows = [];
        for (let i = data.labels.tips.length - 1; i >= 0; i--) {
            const cells = [data.series.temperature[i], data.series.humidity[i], data.series.light_level[i], ...soilSeries.map(s => s.values[i])];
            if (cells.every(v => v == null)) continue;
            rows.push(`<tr><td class="log-time">${esc(data.labels.tips[i])}</td>`
                + cells.map((v, k) => `<td class="num">${fmt(v, digits[k])}</td>`).join('') + '</tr>');
        }
        document.getElementById('stats-data-body').innerHTML = rows.length
            ? rows.join('')
            : `<tr><td colspan="${head.length}" class="table-empty">Belum ada data pada rentang ini.</td></tr>`;
    }

    // quiet = penyegaran latar (tanpa meredupkan isi)
    async function loadStatistics(force = false, quiet = false) {
        const fresh = statsData && statsData.range === statsRange && Date.now() - statsLoadedAt < STATS_STALE_MS;
        if (!force && fresh) return;

        const requestId = ++statsRequestId;
        const body = document.getElementById('stats-body');
        if (!quiet) body.classList.add('is-loading');
        try {
            const data = await api(STATS_URL + '?range=' + encodeURIComponent(statsRange));
            if (requestId !== statsRequestId) return;
            statsLoadedAt = Date.now();
            renderStats(data);
        } catch (e) {
            if (requestId !== statsRequestId) return;
            document.getElementById('stats-meta').textContent = 'Gagal memuat statistik';
            if (!quiet) toast(e.message, 'error');
        } finally {
            if (requestId === statsRequestId) body.classList.remove('is-loading');
        }
    }

    function markStatsRange() {
        document.querySelectorAll('.seg-btn[data-range]').forEach(btn => {
            const on = btn.dataset.range === statsRange;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function setStatsRange(range) {
        if (!STATS_RANGES.includes(range)) return;
        statsRange = range;
        try { localStorage.setItem('statsRange', range); } catch (e) { /* abaikan */ }
        markStatsRange();
        loadStatistics(true);
    }

    function statsTabActive() {
        return document.getElementById('tab-stats').classList.contains('tab-panel--active');
    }

    // Dimuat saat tab dibuka (switchTab di layout mengirim event ini), disegarkan tiap menit selama tab terbuka
    document.addEventListener('tabchange', e => { if (e.detail === 'tab-stats') loadStatistics(); });
    setInterval(() => { if (!document.hidden && statsTabActive()) loadStatistics(false, true); }, STATS_STALE_MS);

    // ============ INIT ============
    markStatsRange();
    renderScheduleConfig();
    renderModeDetail({
        system_mode: desiredMode,
        manual_mode_type: @json($deviceControl->manual_mode_type ?? 0),
        manual_duration: @json($deviceControl->manual_duration ?? 10),
        schedules: currentSchedules,
    });
    renderZones([], null);
    renderAutoZones(null, null);
    renderRelayControls(null, {}, desiredMode);
    fetchStatus();
    // Polling berhenti saat tab browser tidak aktif
    setInterval(() => { if (!document.hidden) fetchStatus(); }, POLL_INTERVAL_MS);
</script>
@endsection
