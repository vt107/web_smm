<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DepositMatchedBy: string implements HasLabel
{
    case Auto = 'auto';
    case UserClaim = 'user_claim';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Auto => 'Tự động',
            self::UserClaim => 'Khách tự nhận',
            self::Admin => 'Admin gán',
        };
    }
}
