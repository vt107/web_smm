<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TransferStatus: string implements HasColor, HasLabel
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ignored = 'ignored';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unmatched => 'Unmatched',
            self::Matched => 'Matched',
            self::Ignored => 'Ignored',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Unmatched => 'warning',
            self::Matched => 'success',
            self::Ignored => 'gray',
        };
    }
}
