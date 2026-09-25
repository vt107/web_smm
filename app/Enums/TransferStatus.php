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
            self::Unmatched => 'Chưa khớp',
            self::Matched => 'Đã khớp',
            self::Ignored => 'Bỏ qua',
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
