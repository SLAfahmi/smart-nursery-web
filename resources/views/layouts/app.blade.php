<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Smart Nursery Monitor')</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body class="@auth has-bottom-nav @endauth">

    <!-- ============ HEADER ============ -->
    <header class="app-header">
        <div class="app-header-brand">
            <svg class="brand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22V12M12 12C12 7 7 3 2 3c0 5 4 9 10 9zM12 12c0-5 5-9 10-9-1 5-5 9-10 9z" />
            </svg>
            <h1>Smart Nursery</h1>
        </div>
        <div class="header-actions">
            @auth
                <div class="user-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:0.9rem;height:0.9rem;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span class="user-name" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</span>
                    <form action="{{ route('logout') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="logout-btn" title="Logout">Logout</button>
                    </form>
                </div>
            @endauth

            <button class="theme-btn" id="theme-btn" onclick="toggleTheme()" title="Ganti Tema">
                <svg id="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round" style="display:none">
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
                <svg id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            </button>
            
            @auth
            <div class="live-badge offline" id="conn-badge" title="Status koneksi alat">
                <div class="live-dot">
                    <div class="live-dot-ping"></div>
                    <div class="live-dot-inner"></div>
                </div>
                <span id="conn-text">Memuat...</span>
            </div>
            @endauth
        </div>
    </header>

    <!-- ============ APP SHELL (sidebar + content) ============ -->
    <div class="app-shell">

        <!-- DESKTOP SIDEBAR -->
        @auth
        <aside class="sidebar" id="sidebar" role="navigation" aria-label="Menu Utama">
            <nav class="sidebar-nav">
                <button class="sidebar-item active" data-tab="tab-dashboard" onclick="switchTab('tab-dashboard', this)" aria-current="page" aria-label="Dashboard" title="Dashboard">
                    <span class="sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"/>
                            <rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/>
                            <rect x="14" y="14" width="7" height="7" rx="1"/>
                        </svg>
                    </span>
                    <span class="sidebar-label">Dashboard</span>
                </button>

                <button class="sidebar-item" data-tab="tab-control" onclick="switchTab('tab-control', this)" aria-label="Kontrol" title="Kontrol">
                    <span class="sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/>
                            <line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/>
                            <line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/>
                            <line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>
                        </svg>
                    </span>
                    <span class="sidebar-label">Kontrol</span>
                </button>

                <button class="sidebar-item" data-tab="tab-log" onclick="switchTab('tab-log', this)" aria-label="Log" title="Log">
                    <span class="sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                            <line x1="16" y1="13" x2="8" y2="13"/>
                            <line x1="16" y1="17" x2="8" y2="17"/>
                            <polyline points="10 9 9 9 8 9"/>
                        </svg>
                    </span>
                    <span class="sidebar-label">Log</span>
                </button>

                <button class="sidebar-item" data-tab="tab-config" onclick="switchTab('tab-config', this)" aria-label="Config" title="Config">
                    <span class="sidebar-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                        </svg>
                    </span>
                    <span class="sidebar-label">Config</span>
                </button>
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-version">v1.0 · Smart Nursery</div>
            </div>
        </aside>
        @endauth

        <!-- MAIN CONTENT AREA -->
        <main class="main-content" id="main-content">
            @yield('content')
        </main>

    </div><!-- /app-shell -->

    <!-- ============ MOBILE BOTTOM TAB BAR ============ -->
    @auth
    <nav class="bottom-nav" id="bottom-nav" aria-label="Navigasi Tab">
        <button class="bottom-nav-item active" data-tab="tab-dashboard" onclick="switchTab('tab-dashboard', this)">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1"/>
                    <rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/>
                    <rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
            </span>
            <span class="bottom-nav-label">Dashboard</span>
        </button>

        <button class="bottom-nav-item" data-tab="tab-control" onclick="switchTab('tab-control', this)">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/>
                    <line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/>
                    <line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/>
                    <line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>
                </svg>
            </span>
            <span class="bottom-nav-label">Kontrol</span>
        </button>

        <button class="bottom-nav-item" data-tab="tab-log" onclick="switchTab('tab-log', this)">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <polyline points="10 9 9 9 8 9"/>
                </svg>
            </span>
            <span class="bottom-nav-label">Log</span>
        </button>

        <button class="bottom-nav-item" data-tab="tab-config" onclick="switchTab('tab-config', this)">
            <span class="bottom-nav-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                </svg>
            </span>
            <span class="bottom-nav-label">Config</span>
        </button>
    </nav>

    <!-- Notifikasi singkat (sama seperti dashboard lokal firmware). Di luar <main>:
         container query di .main-content membuat position:fixed relatif ke main. -->
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
    @endauth

    <script>
        // ---- TOAST ----
        let toastTimer = null;
        function toast(message, type = 'ok') {
            const el = document.getElementById('toast');
            el.textContent = message;
            el.className = 'toast show ' + type;
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { el.className = 'toast'; }, type === 'error' ? 5000 : 3000);
        }

        // ---- TAB SWITCHING ----
        function switchTab(tabId, clickedEl) {
            // Update all tab panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.toggle('tab-panel--active', panel.id === tabId);
            });

            // Update sidebar items
            document.querySelectorAll('.sidebar-item').forEach(item => {
                const isActive = item.dataset.tab === tabId;
                item.classList.toggle('active', isActive);
                item.setAttribute('aria-current', isActive ? 'page' : 'false');
            });

            // Update bottom nav items
            document.querySelectorAll('.bottom-nav-item').forEach(item => {
                item.classList.toggle('active', item.dataset.tab === tabId);
            });

            // Persist active tab
            localStorage.setItem('activeTab', tabId);
        }

        // Restore last active tab on page load
        document.addEventListener('DOMContentLoaded', function() {
            const savedTab = localStorage.getItem('activeTab') || 'tab-dashboard';
            const targetPanel = document.getElementById(savedTab);
            if (targetPanel) {
                switchTab(savedTab, null);
            }
        });

        // ---- THEME TOGGLE ----
        function toggleTheme() {
            const html = document.documentElement;
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);

            const sunIcon = document.getElementById('icon-sun');
            const moonIcon = document.getElementById('icon-moon');
            if (newTheme === 'dark') {
                sunIcon.style.display = 'block';
                moonIcon.style.display = 'none';
            } else {
                sunIcon.style.display = 'none';
                moonIcon.style.display = 'block';
            }

            localStorage.setItem('theme', newTheme);
        }

        // Restore saved theme
        (function() {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) {
                document.documentElement.setAttribute('data-theme', savedTheme);
                if (savedTheme === 'dark') {
                    document.addEventListener('DOMContentLoaded', () => {
                        const sunIcon = document.getElementById('icon-sun');
                        const moonIcon = document.getElementById('icon-moon');
                        if (sunIcon && moonIcon) {
                            sunIcon.style.display = 'block';
                            moonIcon.style.display = 'none';
                        }
                    });
                }
            }
        })();
    </script>
    @yield('scripts')
</body>

</html>
