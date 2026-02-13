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
     * Canonical wallet balance for API responses.
     *
     * We intentionally return the persisted balance column as source-of-truth,
     * because debit/credit side-effects are applied atomically in payment flows
     * and webhooks/reconciliation.
     */
    public function getActualBalanceAttribute()
    {
        return (float) $this->balance;
    }

    /**
     * Add 'actual_balance' to the model's default JSON representation.
     */
    protected $appends = ['actual_balance'];
}