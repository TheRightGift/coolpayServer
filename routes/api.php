<?php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Models\Transaction;
// use App\Http\Controllers\Auth\AuthController;
// use App\Http\Controllers\TwoFactorController;
// use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\PaymentController;
// use App\Http\Controllers\TipController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LogoutController;

Route::prefix(('auth'))->group(function () {
    Route::post('/login', [LoginController::class, 'apiLogin']);
    Route::post('/logout', [LogoutController::class, 'apiLogout'])->name('Api-logout');
});

Route::middleware('auth:api')->group(function () {
    // Route::post('/2fa/enable', [TwoFactorController::class, 'enable']);
    // Route::post('/2fa/verify', [TwoFactorController::class, 'verify']);
    // Route::post('/2fa/disable', [TwoFactorController::class, 'disable']);
    // Route::post('/profile/update', [ProfileController::class, 'update']);
    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
    Route::get('/wallet/refresh-balance', [WalletController::class, 'refreshBalance']);
    Route::get('/banks', [WalletController::class, 'getBanks']);
    Route::get('/wallet/qr-code', [WalletController::class, 'generateTippingQrCode']);
    Route::get('/getUserTransactions', [PaymentController::class, 'getUserTransactions']);
    Route::apiResource('transactions', PaymentController::class)->except(['index']);
    Route::get('/user', function (Request $request) {
        $user = $request->user();

        // Eager load the wallet
        $user->load('wallet'); 

        // Fetch transactions separately or via your PaymentController method
        $walletId = $user->wallet->id;
        $transactions = Transaction::where('cr_wallet_id', $walletId)
                                ->orWhere('dr_wallet_id', $walletId)
                                ->orderBy('created_at', 'desc')
                                ->get();

        // The Wallet model's actual_balance accessor is automatically available now.
        // It will be included in the user's JSON response if you use a UserResource 
        // or if you manually format the response.
        
        return response()->json([
            'user' => $user,
            'wallet' => $user->wallet, 
            'transactions' => $transactions,
        ]);
    });
});

// Route::post('/tip/{key}', [TipController::class, 'initiateTip']);
// Route::post('/tip/{key}/verify', [TipController::class, 'verifyOtp']);
