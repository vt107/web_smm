<?php

namespace App\Filament\Support;

use App\Support\Money;

/** Định dạng hiển thị dùng chung trong trang admin. */
final class Fmt
{
    public static function usd(mixed $value): string
    {
        return $value === null || $value === '' ? '—' : '$'.Money::format($value);
    }
}
