<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function logout(Request $request)
    {
        // Revoke active API token (Passport) when present.
        $apiUser = $request->user('api');
        if ($apiUser && method_exists($apiUser, 'token') && $apiUser->token()) {
            $apiUser->token()->revoke();
        }

        // Clear session auth when present.
        if (Auth::check()) {
            Auth::logout();
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Logout successful',
            ], 200);
        }

        return redirect('/');
    }
}
