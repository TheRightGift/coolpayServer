<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Return a listing of the resource for the authenticated user (paginated).
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $walletId = $user->wallet->id;
        $query = Transaction::where('cr_wallet_id', $walletId)->orWhere('dr_wallet_id', $walletId)
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return TransactionResource::collection($query->paginate(20));
    }

    /**
     * Return a listing of the resource (non-paginated) for compatibility.
     */
    public function getUserTransactions()                                                                                            
    {
        $user = Auth::user();
        $walletId = $user->wallet->id;
        $transactions = Transaction::where('cr_wallet_id', $walletId)->orWhere('dr_wallet_id', $walletId)->get();
        return TransactionResource::collection($transactions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cr_wallet_id' => 'required|exists:wallets,id', //from wallet
            'dr_wallet_id' => 'required|exists:wallets,id', //to wallet
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'status' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $transaction = Transaction::create($validated);
        $transaction->load('creditorWallet', 'debtorWallet');

        return new TransactionResource($transaction);
    }

    /**
     * Display the specified resource.
     */
    public function show(Transaction $transaction)
    {
        $transaction = Transaction::findOrFail($transaction->id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        // Load the wallet relationship to include wallet details in the response
        $transaction->load('creditorWallet', 'debtorWallet');
        return new TransactionResource($transaction);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request,Transaction $transaction)
    {
        $validated = $request->validate([
            'cr_wallet_id' => 'required|exists:wallets,id',
            'dr_wallet_id' => 'required|exists:wallets,id',
            'amount' => 'required|numeric',
            'type' => 'required|string',
            'status' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $transaction->update($validated);
        $transaction->load('creditorWallet', 'debtorWallet');

        return new TransactionResource($transaction);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted successfully']);
    }
}
