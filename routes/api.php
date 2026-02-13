<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\TwoFactorController;

Route::prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'apiLogin'])->middleware('throttle:30,1');
    Route::post('/logout', [LogoutController::class, 'apiLogout'])->name('Api-logout');
    Route::post('/register', [\App\Http\Controllers\RegisterController::class, 'register'])->middleware('throttle:30,1');
    Route::post('/forgot-password', [\App\Http\Controllers\ForgotPasswordController::class, 'sendResetLink'])->middleware('throttle:10,1');
    Route::post('/reset-password-token', [\App\Http\Controllers\ForgotPasswordController::class, 'resetWithToken'])->middleware('throttle:10,1');
});

Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::post('/auth/reset-password', [\App\Http\Controllers\PasswordController::class, 'reset']);

    // 2FA
    Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->middleware('throttle:10,1');
    Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->middleware('throttle:10,1');
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->middleware('throttle:20,1');

    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/wallet/refresh-balance', [WalletController::class, 'refreshBalance']);
    Route::get('/banks', [WalletController::class, 'getBanks']);

    // Payment links (QR generation)
    Route::post('/pay-links', [\App\Http\Controllers\PaymentLinkController::class, 'store']);
    // Legacy fallback for old mobile clients
    Route::match(['get','post'], '/wallet/qr-code', [WalletController::class, 'legacyQr']);

    // Deposits
    Route::post('/deposits/init', [\App\Http\Controllers\DepositController::class, 'init']);

    // Transactions
    Route::get('/transactions', [PaymentController::class, 'index']);
    Route::get('/getUserTransactions', [PaymentController::class, 'getUserTransactions']);
    // Transaction mutations/routes are intentionally not exposed publicly.
    // All state changes should go through payment/deposit/withdrawal flows.

    Route::get('/user', function (Request $request) {
        $user = $request->user();
        $user->load('wallet');
        $walletId = $user->wallet->id;
        $transactions = Transaction::where('cr_wallet_id', $walletId)
                                ->orWhere('dr_wallet_id', $walletId)
                                ->orderBy('created_at', 'desc')
                                ->get();

        return response()->json([
            'user' => $user,
            'wallet' => $user->wallet,
            'transactions' => $transactions,
        ]);
    });
});

// Public + authenticated payment flows
Route::get('/pay/{token}/prepare', [\App\Http\Controllers\PayController::class, 'prepare'])->middleware('throttle:60,1');
Route::post('/pay/{token}/execute', [\App\Http\Controllers\PayController::class, 'execute'])->middleware(['auth:api', 'throttle:30,1']);
Route::post('/pay/{token}/checkout', [\App\Http\Controllers\PayController::class, 'checkout'])->middleware('throttle:30,1');

// Webhooks
Route::post('/webhooks/paystack', [\App\Http\Controllers\WebhookController::class, 'paystack'])->middleware('throttle:120,1');
