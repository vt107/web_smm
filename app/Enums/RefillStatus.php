<?php

namespace App\Enums;

enum RefillStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Error = 'error';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Error], true);
    }

    public static function fromProvider(?string $status): ?self
    {
        return match (strtolower(trim((string) $status))) {
            'pending' => self::Pending,
            'processing', 'in progress', 'inprogress' => self::Processing,
            'completed', 'complete' => self::Completed,
            'rejected', 'canceled', 'cancelled' => self::Rejected,
            'error' => self::Error,
            default => null,
        };
    }
}
