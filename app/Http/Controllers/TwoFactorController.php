<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function enable(Request $request)
    {
        $user = auth()->user();

        // Start 2FA setup in unverified state; user must verify OTP before enable is finalized.
        $secret = $this->google2fa->generateSecretKey();
        $user->two_factor_secret = $secret;
        $user->two_factor_enabled = false;
        $user->save();

        $qrCode = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $qrCodeSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(200)->generate($qrCode);
        $qrCodeBase64 = base64_encode($qrCodeSvg);

        return response()->json([
            'message' => '2FA setup started. Scan QR and verify with your OTP code.',
            'qr_code' => 'data:image/svg+xml;base64,' . $qrCodeBase64,
            'secret' => $secret,
            'requires_verification' => true,
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|min:6|max:8']);

        $user = auth()->user();

        if (!$user->two_factor_secret) {
            return response()->json(['message' => '2FA setup has not been started'], 400);
        }

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Invalid 2FA code'], 400);
        }

        $user->two_factor_enabled = true;
        $user->save();

        return response()->json(['message' => '2FA enabled successfully']);
    }

    public function disable(Request $request)
    {
        $user = auth()->user();
        $user->two_factor_secret = null;
        $user->two_factor_enabled = false;
        $user->save();

        return response()->json(['message' => '2FA disabled']);
    }
}