<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Platform: string implements HasLabel
{
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case Telegram = 'telegram';
    case Twitter = 'twitter';
    case Spotify = 'spotify';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::TikTok => 'TikTok',
            self::YouTube => 'YouTube',
            self::Facebook => 'Facebook',
            self::Telegram => 'Telegram',
            self::Twitter => 'Twitter (X)',
            self::Spotify => 'Spotify',
            self::Other => 'Other',
        };
    }
}
