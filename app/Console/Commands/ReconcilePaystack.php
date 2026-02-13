<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\Wallet;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePaystack extends Command
{
    protected $signature = 'reconcile:paystack {--limit=50}';
    protected $description = 'Reconcile pending Paystack payments and payouts by verifying status with Paystack';

    public function handle(): int
    {
        $secret = config('services.paystack.secret');
        if (!$secret) {
            $this->error('Paystack secret not configured');
            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        $client = new Client([
            'headers' => [
                'Authorization' => 'Bearer ' . $secret,
                'Accept' => 'application/json',
            ]
        ]);

        // Payments: pending charge refs starting with DEP- or WEB-
        $payments = Transaction::where('status', 'pending')
            ->where('type', 'payment')
            ->where(function ($q) {
                $q->where('reference', 'like', 'DEP-%')
                  ->orWhere('reference', 'like', 'WEB-%');
            })
            ->limit($limit)
            ->get();

        foreach ($payments as $pendingTx) {
            try {
                $resp = $client->get("https://api.paystack.co/transaction/verify/{$pendingTx->reference}");
                $body = json_decode($resp->getBody(), true);
                if (!($body['status'] ?? false)) {
                    continue;
                }

                $status = $body['data']['status'] ?? null; // success/failed/abandoned
                if (!in_array($status, ['success', 'failed'], true)) {
                    continue;
                }

                DB::transaction(function () use ($pendingTx, $body, $status) {
                    $tx = Transaction::where('id', $pendingTx->id)->lockForUpdate()->first();
                    if (!$tx || $tx->status !== 'pending') {
                        return;
                    }

                    $meta = $tx->meta ?? [];
                    $meta['reconciled_at'] = now()->toIso8601String();

                    $tx->status = $status;
                    $tx->external_ref = $body['data']['id'] ?? $tx->external_ref;
                    $tx->meta = $meta;
                    $tx->save();

                    if ($status === 'success' && $tx->cr_wallet_id) {
                        $wallet = Wallet::where('id', $tx->cr_wallet_id)->lockForUpdate()->first();
                        if ($wallet) {
                            $wallet->balance = $wallet->balance + $tx->amount;
                            $wallet->save();
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('Reconcile payment failed', ['reference' => $pendingTx->reference, 'error' => $e->getMessage()]);
            }
        }

        // Payouts: pending transfer refs starting with WD-
        $payouts = Transaction::where('status', 'pending')
            ->where('type', 'payout')
            ->where('reference', 'like', 'WD-%')
            ->limit($limit)
            ->get();

        foreach ($payouts as $pendingTx) {
            try {
                if (!$pendingTx->external_ref) {
                    continue;
                }

                $resp = $client->get("https://api.paystack.co/transfer/{$pendingTx->external_ref}");
                $body = json_decode($resp->getBody(), true);
                if (!($body['status'] ?? false)) {
                    continue;
                }

                $status = $body['data']['status'] ?? null; // success|failed|ongoing
                if (!in_array($status, ['success', 'failed'], true)) {
                    continue;
                }

                DB::transaction(function () use ($pendingTx, $status) {
                    $tx = Transaction::where('id', $pendingTx->id)->lockForUpdate()->first();
                    if (!$tx || $tx->status !== 'pending') {
                        return;
                    }

                    $meta = $tx->meta ?? [];
                    $meta['reconciled_at'] = now()->toIso8601String();

                    $tx->status = $status;
                    $tx->meta = $meta;
                    $tx->save();

                    if ($status === 'failed' && $tx->dr_wallet_id) {
                        $wallet = Wallet::where('id', $tx->dr_wallet_id)->lockForUpdate()->first();
                        if ($wallet) {
                            $wallet->balance = $wallet->balance + $tx->amount;
                            $wallet->save();
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('Reconcile payout failed', ['reference' => $pendingTx->reference, 'error' => $e->getMessage()]);
            }
        }

        $this->info('Reconciliation pass complete');
        return self::SUCCESS;
    }
}
