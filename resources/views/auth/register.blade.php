<!DOCTYPE html>
<html lang="id" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi Akun - Smart Nursery</title>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ $cssVersion }}">
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
                <h2 class="auth-title">Daftar Akun Baru</h2>
                <p class="auth-sub">Buat akun untuk mencatat setiap aktivitas pengoperasian nursery</p>
            </div>

            @if ($errors->any())
                <div class="alert-error" style="margin-bottom: 1rem;">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('register') }}" method="POST" class="auth-form">
                @csrf
                <div>
                    <label for="registration_code" class="form-label">Kode Pendaftaran</label>
                    <input type="text" id="registration_code" name="registration_code" class="form-input" placeholder="Kode dari admin"
                        maxlength="255" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" required autofocus>
                    <p class="form-hint">Minta kode ini ke admin nursery.</p>
                </div>

                <div>
                    <label for="name" class="form-label">Nama Lengkap</label>
                    <input type="text" id="name" name="name" class="form-input" placeholder="Budi Santoso" value="{{ old('name') }}" maxlength="100" autocomplete="name" required>
                </div>

                <div>
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="{{ 'budi@' . \App\Rules\AllowedEmailDomain::domain() }}" value="{{ old('email') }}"
                        maxlength="255" pattern="{{ \App\Rules\AllowedEmailDomain::htmlPattern() }}" title="{{ 'Gunakan alamat @' . \App\Rules\AllowedEmailDomain::domain() }}"
                        autocomplete="email" autocapitalize="none" spellcheck="false" required>
                </div>

                <div>
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Minimal 8 karakter" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" placeholder="••••••••" minlength="8" maxlength="72" autocomplete="new-password" required>
                </div>

                <button type="submit" class="btn-cta">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1.1rem;height:1.1rem;">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <line x1="20" y1="8" x2="20" y2="14"/>
                        <line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                    Daftar Akun
                </button>
            </form>

            <div class="auth-footer">
                Sudah punya akun? <a href="{{ route('login') }}" class="auth-link">Masuk Di Sini</a>
            </div>
        </div>
    </div>
</body>

</html>
