<?php

namespace App\Http\Controllers;

use App\Models\PaymentLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PaymentLinkController extends Controller
{
    public function store(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'amount' => 'nullable|numeric|min:1',
            'memo' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $link = PaymentLink::create([
            'token' => Str::random(32),
            'wallet_id' => $user->wallet->id,
            'user_id' => $user->id,
            'amount' => $request->amount,
            'memo' => $request->memo,
            'expires_at' => $request->expires_at,
            'status' => 'active',
        ]);

        $baseUrl = config('app.url');
        return response()->json([
            'token' => $link->token,
            'url' => $baseUrl . '/pay/' . $link->token,
            'deep_link' => 'coolpay://pay/' . $link->token,
            'receiver' => ['id' => $user->id, 'name' => $user->name],
            'amount' => $link->amount,
            'expires_at' => $link->expires_at,
            'status' => $link->status,
        ], 201);
    }
}
