<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FirmwareController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    // Hanya aktif jika ALLOW_REGISTRATION=true (dicek di AuthController)
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::get('/api/dashboard/status', [DashboardController::class, 'getStatus'])->name('dashboard.status');
    Route::post('/api/update-mode', [DashboardController::class, 'updateMode'])->name('dashboard.update-mode');
    Route::post('/api/toggle-relay', [DashboardController::class, 'toggleRelay'])->name('dashboard.toggle-relay');
    Route::post('/api/save-schedule', [DashboardController::class, 'saveSchedule'])->name('dashboard.save-schedule');

    // Config routes (tab Config, mengikuti halaman Config dashboard lokal firmware)
    Route::post('/api/sys-config', [DashboardController::class, 'saveSysConfig'])->name('dashboard.sys-config');
    Route::get('/api/export-csv', [DashboardController::class, 'exportCsv'])->name('dashboard.export-csv');
    Route::post('/api/reset-log', [DashboardController::class, 'resetLog'])->name('dashboard.reset-log');

    // Update firmware OTA (kartu di tab Config)
    Route::post('/api/firmware', [FirmwareController::class, 'upload'])->name('firmware.upload');
    Route::post('/api/firmware/{release}/cancel', [FirmwareController::class, 'cancel'])->name('firmware.cancel');
});
