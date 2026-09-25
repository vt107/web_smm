<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Sepay = 'sepay';
    case Crypto = 'crypto';
}
