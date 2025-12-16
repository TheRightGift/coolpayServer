<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\DashboardController;

Route::get('/', function () {
    return view('home');
});

Route::prefix(('auth'))->group(function () {
    Route::post('login', [LoginController::class, 'login']);
    Route::post('/logout', [LogoutController::class, 'logout'])->name('logout');

    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    });
});

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Route::get('/t/{key}', [TipController::class, 'showTippingPage'])->name('tipping.page');
// Route::get('/t/{key}/callback', [TipController::class, 'handleCallback'])->name('tipping.callback');
// Route::post('/paystack/webhook', [TipController::class, 'webhook'])->name('paystack.webhook');
