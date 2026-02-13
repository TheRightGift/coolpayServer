<?php
namespace App\Http\Controllers;

use App\Models\Wallet;
use App\Models\BankDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use App\Models\PaymentLink;

class WalletController extends Controller
{
    /**
     * Backward compatibility endpoint for /wallet/qr-code.
     * GET  -> return existing active QR/link if already generated.
     * POST -> regenerate new QR/link and revoke prior active tipping links.
     */
    public function legacyQr(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $wallet = $user->wallet;
        if (!$wallet) {
            return response()->json(['message' => 'Wallet not found'], 404);
        }

        $baseUrl = config('app.url');

        // GET: return previously generated active link/QR (if any)
        if ($request->isMethod('get')) {
            $existing = PaymentLink::where('wallet_id', $wallet->id)
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereNull('amount')
                ->latest('id')
                ->first();

            if (!$existing) {
                return response()->json([
                    'message' => 'No existing QR code',
                    'exists' => false,
                ]);
            }

            $tippingUrl = $baseUrl . '/pay/' . $existing->token;
            $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->generate($tippingUrl);

            return response()->json([
                'message' => 'Existing QR code found',
                'exists' => true,
                'token' => $existing->token,
                'tipping_url' => $tippingUrl,
                'qr_code' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            ]);
        }

        // POST: regenerate (revoke previous active links first)
        PaymentLink::where('wallet_id', $wallet->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('amount')
            ->update(['status' => 'revoked']);

        $request->validate([
            'amount' => 'nullable|numeric|min:1',
        ]);
        $amount = $request->amount ?? null;

        $link = PaymentLink::create([
            'wallet_id' => $wallet->id,
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => 'active',
        ]);

        $tippingUrl = $baseUrl . '/pay/' . $link->token;
        $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(220)->generate($tippingUrl);

        return response()->json([
            'message' => 'QR code regenerated',
            'exists' => true,
            'token' => $link->token,
            'tipping_url' => $tippingUrl,
            'qr_code' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'qr_payload' => json_encode([
                'token' => $link->token,
                'url' => $tippingUrl,
                'deep_link' => 'coolpay://pay/' . $link->token,
                'amount' => $amount,
            ]),
        ]);
    }

    /**
     * Refresh wallet balance and transactions.
     */
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

        $transactions = $wallet->transactions;

        return response()->json([
            'balance' => $wallet->balance,
            'transactions' => $transactions,
            'message' => 'Balance refreshed successfully'
        ]);
    }

    /**
     * Initiate withdrawal via Paystack transfer.
     */
    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1000',
            'account_number' => 'required|string|min:10|max:10',
            'bank_code' => 'required|string',
            'account_name' => 'nullable|string',
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

        // Idempotency support
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $existing = \App\Models\Transaction::where('initiator_user_id', $user->id)
                ->where('type', 'payout')
                ->where('reference', 'like', 'WD-%')
                ->where('meta->idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'message' => 'Withdrawal already initiated',
                    'reference' => $existing->reference,
                    'transaction_id' => $existing->id,
                    'status' => $existing->status,
                ], 200);
            }
        }

        // Save bank details
        $bankDetail = BankDetail::updateOrCreate(
            ['user_id' => $user->id],
            [
                'bank_name' => $request->bank_name ?? 'Unknown',
                'account_number' => $request->account_number,
                'account_name' => $request->account_name ?? 'Unknown',
                'bank_code' => $request->bank_code,
            ]
        );

        $secretKey = config('services.paystack.secret');

        $reference = 'WD-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(6));

        $tx = null;
        try {
            // Debit wallet upfront (hold)
            $wallet->balance -= $total;
            $wallet->save();

            // Create payout transaction
            $tx = \App\Models\Transaction::create([
                'dr_wallet_id' => $wallet->id,
                'cr_wallet_id' => null,
                'amount' => $total,
                'type' => 'payout',
                'status' => 'pending',
                'reference' => $reference,
                'description' => 'Withdrawal to bank',
                'meta' => [
                    'direction' => 'payout',
                    'fee' => $fee,
                    'net' => $amount,
                    'bank' => $bankDetail->only(['bank_name', 'bank_code', 'account_number', 'account_name']),
                    'idempotency_key' => $idempotencyKey,
                ],
                'initiator_user_id' => $user->id,
            ]);

            // Testing shortcut
            if (app()->environment('testing')) {
                $tx->external_ref = 'TEST-TRANSFER-' . $reference;
                $tx->status = 'success';
                $tx->save();
                return response()->json([
                    'message' => 'Withdrawal initiated (testing stub)',
                    'transaction_id' => $tx->id,
                    'reference' => $reference,
                    'amount' => $amount,
                    'fee' => $fee,
                    'total_debited' => $total,
                ]);
            }

            if (!$secretKey) {
                throw new \Exception('Paystack secret not configured');
            }

            $client = new Client();

            // Create transfer recipient
            $recipientResp = $client->request('POST', 'https://api.paystack.co/transferrecipient', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'type' => 'nuban',
                    'name' => $bankDetail->account_name ?? $user->name,
                    'account_number' => $bankDetail->account_number,
                    'bank_code' => $bankDetail->bank_code,
                    'currency' => 'NGN',
                ],
            ]);

            $recipientBody = json_decode($recipientResp->getBody(), true);
            if (!($recipientBody['status'] ?? false)) {
                throw new \Exception('Failed to create transfer recipient');
            }
            $recipientCode = $recipientBody['data']['recipient_code'] ?? null;

            // Check Paystack balance before initiating transfer
            $balanceResp = $client->request('GET', 'https://api.paystack.co/balance', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Accept' => 'application/json',
                ],
            ]);
            $balanceBody = json_decode($balanceResp->getBody(), true);
            $availableKobo = $balanceBody['data'][0]['balance'] ?? 0;
            if ($availableKobo < (int) round($amount * 100)) {
                throw new \Exception('Insufficient Paystack balance for transfer');
            }

            // Initiate Paystack transfer
            $transferInit = $client->request('POST', 'https://api.paystack.co/transfer', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'source' => 'balance',
                    'amount' => (int) round($amount * 100), // user receives amount, fee stays with us
                    'reference' => $reference,
                    'reason' => 'CoolPay withdrawal',
                    'recipient' => $recipientCode,
                ],
            ]);

            $body = json_decode($transferInit->getBody(), true);
            if (!($body['status'] ?? false)) {
                throw new \Exception('Failed to initiate transfer');
            }

            $transferCode = $body['data']['transfer_code'] ?? null;
            $tx->external_ref = $transferCode;
            $tx->save();

            return response()->json([
                'message' => 'Withdrawal initiated',
                'transaction_id' => $tx->id,
                'reference' => $reference,
                'amount' => $amount,
                'fee' => $fee,
                'total_debited' => $total,
            ]);
        } catch (\Throwable $e) {
            // Refund wallet on failure
            $wallet->balance += $total;
            $wallet->save();
            if ($tx) {
                $tx->status = 'failed';
                $tx->save();
            }
            Log::error('Withdrawal failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Withdrawal failed',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List banks from Paystack.
     */
    public function getBanks()
    {
        try {
            $secretKey = config('services.paystack.secret');

            // Make HTTP request with authorization header
            $client = new Client();
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
