<?php

namespace App\Support;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Áp dụng cấu hình SMTP do admin nhập ở "Website settings → Email" vào config mail của Laravel.
 * Chưa nhập host → giữ cấu hình trong .env (mặc định MAIL_MAILER=log, không gửi mail thật).
 */
final class MailSettings
{
    public static function isConfigured(): bool
    {
        return filled(Site::get('mail_host'));
    }

    public static function apply(): void
    {
        if (! self::isConfigured()) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => Site::get('mail_host'),
            'mail.mailers.smtp.port' => (int) Site::get('mail_port'),
            // smtps = SSL (cổng 465); smtp = STARTTLS khi máy chủ hỗ trợ (cổng 587/25).
            'mail.mailers.smtp.scheme' => Site::get('mail_security') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.username' => Site::get('mail_username'),
            'mail.mailers.smtp.password' => self::password(),
            'mail.from.address' => Site::get('mail_from_address') ?: Site::get('mail_username'),
            'mail.from.name' => Site::get('mail_from_name') ?: Site::name(),
        ]);

        // Mailer có thể đã được tạo với cấu hình cũ (vd ngay sau khi admin lưu) → tạo lại.
        Mail::purge('smtp');
    }

    public static function encryptPassword(string $password): string
    {
        return Crypt::encryptString($password);
    }

    private static function password(): ?string
    {
        $encrypted = Site::get('mail_password');

        try {
            return $encrypted ? Crypt::decryptString($encrypted) : null;
        } catch (Throwable) {
            return null; // APP_KEY đổi → admin cần nhập lại mật khẩu
        }
    }
}
