<?php

namespace App\Enums;

enum DepositMatchedBy: string
{
    case Auto = 'auto';
    case UserClaim = 'user_claim';
    case Admin = 'admin';
}
