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
        'external_ref',
        'initiator_user_id',
        'description',
        'meta',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'type' => 'string', // payment | payout
        'status' => 'string', // pending | success | failed | cancelled
        'deleted_at' => 'datetime',
        'expires_at' => 'datetime',
        'meta' => 'array',
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