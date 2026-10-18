<?php

use App\Http\Controllers\Api\TelemetryController;
use App\Http\Middleware\VerifyDeviceToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API Routes v1 (ESP32)
|--------------------------------------------------------------------------
| Firmware mengirim telemetry tiap 5 detik (±12 request/menit).
*/

Route::prefix('v1')->middleware(['throttle:60,1', VerifyDeviceToken::class])->group(function () {
    Route::post('/telemetry', [TelemetryController::class, 'store']);
    Route::post('/telemetry/sync-batch', [TelemetryController::class, 'syncBatch']);
    Route::get('/device-command', [TelemetryController::class, 'getCommand']);
});
