<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\FirmwareRelease;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * OTA cloud: rilis firmware dari tab Config, diambil alat lewat tawaran "ota" di balasan telemetry.
 */
class FirmwareController extends Controller
{
    /**
     * API alat (middleware VerifyDeviceToken). Firmware memverifikasi MD5 dari tawaran telemetry.
     * GET /api/v1/firmware/{release}/download
     */
    public function download(FirmwareRelease $release): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($release->path), 404);

        // BinaryFileResponse mengirim Content-Length (wajib bagi updater firmware)
        return response()->download(
            $disk->path($release->path),
            "smart-nursery-{$release->version}.bin",
            ['Content-Type' => 'application/octet-stream', 'x-MD5' => $release->md5]
        );
    }

    /** Web (auth): rilis firmware baru, rilis lama otomatis nonaktif */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            // Harus sama persis dengan FIRMWARE_VERSION di include/config.h firmware.
            // Hanya angka 0-9 bertitik. \z (bukan $) agar "1.0.2\n" tidak lolos, versi dipakai sebagai nama file.
            'version' => ['required', 'string', 'max:11', 'regex:/^\d{1,3}\.\d{1,3}\.\d{1,3}\z/', 'unique:firmware_releases,version'],
            'firmware' => 'required|file|extensions:bin|max:6400',   // KB, partisi app 6.25 MB
            'notes' => 'nullable|string|max:500',
        ], [
            'version.regex' => 'Versi harus angka dengan format X.Y.Z (mis. 1.0.2).',
            'version.max' => 'Versi harus angka dengan format X.Y.Z (mis. 1.0.2).',
            'version.unique' => 'Versi ini sudah pernah dirilis.',
            'firmware.extensions' => 'File firmware harus berformat .bin.',
            'firmware.max' => 'Ukuran file firmware maksimal 6,25 MB.',
            'notes.max' => 'Catatan maksimal 500 karakter.',
        ]);

        $file = $request->file('firmware');
        // Image aplikasi ESP32 selalu diawali magic byte 0xE9
        if (file_get_contents($file->getRealPath(), false, null, 0, 1) !== "\xE9") {
            return response()->json(['status' => 'error', 'message' => 'File bukan firmware ESP32 (.bin)'], 422);
        }

        $user = Auth::user();
        $release = DB::transaction(function () use ($validated, $file, $user) {
            FirmwareRelease::where('is_active', true)->update(['is_active' => false]);

            return FirmwareRelease::create([
                'version' => $validated['version'],
                'path' => $file->storeAs('firmware', "{$validated['version']}.bin", 'local'),
                'size' => $file->getSize(),
                'md5' => md5_file($file->getRealPath()),
                'notes' => $validated['notes'] ?? null,
                'is_active' => true,
                'uploaded_by_user_id' => $user->id,
            ]);
        });

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => "Merilis firmware {$release->version} (OTA)",
            'details_json' => ['size' => $release->size, 'md5' => $release->md5],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Firmware {$release->version} dirilis oleh {$user->name}",
        ]);
    }

    /** Web (auth): hentikan penawaran rilis. Alat yang sudah di status "ready" tetap restart ke versi itu. */
    public function cancel(FirmwareRelease $release): JsonResponse
    {
        $release->update(['is_active' => false]);

        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => "Membatalkan rilis firmware {$release->version}",
            'details_json' => ['release_id' => $release->id],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Rilis firmware {$release->version} dibatalkan",
        ]);
    }
}
