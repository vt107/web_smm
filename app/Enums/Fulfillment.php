<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum Fulfillment: string implements HasColor, HasLabel
{
    /** Gửi đơn sang provider qua API. */
    case Api = 'api';

    /** Admin xử lý tay và tự cập nhật trạng thái. */
    case Manual = 'manual';

    public function getLabel(): string
    {
        return match ($this) {
            self::Api => 'Qua API provider',
            self::Manual => 'Manual',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Api => 'info',
            self::Manual => 'warning',
        };
    }
}
