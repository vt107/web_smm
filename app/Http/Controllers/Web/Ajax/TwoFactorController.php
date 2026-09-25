<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Exceptions\SmmException;
use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Trang Account → Security: khách tự bật / tắt app xác thực và mã qua email. */
class TwoFactorController extends Controller
{
    private const PENDING_SECRET = '2fa_pending_secret';

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'app_enabled' => filled($user->app_authentication_secret),
            'email_enabled' => $user->has_email_authentication,
            'recovery_codes_left' => $this->twoFactor->remainingRecoveryCodes($user),
            'email' => $user->email,
        ]);
    }

    /** Bước 1: tạo secret tạm (chưa lưu) + URL để vẽ mã QR. */
    public function setupApp(Request $request): JsonResponse
    {
        $secret = $this->twoFactor->generateSecret();
        $request->session()->put(self::PENDING_SECRET, $secret);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $this->twoFactor->otpauthUrl($request->user(), $secret),
        ]);
    }

    /** Bước 2: khách nhập mã trong app để xác nhận → lưu secret, trả mã khôi phục. */
    public function enableApp(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $secret = $request->session()->get(self::PENDING_SECRET) ?? throw new SmmException('Setup has expired, please start again');

        if (! $this->twoFactor->verifyAppCode($secret, $request->input('code'))) {
            throw new SmmException('The code is invalid, please try again');
        }

        $request->session()->forget(self::PENDING_SECRET);

        return response()->json([
            'message' => 'Authenticator app enabled',
            'recovery_codes' => $this->twoFactor->enableApp($request->user(), $secret),
        ]);
    }

    public function disableApp(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $this->twoFactor->disableApp($request->user());

        return response()->json(['message' => 'Authenticator app disabled']);
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        if (blank($request->user()->app_authentication_secret)) {
            throw new SmmException('Enable the authenticator app first');
        }

        return response()->json([
            'message' => 'New recovery codes generated',
            'recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($request->user()),
        ]);
    }

    /** Gửi mã để xác nhận email nhận được thư trước khi bật. */
    public function sendEmailCode(Request $request): JsonResponse
    {
        $this->twoFactor->sendEmailCode($request->user());

        return response()->json(['message' => 'We sent a 6-digit code to '.$request->user()->email]);
    }

    public function enableEmail(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        if (! $this->twoFactor->verifyEmailCode($request->user(), $request->input('code'))) {
            throw new SmmException('The code is invalid or has expired');
        }

        $request->user()->toggleEmailAuthentication(true);

        return response()->json(['message' => 'Email codes enabled']);
    }

    public function disableEmail(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $request->user()->toggleEmailAuthentication(false);

        return response()->json(['message' => 'Email codes disabled']);
    }
}
