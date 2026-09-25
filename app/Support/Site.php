<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Cấu hình website (tên, logo, SEO, nội dung landing…) do admin sửa ở trang "Website settings".
 * Giá trị lưu trong bảng settings với key "site.*", mặc định ở config/smm.php → site.
 */
final class Site
{
    public static function get(string $key): mixed
    {
        $default = config("smm.site.{$key}");

        try {
            $value = Setting::get("site.{$key}");
        } catch (Throwable) {
            // Chưa chạy migrate (cài mới) → dùng giá trị mặc định.
            return $default;
        }

        return $value === null || $value === '' || $value === [] ? $default : $value;
    }

    public static function name(): string
    {
        return (string) self::get('name');
    }

    public static function showName(): bool
    {
        return (bool) self::get('show_name');
    }

    public static function logoUrl(): string
    {
        return self::fileUrl(self::get('logo')) ?? asset('images/logo.svg');
    }

    public static function faviconUrl(): string
    {
        return self::fileUrl(self::get('favicon')) ?? self::fileUrl(self::get('logo')) ?? asset('favicon.svg');
    }

    public static function ogImageUrl(): string
    {
        return self::fileUrl(self::get('og_image')) ?? asset('images/og-default.png');
    }

    public static function seoTitle(): string
    {
        return (string) (self::get('seo_title') ?: self::name().' – '.self::get('tagline'));
    }

    public static function footerText(): string
    {
        return (string) (self::get('footer_text') ?: '© '.now()->year.' '.self::name().'. All rights reserved.');
    }

    /** @return list<array{question: string, answer: string}> */
    public static function faq(): array
    {
        return array_values(array_filter(
            (array) self::get('faq'),
            fn ($item) => filled($item['question'] ?? null) && filled($item['answer'] ?? null),
        ));
    }

    private static function fileUrl(mixed $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
