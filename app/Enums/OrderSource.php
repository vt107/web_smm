<?php

namespace App\Enums;

enum OrderSource: string
{
    case Web = 'web';
    case Api = 'api';
    case Mass = 'mass';
}
