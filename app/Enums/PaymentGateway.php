<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentGateway: string implements HasLabel
{
    case Sepay = 'sepay';
    case Crypto = 'crypto';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sepay => 'SePay (bank transfer)',
            self::Crypto => 'Crypto (wallet scan)',
        };
    }
}
