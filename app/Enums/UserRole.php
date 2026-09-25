<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case User = 'user';
    case Support = 'support';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'Customer',
            self::Support => 'Support staff',
            self::Admin => 'Admin',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::User => 'gray',
            self::Support => 'info',
            self::Admin => 'warning',
        };
    }
}
