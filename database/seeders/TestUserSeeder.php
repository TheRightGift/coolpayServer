<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction; // <-- Import Transaction model
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Define the users and their initial deposits
        $usersData = [
            [
                'name' => 'Test User A',
                'email' => 'user.a@test.com',
                'password' => 'password123',
                'deposit_amount' => 500000.00, // ₦500,000.00
            ],
            [
                'name' => 'Test User B',
                'email' => 'user.b@test.com',
                'password' => 'password123',
                'deposit_amount' => 50000.00, // ₦50,000.00
            ],
        ];

        foreach ($usersData as $data) {
            $existingUser = User::where('email', $data['email'])->first();

            if ($existingUser) {
                $this->command->info("User '{$data['name']}' already exists. Skipping creation.");
                $user = $existingUser;
            } else {
                // 1. Create User
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bcrypt($data['password']),
                    'email_verified_at' => now(),
                    // 'last_login_at' => now(), // Initialize last login
                ]);
                $this->command->info("User '{$data['name']}' created successfully.");
            }

            // 2. Create or Get Wallet
            $wallet = $user->wallet()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0, // Balance will be updated via transactions later
                    'tipping_url' => Str::random(32),
                ]
            );

            // 3. Seed Initial Deposit Transaction
            $this->seedInitialDeposit($wallet, $data['deposit_amount']);
        }

        $this->command->info('Seeding complete. Two users and their initial deposits are ready.');
    }

    /**
     * Seeds a single successful deposit transaction for a given wallet.
     * * @param \App\Models\Wallet $wallet
     * @param float $amount
     * @return void
     */
    protected function seedInitialDeposit(Wallet $wallet, float $amount): void
    {
        // Use the wallet ID for both Dr and Cr to denote an external deposit
        $walletId = $wallet->id; 

        // Ensure transaction is not duplicated
        $reference = 'INIT-DEPOSIT-' . $walletId . '-' . time();
        $existingTx = Transaction::where('reference', $reference)->first();

        if (!$existingTx) {
            Transaction::create([
                'dr_wallet_id' => $walletId, // Wallet is debited externally
                'cr_wallet_id' => $walletId, // Wallet is credited internally
                'amount' => $amount,
                'type' => 'Deposit',
                'status' => 'Success',
                'reference' => $reference,
                'description' => 'Initial test deposit.',
            ]);
            $this->command->info("Initial deposit of ₦" . number_format($amount, 2) . " seeded for User ID: {$wallet->user_id}.");
        } else {
            $this->command->warn("Deposit transaction already exists for Wallet ID: {$walletId}.");
        }
    }
}