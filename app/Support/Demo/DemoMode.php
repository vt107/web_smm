<?php

namespace App\Support\Demo;

use Closure;

/**
 * Chế độ demo chỉ xem. Xem config/demo.php.
 */
class DemoMode
{
    /** Số lớp bypass đang mở (cho phép ghi tạm thời). */
    protected static int $bypassDepth = 0;

    /** Test bật guard dù đang chạy CLI. */
    protected static bool $forceGuard = false;

    public static function enabled(): bool
    {
        return (bool) config('demo.enabled');
    }

    /** Guard SQL có đang chặn ghi không: chỉ với request web, không với artisan / queue / test. */
    public static function guarding(): bool
    {
        if (static::$bypassDepth > 0 || ! static::enabled()) {
            return false;
        }

        return static::$forceGuard || ! app()->runningInConsole();
    }

    public static function forceGuard(bool $force = true): void
    {
        static::$forceGuard = $force;
    }

    /**
     * Cho phép ghi trong callback (dùng rất hạn chế, cho các lệnh ghi bắt buộc để xem được trang).
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function bypass(Closure $callback): mixed
    {
        static::$bypassDepth++;

        try {
            return $callback();
        } finally {
            static::$bypassDepth--;
        }
    }

    /** Chặn thao tác có tác dụng phụ ngoài database (gửi Telegram, gọi API, xoá cache...). */
    public static function abortIfEnabled(?string $message = null): void
    {
        if (static::guarding()) {
            throw new DemoModeException($message);
        }
    }

    public static function message(): string
    {
        return (string) config('demo.message');
    }

    /** Được gọi trước mỗi câu SQL (DB::beforeExecuting). */
    public static function guardQuery(string $sql): void
    {
        if (! static::guarding() || ! static::isWriteQuery($sql)) {
            return;
        }

        $table = static::writeTarget($sql);

        if ($table !== null && in_array($table, config('demo.writable_tables', []), true)) {
            return;
        }

        foreach (config('demo.allowed_write_patterns', []) as $pattern) {
            if (preg_match($pattern, ltrim($sql))) {
                return;
            }
        }

        throw new DemoModeException;
    }

    public static function isWriteQuery(string $sql): bool
    {
        return (bool) preg_match('/^\s*(insert|update|delete|replace|truncate|alter|drop|create|rename)\b/i', $sql);
    }

    /** Tên bảng bị ghi (bỏ prefix schema / quote), null nếu không nhận diện được. */
    public static function writeTarget(string $sql): ?string
    {
        $pattern = '/^\s*(?:insert(?:\s+ignore)?\s+into|replace\s+into|update(?:\s+ignore)?|delete\s+from|truncate(?:\s+table)?)\s+([`"\w.]+)/i';

        if (! preg_match($pattern, $sql, $m)) {
            return null;
        }

        $parts = explode('.', str_replace(['`', '"'], '', $m[1]));

        return end($parts) ?: null;
    }

    /** Tất cả tài khoản demo, key => account (kèm label khu vực). */
    public static function accounts(): array
    {
        $accounts = [];

        foreach (config('demo.portals', []) as $portal) {
            foreach ($portal['accounts'] ?? [] as $account) {
                $accounts[$account['key']] = $account + ['portal' => $portal['label']];
            }
        }

        return $accounts;
    }

    /**
     * Tài khoản để điền sẵn vào form đăng nhập: ưu tiên ?demo=<key>, sau đó $defaultKey.
     *
     * @return array{email: string, password: string}|null
     */
    public static function credentials(?string $defaultKey = null): ?array
    {
        if (! static::enabled()) {
            return null;
        }

        $accounts = static::accounts();
        $account = $accounts[(string) request()->query('demo')] ?? $accounts[(string) $defaultKey] ?? null;

        return $account ? ['email' => $account['email'], 'password' => $account['password']] : null;
    }
}
