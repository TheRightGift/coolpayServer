<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use GuzzleHttp\Client;

class DepositController extends Controller
{
    /**
     * Initialize a deposit (top-up) via Paystack Standard.
     */
    public function init(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'email' => 'nullable|email',
        ]);

        $amount = $validated['amount'];
        $email = $validated['email'] ?? $user->email;
        $reference = 'DEP-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(6));

        // Idempotency support
        $idempotencyKey = $request->header('Idempotency-Key');
        if ($idempotencyKey) {
            $existing = Transaction::where('initiator_user_id', $user->id)
                ->where('type', 'payment')
                ->where('status', 'pending')
                ->where('meta->direction', 'deposit_funding')
                ->where('meta->idempotency_key', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'message' => 'Deposit already initialized',
                    'reference' => $existing->reference,
                    'transaction_id' => $existing->id,
                ], 200);
            }
        }

        // Create pending transaction (deposit funding into company account, credit user wallet on webhook)
        $tx = Transaction::create([
            'dr_wallet_id' => null,
            'cr_wallet_id' => $user->wallet->id,
            'amount' => $amount,
            'type' => 'payment',
            'status' => 'pending',
            'reference' => $reference,
            'description' => 'Wallet deposit',
            'meta' => [
                'direction' => 'deposit_funding',
                'user_id' => $user->id,
                'wallet_id' => $user->wallet->id,
                'source' => 'deposit-init',
                'idempotency_key' => $idempotencyKey,
            ],
            'initiator_user_id' => $user->id,
        ]);

        // Testing shortcut (avoid external HTTP calls)
        if (app()->environment('testing')) {
            return response()->json([
                'message' => 'Deposit initialized (testing stub)',
                'reference' => $reference,
                'transaction_id' => $tx->id,
                'authorization_url' => 'https://paystack.test/authorize/' . $reference,
                'access_code' => 'TEST-' . $reference,
            ], 200);
        }

        $secretKey = config('services.paystack.secret');
        if (!$secretKey) {
            return response()->json(['message' => 'Paystack secret not configured'], 500);
        }

        $client = new Client();
        $callbackUrl = config('app.url') . '/api/webhooks/paystack';

        try {
            $paystackResponse = $client->request('POST', 'https://api.paystack.co/transaction/initialize', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'amount' => (int) round($amount * 100), // kobo
                    'email' => $email,
                    'reference' => $reference,
                    'callback_url' => $callbackUrl,
                    'metadata' => [
                        'direction' => 'deposit_funding',
                        'wallet_id' => $user->wallet->id,
                        'user_id' => $user->id,
                        'transaction_id' => $tx->id,
                        'source' => 'deposit-init',
                        'idempotency_key' => $idempotencyKey,
                    ],
                ],
            ]);

            $body = json_decode($paystackResponse->getBody(), true);
            if (!($body['status'] ?? false)) {
                return response()->json(['message' => 'Failed to initialize deposit'], 400);
            }

            return response()->json([
                'message' => 'Deposit initialized',
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
