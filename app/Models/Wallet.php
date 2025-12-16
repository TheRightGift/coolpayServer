<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['user_id', 'balance', 'tipping_url'];

    /**
     * Get the user that owns the wallet (One-to-One inverse relationship).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the transactions associated with the wallet.
     * * Since a transaction involves a Debit and a Credit wallet, 
     * this needs to return transactions where this wallet is EITHER 
     * the debtor OR the creditor.
     */
    public function transactions()
    {
        // This is the problematic definition if used without arguments, 
        // as it defaults to searching for 'wallet_id'
        // return $this->hasMany(Transaction::class); 
        
        // To get ALL transactions associated with this wallet (either as debtor or creditor), 
        // you cannot use a simple hasMany relationship. You would use a query scope 
        // or a specialized package.
        
        // For now, make sure you are using the explicit relationships:

        return $this->debitTransactions()->union($this->creditTransactions()); 
        // NOTE: This complex union might also be the source of the error if used as is.
        // The safest fix is to ensure the Dashboard only uses the explicit relationships below:
    }

    /**
     * Define the relationships using the custom foreign keys
     */
    public function debitTransactions()
    {
        return $this->hasMany(Transaction::class, 'dr_wallet_id');
    }

    public function creditTransactions()
    {
        return $this->hasMany(Transaction::class, 'cr_wallet_id');
    }


    /**
     * Calculate the actual wallet balance using the transactions ledger.
     * This overrides the simple 'balance' column for accurate reporting.
     */
    public function getActualBalanceAttribute()
    {
        $walletId = $this->id;

        // Fetch all successful transactions involving this wallet (either as Dr or Cr)
        $allTransactions = Transaction::where('status', 'Success')
            ->where(function ($query) use ($walletId) {
                $query->where('cr_wallet_id', $walletId)
                      ->orWhere('dr_wallet_id', $walletId);
            })
            ->get();

        $balance = 0.00;

        foreach ($allTransactions as $transaction) {
            $amount = (float) $transaction->amount;
            
            // Scenario 1: Wallet is the Creditor (Money IN)
            if ($transaction->cr_wallet_id == $walletId) {
                // This covers Tips, Transfers TO this wallet, and Deposits (where Dr == Cr, Type=Deposit)
                $balance += $amount;
            }

            // Scenario 2: Wallet is the Debtor (Money OUT)
            if ($transaction->dr_wallet_id == $walletId) {
                // This covers Transfers FROM this wallet and Withdrawals (where Dr == Cr, Type=Withdrawal)
                $balance -= $amount;
            }
            
            /* * Note on Cr=Dr Transactions (Deposit/Withdrawal):
             * If Cr_Wallet_ID == Dr_Wallet_ID == $walletId:
             * - Scenario 1 runs: $balance += $amount;
             * - Scenario 2 runs: $balance -= $amount;
             * - The net effect is ZERO.
             * * If the transaction is a Deposit/Withdrawal with matching IDs, you must check the 'type'
             * to determine the direction relative to the *external* world.
             */

            // Corrective Logic for Self-Transactions (Deposit/Withdrawal):
            if ($transaction->cr_wallet_id == $walletId && $transaction->dr_wallet_id == $walletId) {
                // If it's a deposit, the amount was added (credit), and then subtracted (debit). Net zero.
                // But a Deposit means the external world gave us money. We need to add it once.
                if ($transaction->type === 'Deposit') {
                    // Since it was cancelled out, we add it back to show the net gain from external funds.
                    $balance += $amount; 
                } 
                // If it's a Withdrawal, the amount was added (credit), and subtracted (debit). Net zero.
                // But a Withdrawal means we gave the external world money. We need to subtract it once.
                elseif ($transaction->type === 'Withdrawal') {
                    // Since it was cancelled out, we subtract it again to show the net loss to external funds.
                    $balance -= $amount; 
                }
            }
        }
        
        // This logic simplifies to: Total Credits - Total Debits, PLUS (Deposit amount) - (Withdrawal amount)
        // Let's use a simpler, query-based approach for performance:

        $totalCredits = $this->creditTransactions()->where('status', 'Success')->sum('amount');
        $totalDebits = $this->debitTransactions()->where('status', 'Success')->sum('amount');

        // Total flow *through* the internal ledger
        $internalBalance = (float) ($totalCredits - $totalDebits);

        // Calculate external movements (where Cr_ID == Dr_ID == $walletId)
        $selfTransactions = Transaction::where('status', 'Success')
                                       ->where('cr_wallet_id', $walletId)
                                       ->where('dr_wallet_id', $walletId);

        $totalDeposits = $selfTransactions->clone()->where('type', 'Deposit')->sum('amount');
        $totalWithdrawals = $selfTransactions->clone()->where('type', 'Withdrawal')->sum('amount');

        // If the balance is calculated purely on internal debits/credits, the self-transactions 
        // will have been accounted for (added and subtracted), resulting in a net zero change.
        // We must re-introduce the external change.
        return $internalBalance + $totalDeposits - $totalWithdrawals;
    }

    /**
     * Add 'actual_balance' to the model's default JSON representation.
     */
    protected $appends = ['actual_balance'];
}