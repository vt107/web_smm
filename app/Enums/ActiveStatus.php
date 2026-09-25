<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ActiveStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Hoạt động',
            self::Disabled => 'Tắt',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Disabled => 'gray',
        };
    }
}
