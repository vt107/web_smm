<?php

namespace App\Notifications;

use App\Support\Site;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Mã đăng nhập 2 lớp gửi qua email (gửi ngay, không qua queue). */
class TwoFactorCode extends Notification
{
    public function __construct(public readonly string $code, public readonly int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.Site::name().' security code')
            ->line('Use this code to continue signing in:')
            ->line("**{$this->code}**")
            ->line("The code expires in {$this->minutes} minutes.")
            ->line('If you did not try to sign in, please change your password.');
    }
}
