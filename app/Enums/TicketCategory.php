<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketCategory: string implements HasLabel
{
    case Refill = 'refill';
    case Cancel = 'cancel';
    case SpeedUp = 'speed_up';
    case Payment = 'payment';
    case Other = 'other';

    /** Loại ticket bắt buộc kèm order ID. */
    public function requiresOrders(): bool
    {
        return in_array($this, [self::Refill, self::Cancel, self::SpeedUp], true);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Refill => 'Refill',
            self::Cancel => 'Huỷ đơn',
            self::SpeedUp => 'Tăng tốc',
            self::Payment => 'Thanh toán',
            self::Other => 'Khác',
        };
    }
}
