<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrderSource: string implements HasLabel
{
    case Web = 'web';
    case Api = 'api';
    case Mass = 'mass';

    public function getLabel(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Api => 'API',
            self::Mass => 'Mass order',
        };
    }
}
