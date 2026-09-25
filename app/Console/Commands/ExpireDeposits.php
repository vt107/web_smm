<?php

namespace App\Console\Commands;

use App\Services\Payments\DepositService;
use Illuminate\Console\Command;

class ExpireDeposits extends Command
{
    protected $signature = 'deposits:expire';

    protected $description = 'Mark overdue deposits as expired';

    public function handle(DepositService $deposits): int
    {
        $this->info('Expired '.$deposits->expireOverdue().' deposits');

        return self::SUCCESS;
    }
}
