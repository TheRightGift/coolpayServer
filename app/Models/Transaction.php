<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'dr_wallet_id',
        'cr_wallet_id',
        'amount',
        'type',
        'status',
        'reference',
        'description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'type' => 'string', // Handled by ENUM in DB, cast to string in model
        'status' => 'string', // Handled by ENUM in DB, cast to string in model
        'deleted_at' => 'datetime',
    ];


    // --- Relationships ---

    /**
     * Get the Wallet that is being Debited (the source of funds).
     */
    public function debtorWallet()
    {
        return $this->belongsTo(Wallet::class, 'dr_wallet_id');
    }

    /**
     * Get the Wallet that is being Credited (the destination of funds).
     */
    public function creditorWallet()
    {
        return $this->belongsTo(Wallet::class, 'cr_wallet_id');
    }
}