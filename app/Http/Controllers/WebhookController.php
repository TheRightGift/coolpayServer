<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    public function paystack(Request $request)
    {
        $secret = config('services.paystack.secret');
        $signature = $request->header('X-Paystack-Signature');
        $payload = $request->getContent();

        if (!$secret || !$signature) {
            return response()->json(['message' => 'Signature missing'], 400);
        }

        $computed = hash_hmac('sha512', $payload, $secret);
        if (!hash_equals($computed, $signature)) {
            Log::warning('Invalid Paystack signature');
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->input('event');
        $data = $request->input('data');
        $reference = $data['reference'] ?? null;
        $metadata = $data['metadata'] ?? [];

        // Transfer webhooks may not include reference the same way
        $transferCode = $data['transfer_code'] ?? null;

        // Handle transfer webhooks separately
        if ($event === 'transfer.success' || $event === 'transfer.failed') {
            $tx = null;
            if ($transferCode) {
                $tx = Transaction::where('external_ref', $transferCode)->lockForUpdate()->first();
            }
            if (!$tx && $reference) {
                $tx = Transaction::where('reference', $reference)->lockForUpdate()->first();
            }
            if (!$tx) {
                Log::warning('Transfer webhook transaction not found', ['transfer_code' => $transferCode, 'reference' => $reference]);
                return response()->json(['message' => 'ok']);
            }

            try {
                DB::beginTransaction();
                $meta = $tx->meta ?? [];
                $meta['webhook_events'][] = [
                    'event' => $event,
                    'received_at' => now()->toIso8601String(),
                    'payload_id' => $data['id'] ?? null,
                ];
                $tx->meta = $meta;
                $tx->status = $event === 'transfer.success' ? 'success' : 'failed';
                $tx->external_ref = $transferCode ?? $tx->external_ref;
                $tx->save();

                if ($event === 'transfer.failed') {
                    // Refund wallet if we debited at initiation
                    if ($tx->dr_wallet_id) {
                        $wallet = Wallet::where('id', $tx->dr_wallet_id)->lockForUpdate()->first();
                        if ($wallet) {
                            $wallet->balance = $wallet->balance + $tx->amount;
                            $wallet->save();
                        }
                    }
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Transfer webhook processing failed', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'error'], 500);
            }

            return response()->json(['message' => 'ok']);
        }

        if (!$reference) {
            return response()->json(['message' => 'No reference'], 400);
        }

        $tx = Transaction::where('reference', $reference)->lockForUpdate()->first();
        if (!$tx) {
            Log::warning('Webhook reference not found', ['reference' => $reference]);
            return response()->json(['message' => 'ok']);
        }

        if ($tx->status === 'success') {
            return response()->json(['message' => 'ok']);
        }

        if ($event === 'charge.success') {
            try {
                DB::beginTransaction();
                $meta = $tx->meta ?? [];
                $meta['webhook_events'][] = [
                    'event' => $event,
                    'received_at' => now()->toIso8601String(),
                    'payload_id' => $data['id'] ?? null,
                ];
                $tx->meta = $meta;
                $tx->status = 'success';
                $tx->external_ref = $data['id'] ?? null;
                $tx->save();

                // Determine credit wallet
                $creditWalletId = $tx->cr_wallet_id;
                if (!$creditWalletId && isset($metadata['wallet_id'])) {
                    $creditWalletId = $metadata['wallet_id'];
                }

                if ($creditWalletId) {
                    $wallet = Wallet::where('id', $creditWalletId)->lockForUpdate()->first();
                    if ($wallet) {
                        $wallet->balance = $wallet->balance + $tx->amount;
                        $wallet->save();
                    }
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('Webhook processing failed', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'error'], 500);
            }
        }

        if ($event === 'charge.failed') {
            $tx->status = 'failed';
            $tx->external_ref = $data['id'] ?? null;
            $tx->save();
        }

        return response()->json(['message' => 'ok']);
    }
}
