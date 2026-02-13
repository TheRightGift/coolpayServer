<?php

namespace App\Http\Controllers;

use App\Models\PaymentLink;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PayController extends Controller
{
    public function prepare($token)
    {
        $link = PaymentLink::with('wallet.user')->where('token', $token)->first();
        if (!$link || !$link->isActive()) {
            return response()->json(['message' => 'Invalid or expired link'], 404);
        }

        return response()->json([
            'token' => $link->token,
            'receiver' => [
                'id' => $link->wallet->user->id,
                'name' => $link->wallet->user->name,
                'wallet_id' => $link->wallet->id,
            ],
            'amount' => $link->amount,
            'memo' => $link->memo,
            'expires_at' => $link->expires_at,
            'status' => $link->status,
        ]);
    }

    public function execute(Request $request, $token)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1',
        ]);

        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            $result = DB::transaction(function () use ($user, $token, $validated, $idempotencyKey) {
                if ($idempotencyKey) {
                    $existing = Transaction::where('initiator_user_id', $user->id)
                        ->where('type', 'payment')
                        ->where('reference', 'like', 'PAY-%')
                        ->where('meta->idempotency_key', $idempotencyKey)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        return [
                            'idempotent' => true,
                            'tx' => $existing,
                        ];
                    }
                }

                $link = PaymentLink::with('wallet.user')
                    ->where('token', $token)
                    ->lockForUpdate()
                    ->first();

                if (!$link || !$link->isActive()) {
                    return ['error' => response()->json(['message' => 'Invalid or expired link'], 404)];
                }

                $amount = $link->amount ?? ($validated['amount'] ?? null);
                if (!$amount) {
                    return ['error' => response()->json(['message' => 'Amount required'], 400)];
                }

                $senderWallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                if (!$senderWallet) {
                    return ['error' => response()->json(['message' => 'Sender wallet not found'], 404)];
                }

                if ($senderWallet->id === $link->wallet_id) {
                    return ['error' => response()->json(['message' => 'Cannot pay yourself'], 400)];
                }

                $currentBalance = $senderWallet->actual_balance ?? $senderWallet->balance;
                if ($amount > $currentBalance) {
                    return ['error' => response()->json(['message' => 'Insufficient funds'], 402)];
                }

                $receiverWallet = Wallet::where('id', $link->wallet_id)->lockForUpdate()->first();
                if (!$receiverWallet) {
                    return ['error' => response()->json(['message' => 'Receiver wallet not found'], 404)];
                }

                $reference = 'PAY-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(6));

                $tx = Transaction::create([
                    'dr_wallet_id' => $senderWallet->id,
                    'cr_wallet_id' => $link->wallet_id,
                    'amount' => $amount,
                    'type' => 'payment',
                    'status' => 'success',
                    'reference' => $reference,
                    'description' => $link->memo,
                    'initiator_user_id' => $user->id,
                    'meta' => [
                        'payment_link_id' => $link->id,
                        'source' => 'app-execute',
                        'direction' => 'user_payment',
                        'idempotency_key' => $idempotencyKey,
                    ],
                ]);

                $senderWallet->balance = $senderWallet->balance - $amount;
                $senderWallet->save();

                $receiverWallet->balance = $receiverWallet->balance + $amount;
                $receiverWallet->save();

                return [
                    'idempotent' => false,
                    'tx' => $tx,
                    'reference' => $reference,
                    'amount' => $amount,
                    'receiver' => [
                        'id' => $link->wallet->user->id,
                        'name' => $link->wallet->user->name,
                    ],
                    'sender_balance' => $senderWallet->balance,
                    'receiver_balance' => $receiverWallet->balance,
                ];
            });

            if (isset($result['error'])) {
                return $result['error'];
            }

            if (!empty($result['idempotent'])) {
                $existing = $result['tx'];
                return response()->json([
                    'message' => 'Payment already processed',
                    'reference' => $existing->reference,
                    'transaction_id' => $existing->id,
                    'status' => $existing->status,
                ], 200);
            }

            return response()->json([
                'reference' => $result['reference'],
                'status' => 'success',
                'amount' => $result['amount'],
                'receiver' => $result['receiver'],
                'sender_balance' => $result['sender_balance'],
                'receiver_balance' => $result['receiver_balance'],
                'transaction_id' => $result['tx']->id,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Payment failed', 'error' => $e->getMessage()], 500);
        }
    }

    // Public checkout start (non-app payers) - stub for Paystack redirect/inline
    public function checkout(Request $request, $token)
    {
        $link = PaymentLink::with('wallet.user')->where('token', $token)->first();
        if (!$link || !$link->isActive()) {
            return response()->json(['message' => 'Invalid or expired link'], 404);
        }

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:1',
            'email' => 'required|email',
            'payer_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:32',
        ]);

        $amount = $link->amount ?? $validated['amount'] ?? null;
        if (!$amount) {
            return response()->json(['message' => 'Amount required'], 400);
        }

        $reference = 'WEB-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(6));

        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $existing = Transaction::where('type', 'payment')
                ->where('reference', 'like', 'WEB-%')
                ->where('meta->idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'message' => 'Checkout already initialized',
                    'reference' => $existing->reference,
                    'transaction_id' => $existing->id,
                ], 200);
            }
        }

        $tx = Transaction::create([
            'dr_wallet_id' => null,
            'cr_wallet_id' => $link->wallet_id,
            'amount' => $amount,
            'type' => 'payment',
            'status' => 'pending',
            'reference' => $reference,
            'description' => $link->memo,
            'meta' => [
                'payment_link_id' => $link->id,
                'payer_email' => $validated['email'],
                'payer_name' => $validated['payer_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'source' => 'web-checkout',
                'direction' => 'user_payment',
                'idempotency_key' => $idempotencyKey,
            ],
        ]);

        // Initialize Paystack Standard
        $secretKey = config('services.paystack.secret');
        if (!$secretKey) {
            return response()->json(['message' => 'Paystack secret not configured'], 500);
        }

        $client = new \GuzzleHttp\Client();
        $callbackUrl = config('app.url') . '/api/webhooks/paystack';

        if (app()->environment('testing')) {
            return response()->json([
                'message' => 'Checkout initialized (testing stub)',
                'reference' => $reference,
                'transaction_id' => $tx->id,
                'authorization_url' => 'https://paystack.test/authorize/' . $reference,
                'access_code' => 'TEST-' . $reference,
            ]);
        }

        try {
            $paystackResponse = $client->request('POST', 'https://api.paystack.co/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'amount' => (int) round($amount * 100), // kobo
                    'email' => $validated['email'],
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => [
                        'payment_link_id' => $link->id,
                        'receiver_wallet_id' => $link->wallet_id,
                        'memo' => $link->memo,
                        'payer_name' => $validated['payer_name'] ?? null,
                        'phone' => $validated['phone'] ?? null,
                        'source' => 'web-checkout',
                        'direction' => 'user_payment',
                    ],
                ],
            ]);

            $body = json_decode($paystackResponse->getBody(), true);
            if (!($body['status'] ?? false)) {
                return response()->json(['message' => 'Failed to initialize payment'], 400);
            }

            return response()->json([
                'message' => 'Checkout initialized',
                'reference' => $reference,
                'transaction_id' => $tx->id,
                'authorization_url' => $body['data']['authorization_url'] ?? null,
                'access_code' => $body['data']['access_code'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Paystack init failed', 'error' => $e->getMessage()], 500);
        }
    }
}
