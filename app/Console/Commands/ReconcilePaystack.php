<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use GuzzleHttp\Client;
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
            ->whereIn('type', ['payment'])
            ->where(function ($q) {
                $q->where('reference', 'like', 'DEP-%')
                  ->orWhere('reference', 'like', 'WEB-%');
            })
            ->limit($limit)
            ->get();

        foreach ($payments as $tx) {
            try {
                $resp = $client->get("https://api.paystack.co/transaction/verify/{$tx->reference}");
                $body = json_decode($resp->getBody(), true);
                if (!($body['status'] ?? false)) {
                    continue;
                }
                $status = $body['data']['status'] ?? null; // success/failed/abandoned
                if ($status === 'success') {
                    $tx->status = 'success';
                    $tx->external_ref = $body['data']['id'] ?? $tx->external_ref;
                    $meta = $tx->meta ?? [];
                    $meta['reconciled_at'] = now()->toIso8601String();
                    $tx->meta = $meta;
                    $tx->save();
                }
                if ($status === 'failed') {
                    $tx->status = 'failed';
                    $tx->external_ref = $body['data']['id'] ?? $tx->external_ref;
                    $meta = $tx->meta ?? [];
                    $meta['reconciled_at'] = now()->toIso8601String();
                    $tx->meta = $meta;
                    $tx->save();
                }
            } catch (\Throwable $e) {
                Log::warning('Reconcile payment failed', ['reference' => $tx->reference, 'error' => $e->getMessage()]);
            }
        }

        // Payouts: pending transfer refs starting with WD-
        $payouts = Transaction::where('status', 'pending')
            ->where('type', 'payout')
            ->where('reference', 'like', 'WD-%')
            ->limit($limit)
            ->get();

        foreach ($payouts as $tx) {
            try {
                if (!$tx->external_ref) {
                    continue;
                }
                $resp = $client->get("https://api.paystack.co/transfer/{$tx->external_ref}");
                $body = json_decode($resp->getBody(), true);
                if (!($body['status'] ?? false)) {
                    continue;
                }
                $status = $body['data']['status'] ?? null; // success|failed|ongoing
                if ($status === 'success') {
                    $tx->status = 'success';
                } elseif ($status === 'failed') {
                    $tx->status = 'failed';
                    // refund wallet?
                }
                $meta = $tx->meta ?? [];
                $meta['reconciled_at'] = now()->toIso8601String();
                $tx->meta = $meta;
                $tx->save();
            } catch (\Throwable $e) {
                Log::warning('Reconcile payout failed', ['reference' => $tx->reference, 'error' => $e->getMessage()]);
            }
        }

        $this->info('Reconciliation pass complete');
        return self::SUCCESS;
    }
}
