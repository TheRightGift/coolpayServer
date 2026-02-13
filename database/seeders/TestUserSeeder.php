<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $usersData = [
            [
                'name' => 'Test User A',
                'email' => 'user.a@test.com',
                'password' => 'password123',
                'deposit_amount' => 500000.00,
            ],
            [
                'name' => 'Test User B',
                'email' => 'user.b@test.com',
                'password' => 'password123',
                'deposit_amount' => 50000.00,
            ],
        ];

        foreach ($usersData as $data) {
            $existingUser = User::where('email', $data['email'])->first();

            if ($existingUser) {
                $this->command->info("User '{$data['name']}' already exists. Skipping creation.");
                $user = $existingUser;
            } else {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bcrypt($data['password']),
                    'email_verified_at' => now(),
                ]);
                $this->command->info("User '{$data['name']}' created successfully.");
            }

            $wallet = $user->wallet()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0,
                    'tipping_url' => null,
                ]
            );

            $this->seedInitialDeposit($wallet, $data['deposit_amount']);
        }

        $this->command->info('Seeding complete. Two users and their initial deposits are ready.');
    }

    protected function seedInitialDeposit(Wallet $wallet, float $amount): void
    {
        $walletId = $wallet->id;
        $reference = 'INIT-DEPOSIT-' . $walletId . '-' . time();

        if (Transaction::where('reference', $reference)->exists()) {
            $this->command->warn("Deposit transaction already exists for Wallet ID: {$walletId}.");
            return;
        }

        Transaction::create([
            'dr_wallet_id' => null,
            'cr_wallet_id' => $walletId,
            'amount' => $amount,
            'type' => 'payment',
            'status' => 'success',
            'reference' => $reference,
            'description' => 'Initial test deposit.',
            'meta' => [
                'direction' => 'deposit_funding',
                'seeded' => true,
            ],
        ]);

        $wallet->balance = ($wallet->balance ?? 0) + $amount;
        $wallet->save();

        $this->command->info("Initial deposit of ₦" . number_format($amount, 2) . " seeded for User ID: {$wallet->user_id}.");
    }
}
