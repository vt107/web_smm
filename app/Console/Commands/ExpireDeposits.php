<?php

namespace App\Console\Commands;

use App\Services\Payments\DepositService;
use Illuminate\Console\Command;

class ExpireDeposits extends Command
{
    protected $signature = 'deposits:expire';

    protected $description = 'Chuyển phiếu nạp quá hạn sang expired';

    public function handle(DepositService $deposits): int
    {
        $this->info('Expired '.$deposits->expireOverdue().' deposits');

        return self::SUCCESS;
    }
}
