<?php

namespace App\Enums;

enum TicketCategory: string
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
}
