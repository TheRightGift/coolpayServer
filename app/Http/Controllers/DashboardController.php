<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show the dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Dashboard data',
                'user' => $user,
                'stats' => [
                    'total_tokens' => $user->tokens()->count(),
                    // 'last_login' => $user->updated_at,
                ]
            ]);
        }

        return view('dashboard', compact('user'));
    }
}
