<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAccountActive;
use App\Models\ActivityLog;
use App\Models\User;
use App\Rules\AllowedEmailDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->merge(['email' => AllowedEmailDomain::normalize($request->input('email'))]);

        $credentials = $request->validate([
            'email' => AllowedEmailDomain::rules(),
            'password' => ['required', 'string', 'max:255'],
        ]);

        // Callback hanya dipanggil Laravel jika password benar, jadi pesan "dinonaktifkan"
        // tidak membocorkan status akun ke orang yang tidak tahu passwordnya.
        $disabledUser = null;
        $loggedIn = Auth::attemptWhen($credentials, function (User $user) use (&$disabledUser) {
            if ($user->isDisabled()) {
                $disabledUser = $user;
                return false;
            }
            return true;
        }, $request->boolean('remember'));

        if ($disabledUser) {
            ActivityLog::create([
                'user_id' => $disabledUser->id,
                'action' => 'Login Ditolak (Akun Nonaktif)',
                'details_json' => ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            ]);

            return back()->withErrors(['email' => EnsureAccountActive::MESSAGE])->onlyInput('email');
        }

        if ($loggedIn) {
            $request->session()->regenerate();

            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Login Ke Sistem',
                'details_json' => ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
            ]);

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Email atau password yang Anda masukkan salah.',
        ])->onlyInput('email');
    }

    public function showRegisterForm()
    {
        abort_unless(self::registrationOpen(), 404);

        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        abort_unless(self::registrationOpen(), 404);

        // Kode dicek lebih dulu dan terpisah: tanpa kode yang benar, orang luar tidak
        // mendapat info apa pun (mis. apakah sebuah email sudah terdaftar).
        $request->validate([
            'registration_code' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                if (!is_string($value) || !hash_equals((string) config('nursery.registration_code'), $value)) {
                    $fail('Kode pendaftaran salah. Minta kode ke admin nursery.');
                }
            }],
        ], [
            'registration_code.required' => 'Kode pendaftaran wajib diisi. Minta kode ke admin nursery.',
        ]);

        $request->merge(['email' => AllowedEmailDomain::normalize($request->input('email'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [...AllowedEmailDomain::rules(), 'unique:users'],
            // Minimal sama dengan `nursery:user`; bcrypt hanya memakai 72 byte pertama
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'Registrasi Akun Baru',
            'details_json' => ['email' => $user->email, 'ip' => $request->ip()],
        ]);

        return redirect()->route('dashboard');
    }

    /** Pendaftaran terbuka hanya jika REGISTRATION_CODE di .env valid (lihat config/nursery.php) */
    public static function registrationOpen(): bool
    {
        $code = config('nursery.registration_code');
        return is_string($code) && $code !== '';
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'Logout Dari Sistem',
            ]);

            Auth::logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
