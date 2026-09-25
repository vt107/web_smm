<?php

namespace App\Exceptions;

class InsufficientBalanceException extends SmmException
{
    public function __construct()
    {
        parent::__construct('Not enough funds on balance');
    }
}
