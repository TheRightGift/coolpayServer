<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

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

            // If 2FA is enabled, require OTP verification before issuing API token.
            if ($user->two_factor_enabled && $user->two_factor_secret) {
                $challengeToken = Str::random(64);
                Cache::put(
                    '2fa_login:' . $challengeToken,
                    [
                        'user_id' => $user->id,
                        'remember' => $request->boolean('remember'),
                    ],
                    now()->addMinutes(5)
                );

                // Drop authenticated session until OTP is verified.
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return response()->json([
                    'requires_2fa' => true,
                    'challenge_token' => $challengeToken,
                    'message' => '2FA code required',
                ], 200);
            }

            $token = $user->createToken('SPA-Auth-Token')->accessToken;

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

    public function verifyLogin2fa(Request $request)
    {
        $validated = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string', 'min:6', 'max:8'],
        ]);

        $cacheKey = '2fa_login:' . $validated['challenge_token'];
        $challenge = Cache::get($cacheKey);

        if (!$challenge || empty($challenge['user_id'])) {
            return response()->json(['message' => '2FA session expired. Please login again.'], 400);
        }

        $user = \App\Models\User::find($challenge['user_id']);
        if (!$user || !$user->two_factor_enabled || !$user->two_factor_secret) {
            Cache::forget($cacheKey);
            return response()->json(['message' => '2FA is not available for this account'], 400);
        }

        $google2fa = new Google2FA();
        $isValid = $google2fa->verifyKey($user->two_factor_secret, $validated['code']);

        if (!$isValid) {
            return response()->json(['message' => 'Invalid 2FA code'], 400);
        }

        Cache::forget($cacheKey);

        // Restore web session auth so /dashboard (web guard) works after 2FA login.
        Auth::login($user, (bool) ($challenge['remember'] ?? false));
        $request->session()->regenerate();

        $token = $user->createToken('SPA-Auth-Token')->accessToken;

        return response()->json([
            'token_data' => [
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
            'user' => $user,
            'remember' => (bool) ($challenge['remember'] ?? false),
        ], 200);
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