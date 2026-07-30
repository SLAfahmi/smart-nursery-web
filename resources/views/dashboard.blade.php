@extends('layouts.app')

@section('title', 'Dashboard - Smart Nursery')

@section('content')

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

    <!-- Irrigation Status -->
    <div class="section-label">Sistem Irigasi & Kontrol Mode</div>
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

    <!-- Audit Log User Activity History -->
    <div class="audit-table-card">
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

@endsection

@section('scripts')
<script>
    const NUM_ZONES = 5;
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    let currentSchedules = @json($deviceControl->schedules_json ?? []);

    // Render 5 Zones Cards
    function renderZones(zonesData = []) {
        const container = document.getElementById('valve-container');
        let html = '';

        for (let i = 1; i <= NUM_ZONES; i++) {
            const zData = zonesData.find(z => z.zone_id === i) || { soil_moisture: '--', relay_status: false };
            const isRelayOn = !!zData.relay_status;
            
            html += `
            <div class="valve-card ${isRelayOn ? 'active-zone' : ''}">
                <div class="valve-card-info">
                    <div class="valve-name-row">
                        <div class="valve-name">Zona ${i}</div>
                        <span class="valve-mode-badge ${isRelayOn ? 'active' : ''}">
                            ${isRelayOn ? 'MENYIRAM (ON)' : 'IDLE (OFF)'}
                        </span>
                    </div>
                    <div class="valve-desc">Sub-Blok Area Pembibitan ${i}</div>
                    <div class="valve-metrics">
                        <div class="valve-metric">Soil: <span>${zData.soil_moisture !== undefined ? zData.soil_moisture : '--'}%</span></div>
                    </div>
                </div>
                <div class="valve-card-toggle">
                    <span class="valve-toggle-label">${isRelayOn ? 'ON' : 'OFF'}</span>
                    <label class="toggle-wrap">
                        <input type="checkbox" class="toggle-input" ${isRelayOn ? 'checked' : ''} onchange="toggleRelay(${i}, this.checked)">
                        <div class="toggle-track"></div>
                    </label>
                </div>
            </div>`;
        }

        container.innerHTML = html;
    }

    // Change System Mode (Auto Fuzzy / Manual / Schedule)
    function changeSystemMode() {
        const select = document.getElementById('systemModeSelect');
        const modeVal = parseInt(select.value);

        const manualDiv = document.getElementById('manualSettings');
        const scheduleDiv = document.getElementById('scheduleSettings');

        manualDiv.style.display = (modeVal === 1) ? 'flex' : 'none';
        scheduleDiv.style.display = (modeVal === 2) ? 'flex' : 'none';

        const manualType = parseInt(document.getElementById('cfg-manual-mode').value);
        const manualDur = parseInt(document.getElementById('cfg-manual-dur').value);

        fetch("{{ route('dashboard.update-mode') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                system_mode: modeVal,
                manual_mode_type: manualType,
                manual_duration: manualDur
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                updateAuditNotice(data.updated_by, data.updated_at);
                fetchStatus();
            }
        })
        .catch(err => console.error(err));
    }

    function updateManualConfig() {
        changeSystemMode();
    }

    // Toggle Relay per Zone
    function toggleRelay(zoneId, status) {
        fetch("{{ route('dashboard.toggle-relay') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                zone_id: zoneId,
                relay_status: status
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                updateAuditNotice(data.updated_by, data.updated_at);
                fetchStatus();
            }
        })
        .catch(err => console.error(err));
    }

    // Render Schedule Config Inputs
    function renderScheduleConfig() {
        const container = document.getElementById('schedule-container');
        let html = '';

        for (let i = 0; i < NUM_ZONES; i++) {
            let slotsHtml = '';
            for (let j = 0; j < 3; j++) {
                slotsHtml += `
                <div id="sch-row-${i}-${j}" style="display:${j === 0 ? 'flex' : 'none'}; align-items:center; gap:0.5rem;" class="schedule-slot">
                    <label class="toggle-wrap" style="transform:scale(0.8);">
                        <input type="checkbox" class="toggle-input" id="sch-active-${i}-${j}">
                        <div class="toggle-track"></div>
                    </label>
                    <input type="time" id="sch-time-${i}-${j}" class="form-input" style="padding:0.3rem 0.5rem; width: auto; font-size:0.8rem;">
                    <input type="number" id="sch-dur-${i}-${j}" min="1" max="3600" placeholder="Durasi (dtk)" class="form-input" style="width:5.5rem; padding:0.3rem 0.5rem; font-size:0.8rem;">
                </div>`;
            }
            html += `
            <div class="schedule-zone">
                <div class="schedule-zone-header">
                    <div class="schedule-zone-title">
                        <div class="zone-dot"></div>
                        Zona ${i + 1}
                    </div>
                    <select id="sch-count-${i}" onchange="updateScheduleCount(${i})" class="form-input" style="width:auto; padding:0.3rem 0.5rem; font-size:0.75rem; font-weight:600;">
                        <option value="1">1x Sehari</option>
                        <option value="2">2x Sehari</option>
                        <option value="3">3x Sehari</option>
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
        for (let j = 0; j < 3; j++) {
            document.getElementById('sch-row-' + zoneIndex + '-' + j).style.display = (j < count) ? 'flex' : 'none';
        }
    }

    function populateScheduleInputs(data) {
        if (!data || !Array.isArray(data)) return;
        data.forEach((z, i) => {
            let activeCount = 1;
            if (z.schedules && z.schedules.length > 0) {
                z.schedules.forEach((s, j) => {
                    if (j >= 3) return;
                    const activeEl = document.getElementById('sch-active-' + i + '-' + j);
                    const timeEl = document.getElementById('sch-time-' + i + '-' + j);
                    const durEl = document.getElementById('sch-dur-' + i + '-' + j);

                    if (activeEl) activeEl.checked = !!s.active;
                    if (s.active && j >= activeCount) activeCount = j + 1;

                    if (timeEl) {
                        if (s.hour !== null && s.minute !== null && s.hour !== undefined && s.minute !== undefined) {
                            timeEl.value = String(s.hour).padStart(2, '0') + ':' + String(s.minute).padStart(2, '0');
                        } else {
                            timeEl.value = '';
                        }
                    }
                    if (durEl) durEl.value = s.duration !== null ? s.duration : '';
                });
            }
            const countEl = document.getElementById('sch-count-' + i);
            if (countEl) {
                countEl.value = activeCount;
                updateScheduleCount(i);
            }
        });
    }

    function saveSchedule() {
        const payload = [];
        for (let i = 0; i < NUM_ZONES; i++) {
            const count = parseInt(document.getElementById('sch-count-' + i).value);
            const schedules = [];
            for (let j = 0; j < 3; j++) {
                if (j < count) {
                    const timeValue = document.getElementById('sch-time-' + i + '-' + j).value;
                    const durationValue = document.getElementById('sch-dur-' + i + '-' + j).value;
                    let hour = null, minute = null;
                    if (timeValue) {
                        const parts = timeValue.split(':');
                        hour = parseInt(parts[0]);
                        minute = parseInt(parts[1]);
                    }
                    schedules.push({
                        active: document.getElementById('sch-active-' + i + '-' + j).checked,
                        hour: hour, minute: minute,
                        duration: durationValue === '' ? null : parseInt(durationValue)
                    });
                } else {
                    schedules.push({ active: false, hour: 0, minute: 0, duration: 10 });
                }
            }
            payload.push({ schedules: schedules });
        }

        fetch("{{ route('dashboard.save-schedule') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ schedules: payload })
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message);
                updateAuditNotice(data.updated_by, data.updated_at);
                fetchStatus();
            }
        })
        .catch(() => alert('Gagal menyimpan jadwal.'));
    }

    function updateAuditNotice(userName, timeStr) {
        document.getElementById('audit-user-name').innerText = userName;
        document.getElementById('audit-user-time').innerText = timeStr;
    }

    // Fetch Live Dashboard Status via AJAX
    function fetchStatus() {
        fetch("{{ route('dashboard.status') }}")
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update sensor cards
                    if (data.latest_sensor) {
                        document.getElementById('temp').innerText = parseFloat(data.latest_sensor.temperature).toFixed(1);
                        document.getElementById('hum').innerText = parseFloat(data.latest_sensor.humidity).toFixed(1);
                        document.getElementById('light').innerText = parseInt(data.latest_sensor.light_level);
                        document.getElementById('timestamp').innerText = data.latest_sensor.recorded_at;

                        if (data.latest_sensor.zones && data.latest_sensor.zones.length > 0) {
                            const avgSoil = data.latest_sensor.zones.reduce((sum, z) => sum + z.soil_moisture, 0) / data.latest_sensor.zones.length;
                            document.getElementById('soil').innerText = Math.round(avgSoil);
                            renderZones(data.latest_sensor.zones);
                        }
                    } else {
                        renderZones([]);
                    }

                    // Update Audit Notice
                    if (data.device_control) {
                        updateAuditNotice(data.device_control.updated_by, data.device_control.updated_at_exact);
                        document.getElementById('irrig-time').innerText = data.device_control.manual_duration;
                        
                        const badge = document.getElementById('irrig-mode-badge');
                        const sysMode = data.device_control.system_mode;
                        if (sysMode === 1) {
                            badge.innerText = 'MANUAL';
                            badge.className = 'mode-badge manual';
                        } else if (sysMode === 2) {
                            badge.innerText = 'SCHEDULE';
                            badge.className = 'mode-badge schedule';
                        } else {
                            badge.innerText = 'AUTO (FUZZY)';
                            badge.className = 'mode-badge active';
                        }
                    }

                    // Update Audit Logs Table
                    if (data.recent_logs && data.recent_logs.length > 0) {
                        let logHtml = '';
                        data.recent_logs.forEach(log => {
                            logHtml += `
                            <tr>
                                <td>${log.time_exact}</td>
                                <td><strong>${log.user}</strong></td>
                                <td>${log.action}</td>
                            </tr>`;
                        });
                        document.getElementById('audit-log-body').innerHTML = logHtml;
                    }
                }
            })
            .catch(err => console.error('Fetch status error:', err));
    }

    // Init Page
    renderScheduleConfig();
    renderZones([]);
    fetchStatus();
    setInterval(fetchStatus, 3000);
</script>
@endsection
