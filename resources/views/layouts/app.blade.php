<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Smart Nursery Monitor')</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body>

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
                    <span>{{ Auth::user()->name }}</span>
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
                    <path
                        d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
                <svg id="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            </button>
            
            <div class="live-badge">
                <div class="live-dot">
                    <div class="live-dot-ping"></div>
                    <div class="live-dot-inner"></div>
                </div>
                Live
            </div>
        </div>
    </header>

    <main class="main-content">
        @yield('content')
    </main>

    <script>
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
