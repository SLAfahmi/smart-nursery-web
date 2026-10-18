<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya ESP32 yang boleh memakai API perangkat. Firmware mengirim header
 * X-Device-Token berisi DEVICE_KEY (include/config.h), yang harus sama dengan
 * DEVICE_TOKEN di .env. Jika DEVICE_TOKEN belum diisi, semua request ditolak.
 */
class VerifyDeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('nursery.device_token');
        $given = $request->header('X-Device-Token');

        if (!is_string($expected) || $expected === '' || !is_string($given) || !hash_equals($expected, $given)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Device token tidak valid',
            ], 401);
        }

        return $next($request);
    }
}
