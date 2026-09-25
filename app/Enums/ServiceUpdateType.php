<?php

namespace App\Enums;

enum ServiceUpdateType: string
{
    case New = 'new';
    case PriceIncrease = 'price_increase';
    case PriceDecrease = 'price_decrease';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
}
