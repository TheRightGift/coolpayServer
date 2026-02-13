<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'If that email exists, a reset token was created'], 200);
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        // Send email with token (simple text). In production, swap to Mailable with link.
        try {
            \Mail::raw('Your CoolPay password reset token: ' . $token, function ($message) use ($user) {
                $message->to($user->email)->subject('CoolPay Password Reset');
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send reset email', ['email' => $user->email, 'error' => $e->getMessage()]);
        }

        Log::info('Password reset token generated', ['email' => $user->email]);

        return response()->json([
            'message' => 'If the email exists, a reset token has been sent.',
        ], 200);
    }

    public function resetWithToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();
        if (!$record) {
            return response()->json(['message' => 'Invalid token'], 400);
        }
        if (!Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Invalid token'], 400);
        }
        // Expiry check: 60 minutes
        if ($record->created_at && now()->subMinutes(60)->greaterThan($record->created_at)) {
            return response()->json(['message' => 'Token expired'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Delete token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Password reset successful'], 200);
    }
}
