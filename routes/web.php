<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    Route::get('/', [DashboardController::class, 'index'])->name('home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    Route::get('/api/dashboard/status', [DashboardController::class, 'getStatus'])->name('dashboard.status');
    Route::post('/api/update-mode', [DashboardController::class, 'updateMode'])->name('dashboard.update-mode');
    Route::post('/api/toggle-relay', [DashboardController::class, 'toggleRelay'])->name('dashboard.toggle-relay');
    Route::post('/api/save-schedule', [DashboardController::class, 'saveSchedule'])->name('dashboard.save-schedule');
});
