<?php
namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\BankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class WalletController extends Controller
{
    public function generateTippingQrCode(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        // Ensure the tipping URL is a valid, absolute URL
        $tippingUrl = $wallet->tipping_url;
        if (!filter_var($tippingUrl, FILTER_VALIDATE_URL)) {
            $tippingUrl = config('app.url') . '/t/' . basename($tippingUrl);
        }

        try {
            // Generate QR code as PNG with explicit URL encoding
            $qrCode = QrCode::format('png')->size(300)->generate($tippingUrl);
            $qrCodeBase64 = base64_encode($qrCode);

            return response()->json([
                'message' => 'QR code generated',
                'tipping_url' => $tippingUrl,
                'qr_code' => 'data:image/png;base64,' . $qrCodeBase64,
            ]);
        } catch (\Exception $e) {
            Log::error('QR code generation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate QR code'
            ], 500);
        }
    }

    public function generateTippingQrCode1(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            // Create wallet if it doesn't exist
            $wallet = $user->wallet()->create([
                'balance' => 0,
                'tipping_url' => config('app.url') . '/t/' . \Illuminate\Support\Str::random(32),
            ]);
        }

        try {
            // Use SVG format which doesn't require Imagick extension
            $qrCode = QrCode::size(300)
                ->format('svg')
                ->errorCorrection('M')
                ->margin(1)
                ->generate($wallet->tipping_url);

            // For SVG, we don't need to base64 encode, but we'll do it anyway for consistency
            $qrCodeBase64 = base64_encode($qrCode);

            return response()->json([
                'message' => 'QR code generated successfully',
                'tipping_url' => $wallet->tipping_url,
                'qr_code' => 'data:image/svg+xml;base64,' . $qrCodeBase64,
                'user_name' => $user->name,
            ]);
        } catch (\Exception $e) {
            Log::error('QR code generation failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate QR code'
            ], 500);
        }
    }

    public function getWalletStats(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        $wallet = $user->wallet;

        // Calculate statistics
        $totalTips = $wallet->transactions()
            ->where('type', 'tip')
            ->where('status', 'completed')
            ->sum('amount');

        $totalWithdrawals = $wallet->transactions()
            ->where('type', 'withdrawal')
            ->sum('amount');

        $pendingWithdrawals = $wallet->transactions()
            ->where('type', 'withdrawal')
            ->where('status', 'pending')
            ->sum('amount');

        $thisMonthTips = $wallet->transactions()
            ->where('type', 'tip')
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return response()->json([
            'balance' => $wallet->balance,
            'total_tips' => $totalTips,
            'total_withdrawals' => $totalWithdrawals,
            'pending_withdrawals' => $pendingWithdrawals,
            'this_month_tips' => $thisMonthTips,
            'total_transactions' => $wallet->transactions()->count(),
        ]);
    }

    public function refreshBalance()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        // Calculate the total tips received
        $totalTips = $wallet->transactions()
            ->where('type', 'tip')
            ->where('status', 'completed')
            ->sum('amount');

        // Get all transactions to return to frontend
        $transactions = $wallet->transactions;

        // Return the current balance and total tips - this ensures we get the most up-to-date values from the database
        // In a real-world application, this could also trigger verification of
        // pending transactions or check for incoming payments
        return response()->json([
            'balance' => $wallet->balance,
            'total_tips' => $totalTips,
            'transactions' => $transactions,
            'message' => 'Balance refreshed successfully'
        ]);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'account_number' => 'required|string|min:10|max:10',
            'bank_code' => 'required|string',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        $amount = $request->amount;
        $fee = 300; // NGN 300 withdrawal fee
        $total = $amount + $fee;

        if ($total > $wallet->balance) {
            return response()->json([
                'message' => 'Insufficient balance. You need ₦' . number_format($total) . ' (including ₦300 fee)'
            ], 400);
        }

        // Update or create bank details
        BankDetail::updateOrCreate(
            ['user_id' => $user->id],
            [
                'bank_name' => $request->bank_name ?? 'Unknown',
                'account_number' => $request->account_number,
                'account_name' => $request->account_name ?? 'Unknown',
                'bank_code' => $request->bank_code,
            ]
        );

        try {
            // For now, we'll create a withdrawal request that needs to be processed manually
            // In a production environment, you would integrate with Paystack's Transfer API
            // or use a different service for bank transfers

            // Create transaction record
            $transaction = \App\Models\Transaction::create([
                'wallet_id' => $wallet->id,
                'amount' => $total,
                'type' => 'withdrawal',
                'status' => 'pending',
                'qr_code_key' => \Illuminate\Support\Str::random(32),
            ]);

            // Deduct from wallet balance
            $wallet->balance -= $total;
            $wallet->save();

            // Log withdrawal request for manual processing
            Log::info('Withdrawal request created', [
                'user_id' => $user->id,
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'fee' => $fee,
                'total' => $total,
                'account_number' => $request->account_number,
                'bank_code' => $request->bank_code,
            ]);

            return response()->json([
                'message' => 'Withdrawal request submitted successfully. You will receive payment within 24 hours.',
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'fee' => $fee,
                'total' => $total
            ]);
        } catch (\Exception $e) {
            Log::error('Withdrawal failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Withdrawal failed: ' . $e->getMessage()
            ], 400);
        }
    }

    public function getBanks()
    {
        try {
            $secretKey = config('services.paystack.secret');

            // Make HTTP request with authorization header
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', 'https://api.paystack.co/bank', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Accept' => 'application/json',
                ]
            ]);

            $banks = json_decode($response->getBody(), true);

            if ($banks && isset($banks['data'])) {
                return response()->json(['banks' => $banks['data']]);
            } else {
                Log::error('Invalid response format from Paystack API');
                return response()->json(['message' => 'Failed to fetch banks'], 400);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch banks: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch banks'], 400);
        }
    }
}
