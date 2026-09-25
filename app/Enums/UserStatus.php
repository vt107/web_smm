<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Banned = 'banned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Hoạt động',
            self::Banned => 'Bị khoá',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Banned => 'danger',
        };
    }
}
