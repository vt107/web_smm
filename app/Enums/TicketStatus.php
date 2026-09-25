<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Answered = 'answered';
    case CustomerReply = 'customer_reply';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Đang mở',
            self::Answered => 'Đã trả lời',
            self::CustomerReply => 'Khách phản hồi',
            self::Closed => 'Đã đóng',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Answered => 'success',
            self::CustomerReply => 'danger',
            self::Closed => 'gray',
        };
    }
}
