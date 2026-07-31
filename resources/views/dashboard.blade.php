@extends('layouts.app')

@section('title', 'Dashboard - Smart Nursery')

@section('content')

<!-- ===== TAB PANEL: DASHBOARD ===== -->
<div class="tab-panel tab-panel--active" id="tab-dashboard">

    <!-- Hero Banner -->
    <div class="hero-banner">
        <div class="hero-text">
            <div class="hero-greeting">Selamat Datang, {{ Auth::user()->name }}</div>
            <div class="hero-title">Smart Nursery</div>
            <div class="hero-sub">Monitoring &amp; Irigasi Otomatis Pembibitan Kelapa Sawit</div>
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
                {{ $deviceControl && $deviceControl->updatedBy ? $deviceControl->updatedBy->name : 'System' }}
            </span>
            pada <span id="audit-user-time" style="font-weight:700;">
                {{ $deviceControl && $deviceControl->updated_at ? $deviceControl->updated_at->format('d M Y, H:i T') : '-' }}
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
                <div class="sensor-value"><span id="temp">{{ $latestSensor ? number_format($latestSensor->temperature, 1) : '--' }}</span><span class="sensor-unit">°C</span></div>
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
                <div class="sensor-value"><span id="light">{{ $latestSensor ? number_format($latestSensor->light_level) : '--' }}</span><span class="sensor-unit">lx</span></div>
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
                <div class="sensor-value"><span id="hum">{{ $latestSensor ? number_format($latestSensor->humidity, 1) : '--' }}</span><span class="sensor-unit">%</span></div>
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
                    <span id="soil">
                        @if ($latestSensor && $latestSensor->zoneLogs->count() > 0)
                            {{ number_format($latestSensor->zoneLogs->avg('soil_moisture'), 0) }}
                        @else
                            --
                        @endif
                    </span>
                    <span class="sensor-unit">%</span>
                </div>
            </div>
        </div>

    </div><!-- /sensor-grid -->

    <!-- Valve Zones -->
    <div class="section-header">
        <h3 class="section-title">Sub-Blok / Zona Pembibitan</h3>
        <span class="count-badge">5 Total Zona</span>
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
        Terakhir diperbarui:&nbsp;<span id="timestamp" style="font-weight:700;color:var(--clr-primary);">--:--:--</span>
    </div>

</div><!-- /tab-panel dashboard -->


<!-- ===== TAB PANEL: KONTROL ===== -->
<div class="tab-panel" id="tab-control">

    <div class="section-label">Sistem Irigasi &amp; Kontrol Mode</div>

    <div class="irrigation-bar">
        <div class="irrigation-info">
            <h4>Status Mode Terpilih</h4>
            <div class="irrigation-status-row">
                <span class="mode-badge @if(($deviceControl->system_mode ?? 0) == 1) manual @elseif(($deviceControl->system_mode ?? 0) == 2) schedule @else active @endif" id="irrig-mode-badge">
                    @if(($deviceControl->system_mode ?? 0) == 1)
                        MANUAL
                    @elseif(($deviceControl->system_mode ?? 0) == 2)
                        SCHEDULE
                    @else
                        AUTO (FUZZY)
                    @endif
                </span>
                <span class="duration-text">Durasi Manual Timer: <strong><span id="irrig-time">{{ $deviceControl->manual_duration ?? 10 }}</span> dtk</strong></span>
            </div>
        </div>

        <div class="override-control">
            <div>
                <div class="override-label-title">Mode Sistem</div>
                <div class="override-label-sub">Pilih mode operasional</div>
            </div>
            <select id="systemModeSelect" onchange="changeSystemMode()">
                <option value="0" {{ ($deviceControl->system_mode ?? 0) == 0 ? 'selected' : '' }}>Auto (Fuzzy)</option>
                <option value="1" {{ ($deviceControl->system_mode ?? 0) == 1 ? 'selected' : '' }}>Manual</option>
                <option value="2" {{ ($deviceControl->system_mode ?? 0) == 2 ? 'selected' : '' }}>Schedule</option>
            </select>
        </div>

        <!-- MANUAL SETTINGS -->
        <div id="manualSettings" style="display: {{ ($deviceControl->system_mode ?? 0) == 1 ? 'flex' : 'none' }}; width:100%; border-top:1px solid var(--clr-outline-var); padding-top:1rem; flex-wrap:wrap; gap:1rem;">
            <div style="flex:1; min-width:140px;">
                <label class="form-label">Tipe Mode Manual</label>
                <select id="cfg-manual-mode" onchange="updateManualConfig()" class="form-input">
                    <option value="0" {{ ($deviceControl->manual_mode_type ?? 0) == 0 ? 'selected' : '' }}>Toggle (Direct On/Off)</option>
                    <option value="1" {{ ($deviceControl->manual_mode_type ?? 0) == 1 ? 'selected' : '' }}>Timer (Mati Otomatis)</option>
                </select>
            </div>
            <div style="flex:1; min-width:140px;">
                <label class="form-label">Durasi Timer (detik)</label>
                <input type="number" id="cfg-manual-dur" min="1" max="3600" value="{{ $deviceControl->manual_duration ?? 10 }}" onchange="updateManualConfig()" class="form-input">
            </div>
        </div>

        <!-- SCHEDULE SETTINGS -->
        <div id="scheduleSettings" style="display: {{ ($deviceControl->system_mode ?? 0) == 2 ? 'flex' : 'none' }}; width:100%; border-top:1px solid var(--clr-outline-var); padding-top:1rem; flex-direction:column; gap:0.75rem;">
            <div style="font-size:0.85rem; font-weight:800; color:var(--clr-on-surface);">Konfigurasi Jadwal Penyiraman (Maksimal 3 Slot per Zona)</div>
            <div id="schedule-container" style="display:flex; flex-direction:column; gap:0.625rem;"></div>
            <button class="btn-cta" onclick="saveSchedule()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1rem;height:1rem;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
                    <polyline points="17 21 17 13 7 13 7 21" />
                    <polyline points="7 3 7 8 15 8" />
                </svg>
                Simpan Jadwal
            </button>
        </div>
    </div><!-- /irrigation-bar -->

    <!-- Valve Zones Control -->
    <div class="section-header">
        <h3 class="section-title">Kontrol Zona Manual</h3>
        <span class="count-badge">5 Total Zona</span>
    </div>
    <div class="valve-grid" id="valve-container-ctrl">
        <!-- Rendered by JS -->
    </div>

</div><!-- /tab-panel kontrol -->


<!-- ===== TAB PANEL: LOG ===== -->
<div class="tab-panel" id="tab-log">

    <div class="section-label">Riwayat Aktivitas</div>

    <div class="audit-table-card" style="margin-top:0;">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem;">
            <div class="section-title" style="font-size:0.9rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.1rem;height:1.1rem;vertical-align:middle;margin-right:0.3rem;color:var(--clr-primary);">
                    <path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Riwayat Aktivitas Pengguna (Audit Log)
            </div>
        </div>
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
                        <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
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

</div><!-- /tab-panel log -->


<!-- ===== TAB PANEL: CONFIG ===== -->
<div class="tab-panel" id="tab-config">

    <!-- Konfigurasi Fuzzy -->
    <div class="section-header" style="margin-top:0.5rem;">
        <h3 class="section-title">Konfigurasi Fuzzy</h3>
        <span class="count-badge" style="cursor:pointer;" onclick="toggleFuzzyPanel()">
            <span id="fuzzy-toggle-icon">▼</span> Setting
        </span>
    </div>

    <div id="fuzzy-panel" style="display:none;">

        <!-- Kelembaban Tanah -->
        <div class="card">
            <div class="fz-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z" />
                </svg>
                Kelembaban Tanah (%)
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.75rem;">
                <div class="fuzzy-field"><label>Basah Max</label><input type="number" id="fz-basahMax" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Basah Min</label><input type="number" id="fz-basahMin" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Lembab Low</label><input type="number" id="fz-lembabLow" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Lembab Mid</label><input type="number" id="fz-lembabMid" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Lembab High</label><input type="number" id="fz-lembabHigh" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Kering Low</label><input type="number" id="fz-keringLow" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Kering Max</label><input type="number" id="fz-keringMax" step="0.1" class="form-input"></div>
            </div>
        </div>

        <!-- Suhu Udara -->
        <div class="card">
            <div class="fz-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z" />
                </svg>
                Suhu Udara (°C)
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.75rem;">
                <div class="fuzzy-field"><label>Dingin Max</label><input type="number" id="fz-dinginMax" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Dingin Min</label><input type="number" id="fz-dinginMin" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Normal Low</label><input type="number" id="fz-normalLow" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Normal Mid</label><input type="number" id="fz-normalMid" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Normal High</label><input type="number" id="fz-normalHigh" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Panas Low</label><input type="number" id="fz-panasLow" step="0.1" class="form-input"></div>
                <div class="fuzzy-field"><label>Panas Max</label><input type="number" id="fz-panasMax" step="0.1" class="form-input"></div>
            </div>
        </div>

        <!-- Cahaya -->
        <div class="card">
            <div class="fz-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
                Cahaya (Lux)
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.75rem;">
                <div class="fuzzy-field"><label>Redup Max</label><input type="number" id="fz-redupMax" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Redup Min</label><input type="number" id="fz-redupMin" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Sedang Low</label><input type="number" id="fz-sedangLow" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Sedang Mid</label><input type="number" id="fz-sedangMid" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Sedang High</label><input type="number" id="fz-sedangHigh" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Terang Low</label><input type="number" id="fz-terangLow" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Terang Max</label><input type="number" id="fz-terangMax" step="1" class="form-input"></div>
            </div>
        </div>

        <!-- Output Durasi -->
        <div class="card">
            <div class="fz-section-title">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                </svg>
                Output Durasi (Detik)
            </div>
            <div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(120px,1fr)); gap:0.75rem;">
                <div class="fuzzy-field"><label>Pendek Max</label><input type="number" id="fz-pendekMax" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Sedang Max</label><input type="number" id="fz-sedangOutMax" step="1" class="form-input"></div>
                <div class="fuzzy-field"><label>Lama Max</label><input type="number" id="fz-defuzzMax" step="1" class="form-input"></div>
            </div>
        </div>

        <div style="display:flex; gap:0.75rem; flex-wrap:wrap; margin-top:0.25rem; margin-bottom:1.5rem;">
            <button class="btn btn-primary" onclick="saveFuzzyConfig()" style="padding:0.625rem 1.5rem; flex:1;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:0.9rem;height:0.9rem;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                    <polyline points="7 3 7 8 15 8"/>
                </svg>
                Simpan Konfigurasi Fuzzy
            </button>
            <button class="btn btn-secondary" onclick="resetFuzzyConfig()" style="padding:0.625rem 1.25rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:0.9rem;height:0.9rem;">
                    <polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.53"/>
                </svg>
                Reset Default
            </button>
        </div>
    </div><!-- /fuzzy-panel -->

    <!-- Sistem & Log -->
    <div class="section-header" style="margin-top:1.5rem;">
        <h3 class="section-title">Sistem &amp; Log</h3>
    </div>

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
                <div class="form-hint" style="margin:0;">Waktu antar baca sensor (detik)</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <input type="number" id="cfg-interval" class="form-input" placeholder="10" min="1" max="3600">
            </div>
            <button class="btn btn-primary" onclick="saveSysConfig()" style="padding:0.5rem 1.25rem; white-space:nowrap;">Simpan</button>
        </div>
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
                <div class="form-hint" style="margin:0;">Frekuensi pencatatan otomatis</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <select id="cfg-log-interval" class="form-input">
                    <option value="300">5 Menit</option>
                    <option value="900">15 Menit</option>
                    <option value="1800">30 Menit</option>
                    <option value="3600" selected>1 Jam</option>
                    <option value="7200">2 Jam</option>
                    <option value="10800">3 Jam</option>
                    <option value="21600">6 Jam</option>
                    <option value="43200">12 Jam</option>
                    <option value="86400">1 Hari (24 Jam)</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="saveSysConfig()" style="padding:0.5rem 1.25rem; white-space:nowrap;">Simpan</button>
        </div>
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
                <div class="form-hint" style="margin:0;">Jeda waktu antar penyiraman otomatis</div>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <select id="cfg-cooldown" class="form-input">
                    <option value="3600">1 Jam</option>
                    <option value="7200">2 Jam</option>
                    <option value="14400" selected>4 Jam</option>
                    <option value="21600">6 Jam</option>
                    <option value="43200">12 Jam</option>
                    <option value="86400">24 Jam</option>
                </select>
            </div>
            <button class="btn btn-primary" onclick="saveSysConfig()" style="padding:0.5rem 1.25rem; white-space:nowrap;">Simpan</button>
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
                <div class="form-hint" style="margin:0;">Unduh log sensor sebagai file .csv</div>
            </div>
        </div>
        <button class="btn-cta" onclick="exportLogCSV()" style="margin-top:0.25rem;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1rem;height:1rem;">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export CSV
        </button>
    </div>

    <!-- Hapus Log Data -->
    <div class="config-item" style="margin-bottom:2rem;">
        <div class="config-item-header">
            <div class="config-icon" style="background:#fdecea; color:#c0392b;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
            </div>
            <div>
                <div class="form-label" style="margin-bottom:0; color:#c0392b;">Hapus Data Log</div>
                <div class="form-hint" style="margin:0;">Menghapus semua riwayat log sensor (permanen)</div>
            </div>
        </div>
        <button class="btn btn-danger" onclick="resetLogData()" style="padding:0.5rem 1.25rem; white-space:nowrap; align-self:flex-start;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:0.9rem;height:0.9rem;">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Hapus Log
        </button>
    </div>

</div><!-- /tab-panel config -->

@endsection

@section('scripts')
<script>
    const NUM_ZONES = 5;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let currentSchedules = @json($deviceControl->schedules_json ?? []);

    // ============ RENDER ZONES ============
    function renderZones(zonesData = [], containerId = 'valve-container') {
        const container = document.getElementById(containerId);
        if (!container) return;
        let html = '';
        for (let i = 1; i <= NUM_ZONES; i++) {
            const zData = zonesData.find(z => z.zone_id === i) || { soil_moisture: '--', relay_status: false };
            const isRelayOn = !!zData.relay_status;
            html += `<div class="valve-card ${isRelayOn ? 'active-zone' : ''}">
                <div class="valve-card-info">
                    <div class="valve-name-row">
                        <div class="valve-name">Zona ${i}</div>
                        <span class="valve-mode-badge ${isRelayOn ? 'active' : ''}">${isRelayOn ? 'MENYIRAM (ON)' : 'IDLE (OFF)'}</span>
                    </div>
                    <div class="valve-desc">Sub-Blok Area Pembibitan ${i}</div>
                    <div class="valve-metrics"><div class="valve-metric">Soil: <span>${zData.soil_moisture !== undefined ? zData.soil_moisture : '--'}%</span></div></div>
                </div>
                <div class="valve-card-toggle">
                    <span class="valve-toggle-label">${isRelayOn ? 'ON' : 'OFF'}</span>
                    <label class="toggle-wrap"><input type="checkbox" class="toggle-input" ${isRelayOn ? 'checked' : ''} onchange="toggleRelay(${i}, this.checked)"><div class="toggle-track"></div></label>
                </div>
            </div>`;
        }
        container.innerHTML = html;
    }

    // ============ SYSTEM MODE ============
    function changeSystemMode() {
        const modeVal = parseInt(document.getElementById('systemModeSelect').value);
        document.getElementById('manualSettings').style.display = (modeVal === 1) ? 'flex' : 'none';
        document.getElementById('scheduleSettings').style.display = (modeVal === 2) ? 'flex' : 'none';
        const manualType = parseInt(document.getElementById('cfg-manual-mode').value);
        const manualDur = parseInt(document.getElementById('cfg-manual-dur').value);
        fetch("{{ route('dashboard.update-mode') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ system_mode: modeVal, manual_mode_type: manualType, manual_duration: manualDur })
        }).then(r => r.json()).then(data => { if (data.status === 'success') { updateAuditNotice(data.updated_by, data.updated_at); fetchStatus(); }}).catch(console.error);
    }
    function updateManualConfig() { changeSystemMode(); }

    // ============ TOGGLE RELAY ============
    function toggleRelay(zoneId, status) {
        fetch("{{ route('dashboard.toggle-relay') }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ zone_id: zoneId, relay_status: status })
        }).then(r => r.json()).then(data => { if (data.status === 'success') { updateAuditNotice(data.updated_by, data.updated_at); fetchStatus(); }}).catch(console.error);
    }

    // ============ SCHEDULE ============
    function renderScheduleConfig() {
        const container = document.getElementById('schedule-container');
        if (!container) return;
        let html = '';
        for (let i = 0; i < NUM_ZONES; i++) {
            let slotsHtml = '';
            for (let j = 0; j < 3; j++) {
                slotsHtml += `<div id="sch-row-${i}-${j}" style="display:${j===0?'flex':'none'}; align-items:center; gap:0.5rem;" class="schedule-slot">
                    <label class="toggle-wrap" style="transform:scale(0.8);"><input type="checkbox" class="toggle-input" id="sch-active-${i}-${j}"><div class="toggle-track"></div></label>
                    <input type="time" id="sch-time-${i}-${j}" class="form-input" style="padding:0.3rem 0.5rem; width:auto; font-size:0.8rem;">
                    <input type="number" id="sch-dur-${i}-${j}" min="1" max="3600" placeholder="Durasi (dtk)" class="form-input" style="width:5.5rem; padding:0.3rem 0.5rem; font-size:0.8rem;">
                </div>`;
            }
            html += `<div class="schedule-zone">
                <div class="schedule-zone-header">
                    <div class="schedule-zone-title"><div class="zone-dot"></div>Zona ${i+1}</div>
                    <select id="sch-count-${i}" onchange="updateScheduleCount(${i})" class="form-input" style="width:auto; padding:0.3rem 0.5rem; font-size:0.75rem; font-weight:600;">
                        <option value="1">1x Sehari</option><option value="2">2x Sehari</option><option value="3">3x Sehari</option>
                    </select>
                </div>
                <div id="sch-slots-${i}" style="display:flex; flex-direction:column; gap:0.375rem;">${slotsHtml}</div>
            </div>`;
        }
        container.innerHTML = html;
        populateScheduleInputs(currentSchedules);
    }
    function updateScheduleCount(zoneIndex) {
        const count = parseInt(document.getElementById('sch-count-' + zoneIndex).value);
        for (let j = 0; j < 3; j++) { document.getElementById('sch-row-'+zoneIndex+'-'+j).style.display = (j<count)?'flex':'none'; }
    }
    function populateScheduleInputs(data) {
        if (!data || !Array.isArray(data)) return;
        data.forEach((z, i) => {
            let activeCount = 1;
            (z.schedules || []).forEach((s, j) => {
                if (j >= 3) return;
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
    function saveSchedule() {
        const payload = [];
        for (let i = 0; i < NUM_ZONES; i++) {
            const count = parseInt(document.getElementById('sch-count-'+i).value);
            const schedules = [];
            for (let j = 0; j < 3; j++) {
                if (j < count) {
                    const tv = document.getElementById('sch-time-'+i+'-'+j).value;
                    const dv = document.getElementById('sch-dur-'+i+'-'+j).value;
                    let hour=null, minute=null;
                    if (tv) { const p=tv.split(':'); hour=parseInt(p[0]); minute=parseInt(p[1]); }
                    schedules.push({ active: document.getElementById('sch-active-'+i+'-'+j).checked, hour, minute, duration: dv===''?null:parseInt(dv) });
                } else { schedules.push({ active:false, hour:0, minute:0, duration:10 }); }
            }
            payload.push({ schedules });
        }
        fetch("{{ route('dashboard.save-schedule') }}", {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken}, body:JSON.stringify({schedules:payload})
        }).then(r=>r.json()).then(data=>{ if(data.status==='success'){alert(data.message);updateAuditNotice(data.updated_by,data.updated_at);fetchStatus();}}).catch(()=>alert('Gagal menyimpan jadwal.'));
    }

    // ============ AUDIT NOTICE ============
    function updateAuditNotice(userName, timeStr) {
        const nameEl = document.getElementById('audit-user-name');
        const timeEl = document.getElementById('audit-user-time');
        if (nameEl) nameEl.innerText = userName;
        if (timeEl) timeEl.innerText = timeStr;
    }

    // ============ FETCH LIVE STATUS ============
    function fetchStatus() {
        fetch("{{ route('dashboard.status') }}")
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.latest_sensor) {
                        document.getElementById('temp').innerText = parseFloat(data.latest_sensor.temperature).toFixed(1);
                        document.getElementById('hum').innerText = parseFloat(data.latest_sensor.humidity).toFixed(1);
                        document.getElementById('light').innerText = parseInt(data.latest_sensor.light_level);
                        document.getElementById('timestamp').innerText = data.latest_sensor.recorded_at;
                        if (data.latest_sensor.zones && data.latest_sensor.zones.length > 0) {
                            const avg = data.latest_sensor.zones.reduce((s,z)=>s+z.soil_moisture,0)/data.latest_sensor.zones.length;
                            document.getElementById('soil').innerText = Math.round(avg);
                            renderZones(data.latest_sensor.zones,'valve-container');
                            renderZones(data.latest_sensor.zones,'valve-container-ctrl');
                        }
                    } else { renderZones([],'valve-container'); renderZones([],'valve-container-ctrl'); }
                    if (data.device_control) {
                        updateAuditNotice(data.device_control.updated_by, data.device_control.updated_at_exact);
                        document.getElementById('irrig-time').innerText = data.device_control.manual_duration;
                        const badge = document.getElementById('irrig-mode-badge');
                        const m = data.device_control.system_mode;
                        if (m===1){badge.innerText='MANUAL';badge.className='mode-badge manual';}
                        else if (m===2){badge.innerText='SCHEDULE';badge.className='mode-badge schedule';}
                        else{badge.innerText='AUTO (FUZZY)';badge.className='mode-badge active';}
                    }
                    if (data.recent_logs && data.recent_logs.length > 0) {
                        document.getElementById('audit-log-body').innerHTML = data.recent_logs.map(l=>`<tr><td>${l.time_exact}</td><td><strong>${l.user}</strong></td><td>${l.action}</td></tr>`).join('');
                    }
                }
            }).catch(err => console.error('Fetch error:', err));
    }

    // ============ FUZZY CONFIG ============
    const fuzzyFields = ['basahMax','basahMin','lembabLow','lembabMid','lembabHigh','keringLow','keringMax',
        'dinginMax','dinginMin','normalLow','normalMid','normalHigh','panasLow','panasMax',
        'redupMax','redupMin','sedangLow','sedangMid','sedangHigh','terangLow','terangMax','pendekMax','sedangOutMax','defuzzMax'];
    const defaultFuzzy = {basahMax:30,basahMin:50,lembabLow:30,lembabMid:55,lembabHigh:80,keringLow:60,keringMax:80,
        dinginMax:20,dinginMin:25,normalLow:22,normalMid:27,normalHigh:32,panasLow:30,panasMax:35,
        redupMax:5000,redupMin:25000,sedangLow:5000,sedangMid:35000,sedangHigh:65000,terangLow:45000,terangMax:65000,
        pendekMax:10,sedangOutMax:20,defuzzMax:30};
    fuzzyFields.forEach(f => { const el=document.getElementById('fz-'+f); if(el) el.placeholder=defaultFuzzy[f]; });

    function toggleFuzzyPanel() {
        const panel = document.getElementById('fuzzy-panel');
        const icon = document.getElementById('fuzzy-toggle-icon');
        if (panel.style.display === 'none') { panel.style.display='block'; icon.innerText='▲'; fetchFuzzyConfig(); }
        else { panel.style.display='none'; icon.innerText='▼'; }
    }
    function fetchFuzzyConfig() {
        fetch("{{ route('dashboard.fuzzy-config') }}")
            .then(r=>r.json())
            .then(c=>{ fuzzyFields.forEach(f=>{ const el=document.getElementById('fz-'+f); if(el&&c[f]!==undefined) el.value=c[f]; }); })
            .catch(()=>{});
    }
    function saveFuzzyConfig() {
        const payload={};
        fuzzyFields.forEach(f=>{ const el=document.getElementById('fz-'+f); if(el&&el.value!=='') payload[f]=parseFloat(el.value); });
        fetch("{{ route('dashboard.fuzzy-config.save') }}", {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify(payload)})
            .then(r=>r.json()).then(d=>{ if(d.status==='success'){alert('Konfigurasi Fuzzy berhasil disimpan!');fetchFuzzyConfig();}else alert('Gagal menyimpan.'); })
            .catch(()=>alert('Gagal menyimpan konfigurasi fuzzy.'));
    }
    function resetFuzzyConfig() {
        if(!confirm('Reset semua parameter fuzzy ke nilai default?')) return;
        fetch("{{ route('dashboard.fuzzy-config.reset') }}", {method:'POST',headers:{'X-CSRF-TOKEN':csrfToken}})
            .then(r=>r.json()).then(d=>{ if(d.status==='success'){alert('Reset ke default.');fetchFuzzyConfig();}else alert('Gagal reset.'); })
            .catch(()=>alert('Gagal reset.'));
    }

    // ============ SYS CONFIG ============
    function saveSysConfig() {
        const payload = {
            sensor_interval: parseInt(document.getElementById('cfg-interval').value)||10,
            log_interval: parseInt(document.getElementById('cfg-log-interval').value),
            cooldown: parseInt(document.getElementById('cfg-cooldown').value)
        };
        fetch("{{ route('dashboard.sys-config') }}", {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrfToken},body:JSON.stringify(payload)})
            .then(r=>r.json()).then(d=>{ if(d.status==='success') alert('Konfigurasi sistem berhasil disimpan!'); else alert('Gagal menyimpan.'); })
            .catch(()=>alert('Gagal menyimpan konfigurasi.'));
    }
    function exportLogCSV() { window.location.href = "{{ route('dashboard.export-csv') }}"; }
    function resetLogData() {
        if(!confirm('Hapus semua data log sensor? Tindakan ini tidak dapat dibatalkan.')) return;
        fetch("{{ route('dashboard.reset-log') }}", {method:'POST',headers:{'X-CSRF-TOKEN':csrfToken}})
            .then(r=>r.json()).then(d=>{ if(d.status==='success') alert('Data log berhasil dihapus!'); else alert('Gagal menghapus.'); })
            .catch(()=>alert('Terjadi kesalahan.'));
    }

    // ============ INIT ============
    renderScheduleConfig();
    renderZones([], 'valve-container');
    renderZones([], 'valve-container-ctrl');
    fetchStatus();
    setInterval(fetchStatus, 3000);
</script>
@endsection
