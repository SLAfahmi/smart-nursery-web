<?php

use App\Http\Controllers\Api\TelemetryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API Routes v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::post('/telemetry', [TelemetryController::class, 'store']);
    Route::post('/telemetry/sync-batch', [TelemetryController::class, 'syncBatch']);
    Route::get('/device-command', [TelemetryController::class, 'getCommand']);
});
