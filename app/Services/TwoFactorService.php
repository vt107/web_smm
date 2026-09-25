<?php

namespace App\Services;

use App\Exceptions\SmmException;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Support\Site;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

/**
 * Bảo mật 2 lớp cho khách: app xác thực (TOTP) + mã khôi phục, và mã 6 số qua email.
 * Phần TOTP / mã khôi phục dùng lại AppAuthentication của Filament để cùng định dạng lưu với trang admin
 * (có chống dùng lại mã).
 */
class TwoFactorService
{
    public const EMAIL_CODE_MINUTES = 10;

    /** Chấp nhận mã lệch ±2 bước 30 giây (bù lệch giờ điện thoại), Filament mặc định ±8. */
    public const CODE_WINDOW = 2;

    private const EMAIL_CODE_MAX_ATTEMPTS = 5;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /** URL otpauth:// để app xác thực quét (vẽ thành mã QR phía trình duyệt). */
    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(Site::name(), $user->email, $secret);
    }

    public function verifyAppCode(string $secret, string $code): bool
    {
        return $this->app()->verifyCode(preg_replace('/\s+/', '', $code), $secret, shouldPreventCodeReuse: true);
    }

    /** Bật app xác thực, trả về mã khôi phục (chỉ hiển thị 1 lần). @return list<string> */
    public function enableApp(User $user, string $secret): array
    {
        $user->saveAppAuthenticationSecret($secret);

        return $this->regenerateRecoveryCodes($user);
    }

    public function disableApp(User $user): void
    {
        $user->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
    }

    /** @return list<string> */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = $this->app()->generateRecoveryCodes();
        $user->saveAppAuthenticationRecoveryCodes(array_map(fn (string $c) => Hash::make($c), $codes));

        return $codes;
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        return $this->app()->verifyRecoveryCode(trim($code), $user);
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->getAppAuthenticationRecoveryCodes() ?? []);
    }

    /** Gửi mã 6 số qua email. Giới hạn 1 lần / phút. */
    public function sendEmailCode(User $user): void
    {
        $limiterKey = "2fa-email-send:{$user->id}";

        if (RateLimiter::tooManyAttempts($limiterKey, 1)) {
            throw new SmmException('Please wait :seconds seconds before requesting a new code', ['seconds' => RateLimiter::availableIn($limiterKey)]);
        }

        RateLimiter::hit($limiterKey, 60);

        $code = (string) random_int(100000, 999999);
        Cache::put($this->emailCacheKey($user), Hash::make($code), now()->addMinutes(self::EMAIL_CODE_MINUTES));
        Cache::forget($this->emailCacheKey($user).':attempts');

        $user->notifyNow(new TwoFactorCode($code, self::EMAIL_CODE_MINUTES));
    }

    public function verifyEmailCode(User $user, string $code): bool
    {
        $key = $this->emailCacheKey($user);
        $hash = Cache::get($key);

        if (! $hash) {
            return false;
        }

        // Tăng bộ đếm TRƯỚC khi kiểm tra (nguyên tử) → gửi song song nhiều mã cũng không vượt giới hạn.
        Cache::add("{$key}:attempts", 0, now()->addMinutes(self::EMAIL_CODE_MINUTES));

        if (Cache::increment("{$key}:attempts") > self::EMAIL_CODE_MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        if (! Hash::check(trim($code), $hash)) {
            return false;
        }

        Cache::forget($key);
        Cache::forget("{$key}:attempts");

        return true;
    }

    /** Admin tắt toàn bộ 2FA cho khách (vd mất điện thoại và mất mã khôi phục). */
    public function reset(User $user): void
    {
        $user->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
            'has_email_authentication' => false,
        ])->save();
    }

    private function emailCacheKey(User $user): string
    {
        return "2fa-email-code:{$user->id}";
    }

    private function app(): AppAuthentication
    {
        return AppAuthentication::make()->recoverable()->codeWindow(self::CODE_WINDOW);
    }
}
