<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $wallet = Wallet::create([
            'user_id' => $user->id,
            'balance' => 0,
        ]);

        $token = $user->createToken('SPA-Auth-Token')->accessToken;

        return response()->json([
            'token_data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
            'user' => $user,
            'wallet' => $wallet,
        ], 201);
    }
}
