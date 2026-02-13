<?php

namespace Tests\Feature;

use App\Models\PaymentLink;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    #[Test]
    public function prepare_returns_receiver_details()
    {
        $receiver = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $receiver->id]);
        $link = PaymentLink::create([
            'wallet_id' => $wallet->id,
            'user_id' => $receiver->id,
            'amount' => 1000,
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/pay/{$link->token}/prepare");
        $response->assertStatus(200)
            ->assertJsonFragment([
                'token' => $link->token,
                'amount' => 1000,
                'status' => 'active',
            ]);
    }

    #[Test]
    public function execute_debits_sender_and_credits_receiver()
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $senderWallet = Wallet::factory()->create(['user_id' => $sender->id, 'balance' => 5000]);
        $receiverWallet = Wallet::factory()->create(['user_id' => $receiver->id, 'balance' => 0]);

        $link = PaymentLink::create([
            'wallet_id' => $receiverWallet->id,
            'user_id' => $receiver->id,
            'amount' => 1200,
            'status' => 'active',
        ]);

        Passport::actingAs($sender);

        $response = $this->postJson("/api/pay/{$link->token}/execute", [] , ['Idempotency-Key' => Str::random(8)]);
        $response->assertStatus(200)
            ->assertJsonFragment(['status' => 'success']);

        $senderWallet->refresh();
        $receiverWallet->refresh();
        $this->assertEquals(3800, (int) $senderWallet->balance);
        $this->assertEquals(1200, (int) $receiverWallet->balance);
    }

    #[Test]
    public function deposit_init_returns_stub_and_creates_pending_tx()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id]);
        Passport::actingAs($user);

        $response = $this->postJson('/api/deposits/init', [
            'amount' => 1500,
            'email' => $user->email,
        ], ['Idempotency-Key' => 'DEPO-1']);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Deposit initialized (testing stub)']);

        $this->assertDatabaseHas('transactions', [
            'reference' => $response['reference'],
            'status' => 'pending',
            'type' => 'payment',
        ]);
    }

    #[Test]
    public function checkout_init_returns_stub_and_creates_pending_tx()
    {
        $receiver = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $receiver->id]);
        $link = PaymentLink::create([
            'wallet_id' => $wallet->id,
            'user_id' => $receiver->id,
            'amount' => 500,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/pay/{$link->token}/checkout", [
            'email' => 'payer@example.com',
        ], ['Idempotency-Key' => 'CHK-1']);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Checkout initialized (testing stub)']);

        $this->assertDatabaseHas('transactions', [
            'reference' => $response['reference'],
            'status' => 'pending',
            'type' => 'payment',
        ]);
    }

    #[Test]
    public function withdrawal_stub_debits_wallet_and_marks_success()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 5000]);
        Passport::actingAs($user);

        $response = $this->postJson('/api/wallet/withdraw', [
            'amount' => 2000,
            'account_number' => '1234567890',
            'bank_code' => '999',
            'account_name' => 'Test User',
        ], ['Idempotency-Key' => 'WD-1']);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Withdrawal initiated (testing stub)']);

        $wallet->refresh();
        $this->assertEquals(5000 - 2300, (int) $wallet->balance); // 2000 + 300 fee

        $this->assertDatabaseHas('transactions', [
            'reference' => $response['reference'],
            'type' => 'payout',
            'status' => 'success',
        ]);
    }

    #[Test]
    public function webhook_charge_success_credits_wallet()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 0]);

        $tx = Transaction::create([
            'dr_wallet_id' => null,
            'cr_wallet_id' => $wallet->id,
            'amount' => 1000,
            'type' => 'payment',
            'status' => 'pending',
            'reference' => 'DEP-TEST-1',
        ]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $tx->reference,
                'id' => 'PAYSTACK-ID-1',
                'metadata' => ['wallet_id' => $wallet->id],
            ],
        ];
        $secret = config('services.paystack.secret', 'testsecret');
        config(['services.paystack.secret' => $secret]);
        $signature = hash_hmac('sha512', json_encode($payload), $secret);

        $response = $this->withHeaders(['X-Paystack-Signature' => $signature])
            ->postJson('/api/webhooks/paystack', $payload);

        $response->assertStatus(200);
        $tx->refresh();
        $wallet->refresh();
        $this->assertEquals('success', $tx->status);
        $this->assertEquals(1000, (int) $wallet->balance);
    }

    #[Test]
    public function webhook_transfer_failed_refunds_wallet()
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'balance' => 1000]);

        $tx = Transaction::create([
            'dr_wallet_id' => $wallet->id,
            'cr_wallet_id' => null,
            'amount' => 500,
            'type' => 'payout',
            'status' => 'pending',
            'reference' => 'WD-TEST-1',
            'external_ref' => 'TRF_CODE',
        ]);

        // simulate hold already applied
        $wallet->balance -= 500;
        $wallet->save();

        $payload = [
            'event' => 'transfer.failed',
            'data' => [
                'reference' => $tx->reference,
                'transfer_code' => 'TRF_CODE',
                'id' => 'PAYSTACK-TRF-ID',
            ],
        ];
        $secret = config('services.paystack.secret', 'testsecret');
        config(['services.paystack.secret' => $secret]);
        $signature = hash_hmac('sha512', json_encode($payload), $secret);

        $response = $this->withHeaders(['X-Paystack-Signature' => $signature])
            ->postJson('/api/webhooks/paystack', $payload);

        $response->assertStatus(200);
        $tx->refresh();
        $wallet->refresh();
        $this->assertEquals('failed', $tx->status);
        $this->assertEquals(1000, (int) $wallet->balance); // refunded hold
    }
}
