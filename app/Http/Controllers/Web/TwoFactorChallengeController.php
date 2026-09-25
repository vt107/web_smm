<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\SmmException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Bước 2 khi đăng nhập: nhập mã từ app xác thực, mã qua email, hoặc mã khôi phục.
 * Thông tin chờ xác thực lưu trong session "2fa" (hết hạn sau 10 phút).
 */
class TwoFactorChallengeController extends Controller
{
    private const SESSION_KEY = '2fa';

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function start(Request $request, User $user, bool $remember): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, [
            'user_id' => $user->id,
            'remember' => $remember,
            'expires_at' => now()->addMinutes(10)->timestamp,
        ]);

        // Chỉ bật email → gửi mã luôn.
        if (! $user->app_authentication_secret && $user->has_email_authentication) {
            $this->sendEmail($user);
        }

        return redirect()->route('two-factor.challenge');
    }

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge', [
            'hasApp' => filled($user->app_authentication_secret),
            'hasEmail' => $user->has_email_authentication,
            'maskedEmail' => preg_replace('/(?<=.).(?=[^@]*@)/', '•', $user->email),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request) ?? throw ValidationException::withMessages(['code' => 'Your session has expired. Please log in again.']);

        $data = $request->validate([
            'method' => ['required', 'in:app,email,recovery'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        $valid = match ($data['method']) {
            'app' => filled($user->app_authentication_secret) && $this->twoFactor->verifyAppCode($user->app_authentication_secret, $data['code']),
            'recovery' => filled($user->app_authentication_secret) && $this->twoFactor->useRecoveryCode($user, $data['code']),
            'email' => $user->has_email_authentication && $this->twoFactor->verifyEmailCode($user, $data['code']),
        };

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'The code is invalid or has expired.'])->redirectTo(route('two-factor.challenge', ['method' => $data['method']]));
        }

        $remember = (bool) $request->session()->pull(self::SESSION_KEY)['remember'];

        return app(AuthController::class)->completeLogin($request, $user, $remember);
    }

    public function sendEmailCode(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user || ! $user->has_email_authentication) {
            return redirect()->route('login');
        }

        $this->sendEmail($user);

        return redirect()->route('two-factor.challenge', ['method' => 'email'])->with('status', 'We sent a new code to your email.');
    }

    private function sendEmail(User $user): void
    {
        try {
            $this->twoFactor->sendEmailCode($user);
        } catch (SmmException $e) {
            session()->flash('status', $e->localized());
        }
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! $pending || $pending['expires_at'] < now()->timestamp) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return User::query()->find($pending['user_id']);
    }
}
