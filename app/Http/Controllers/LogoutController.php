<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User; // You might not need this if not using apiLogout

class LogoutController extends Controller
{
   public function logout(Request $request)
    {
        // ----------------------------------------------------
        // Passport API Logout: Handle requests from token users
        // ----------------------------------------------------
        if ($request->user('api')) {
            // Revoke the specific access token being used for the current request.
            // This works for Passport as long as the user is authenticated via the 'api' guard.
            $request->user('api')->token()->revoke();

            return response()->json([
                'message' => 'API Logout successful (Passport Token Revoked)'
            ], 200);
        }

        // ----------------------------------------------------
        // Web/Blade Logout: Handle requests from session users
        // ----------------------------------------------------
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // If it's an AJAX/JSON web request, return JSON response
        if ($request->expectsJson()) {
             return response()->json([
                'message' => 'Web Logout successful (Session cleared)'
            ], 200);
        }

        // Otherwise, redirect for standard web logout
        return redirect('/');
    }

    // You should probably delete the apiLogout method unless you have a specific
    // reason for logging out a user by ID without their current token.
    public function apiLogout(Request $request){
        return response()->json([
            'message' => 'This method is generally unsafe/incorrect. Use the standard logout route which revokes the active token.'
        ], 403);
    }
}