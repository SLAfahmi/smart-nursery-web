<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Nursery</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22V12M12 12C12 7 7 3 2 3c0 5 4 9 10 9zM12 12c0-5 5-9 10-9-1 5-5 9-10 9z" />
                    </svg>
                </div>
                <h2 class="auth-title">Smart Nursery</h2>
                <p class="auth-sub">Masuk untuk mengelola sistem irigasi & monitoring</p>
            </div>

            @if ($errors->any())
                <div class="alert-error" style="margin-bottom: 1rem;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login') }}" method="POST" class="auth-form">
                @csrf
                <div>
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="{{ 'nama@' . \App\Rules\AllowedEmailDomain::domain() }}" value="{{ old('email') }}"
                        maxlength="255" pattern="{{ \App\Rules\AllowedEmailDomain::htmlPattern() }}" title="{{ 'Gunakan alamat @' . \App\Rules\AllowedEmailDomain::domain() }}"
                        autocomplete="email" autocapitalize="none" spellcheck="false" required autofocus>
                </div>

                <div>
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" maxlength="255" autocomplete="current-password" required>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; color: var(--clr-on-surface-var);">
                        <input type="checkbox" name="remember" style="accent-color: var(--clr-primary);"> Remember me
                    </label>
                </div>

                <button type="submit" class="btn-cta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.1rem;height:1.1rem;">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    Masuk Ke System
                </button>
            </form>

            @if (config('nursery.allow_registration'))
                <div class="auth-footer">
                    Belum punya akun? <a href="{{ route('register') }}" class="auth-link">Daftar Akun Baru</a>
                </div>
            @endif
        </div>
    </div>
</body>

</html>
