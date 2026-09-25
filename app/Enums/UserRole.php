<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case User = 'user';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::User => 'Khách hàng',
            self::Admin => 'Quản trị',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::User => 'gray',
            self::Admin => 'warning',
        };
    }
}
