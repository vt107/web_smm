<?php

namespace App\Http\Controllers\Web;

use App\Enums\TelegramEvent;
use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\User;
use App\Services\TelegramNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        // Cho đăng nhập bằng email hoặc username.
        $field = filter_var($credentials['email'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::query()->where($field, $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        RateLimiter::clear($throttleKey);

        // Có bật bảo mật 2 lớp → chưa đăng nhập, chuyển sang bước nhập mã.
        if ($user->hasTwoFactorEnabled()) {
            return app(TwoFactorChallengeController::class)->start($request, $user, $request->boolean('remember'));
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    /** Đăng nhập thật sự (sau khi qua mật khẩu và, nếu có, mã 2 lớp). */
    public function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request): View
    {
        return view('auth.register', ['ref' => $request->query('ref')]);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'ref' => ['nullable', 'string', 'max:20'],
            'terms' => ['accepted'],
        ], ['terms.accepted' => 'Please accept the Terms of Service and Privacy Policy.']);

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
            'currency' => Currency::BASE,
            'referred_by' => filled($data['ref'] ?? null)
                ? User::query()->where('ref_code', strtoupper($data['ref']))->value('id')
                : null,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        app(TelegramNotifier::class)->notify(TelegramEvent::NewUser, '👤 <b>New customer</b>: '.e($user->username).' ('.e($user->email).')');

        return redirect()->route('dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
