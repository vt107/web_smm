<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Processing = 'processing';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Partial = 'partial';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    /** Nhãn dùng trong API (chuẩn PerfectPanel). */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Partial => 'Partial',
            self::Canceled => 'Canceled',
            self::Refunded => 'Refunded',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Partial, self::Canceled, self::Refunded], true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Pending, self::Processing, self::InProgress];
    }

    /** Chuyển trạng thái provider trả về sang trạng thái nội bộ; null nếu không nhận ra. */
    public static function fromProvider(?string $status): ?self
    {
        return match (strtolower(trim((string) $status))) {
            'pending' => self::Pending,
            'processing' => self::Processing,
            'in progress', 'inprogress', 'in_progress' => self::InProgress,
            'completed', 'complete' => self::Completed,
            'partial' => self::Partial,
            'canceled', 'cancelled', 'refunded' => self::Canceled,
            default => null,
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Partial => 'Partial',
            self::Canceled => 'Canceled',
            self::Refunded => 'Refunded',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Processing => 'info',
            self::InProgress => 'info',
            self::Completed => 'success',
            self::Partial => 'warning',
            self::Canceled => 'danger',
            self::Refunded => 'danger',
        };
    }
}
