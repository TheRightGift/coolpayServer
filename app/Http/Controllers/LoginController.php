<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Handle an incoming authentication request for web/API.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            
            // Regenerate session for security (standard web practice)
            $request->session()->regenerate();

            // ------------------------------------------------------------------
            // Passport Token Issuance (Personal Access Token Grant)
            // Token is created and sent to the Vue frontend for API calls.
            // ------------------------------------------------------------------
            
            // Create a Personal Access Token for the user
            // We use 'accessToken' property here
            $token = $user->createToken('SPA-Auth-Token')->accessToken;

            // IMPORTANT FIX: 
            // We DO NOT log out of the session here (Auth::logout()) 
            // so the user remains authenticated for the dashboard redirect.
            
            return response()->json([
                'token_data' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
                'user' => $user,
                'remember' => $request->boolean('remember')
            ], 200);
        }

        // Failed authentication attempt
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    /**
     * The apiLogin method simply delegates to the main login method.
     */
    public function apiLogin(Request $request)
    {
        return $this->login($request);
    }

     /**
      * Display the login view.
      */
     public function showLogin()
     {
         return view('auth.login');
     }
}