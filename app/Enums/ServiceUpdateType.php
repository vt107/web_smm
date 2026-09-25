<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ServiceUpdateType: string implements HasColor, HasLabel
{
    case New = 'new';
    case PriceIncrease = 'price_increase';
    case PriceDecrease = 'price_decrease';
    case Enabled = 'enabled';
    case Disabled = 'disabled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New service',
            self::PriceIncrease => 'Price increased',
            self::PriceDecrease => 'Discount',
            self::Enabled => 'Re-enabled',
            self::Disabled => 'Disabled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::PriceIncrease => 'danger',
            self::PriceDecrease => 'success',
            self::Enabled => 'success',
            self::Disabled => 'gray',
        };
    }
}
