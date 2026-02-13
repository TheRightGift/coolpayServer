<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // --- ADDITIONS START ---
            'last_login_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            // --- ADDITIONS END ---
        ];
    }

    public function wallet()
    {
        // Relationship is correctly defined as one-to-one
        return $this->hasOne(Wallet::class); 
    }

    public function bankDetails()
    {
        return $this->hasOne(BankDetail::class);
    }
    
    // --- ADDITIONS START ---
    public function drTransactions()
    {
        // A user's wallet can be the debtor for many transactions
        return $this->hasManyThrough(Transaction::class, Wallet::class, 'user_id', 'dr_wallet_id', 'id', 'id');
    }

    public function crTransactions()
    {
        // A user's wallet can be the creditor for many transactions
        return $this->hasManyThrough(Transaction::class, Wallet::class, 'user_id', 'cr_wallet_id', 'id', 'id');
    }
    // --- ADDITIONS END ---
}