<?php

namespace App\Enums;

enum Platform: string
{
    case Instagram = 'instagram';
    case TikTok = 'tiktok';
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case Telegram = 'telegram';
    case Twitter = 'twitter';
    case Spotify = 'spotify';
    case Other = 'other';
}
