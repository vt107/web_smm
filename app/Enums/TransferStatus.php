<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Unmatched = 'unmatched';
    case Matched = 'matched';
    case Ignored = 'ignored';
}
