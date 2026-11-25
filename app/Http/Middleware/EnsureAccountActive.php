<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengeluarkan user yang aksesnya dicabut (`php artisan nursery:user <email> --cabut`)
 * pada request berikutnya, termasuk yang masuk lewat "Remember me".
 */
class EnsureAccountActive
{
    public const MESSAGE = 'Akun ini sudah dinonaktifkan. Hubungi admin nursery.';

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::user()?->isDisabled()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Polling dashboard (fetch JSON) mengarahkan ke halaman login saat menerima 401
            if ($request->expectsJson()) {
                return response()->json(['status' => 'error', 'message' => self::MESSAGE], 401);
            }

            return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
