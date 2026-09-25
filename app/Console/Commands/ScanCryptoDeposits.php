<?php

namespace App\Console\Commands;

use App\Enums\PaymentGateway;
use App\Models\PaymentMethod;
use App\Services\Payments\Crypto\CryptoScanService;
use Illuminate\Console\Command;
use Throwable;

class ScanCryptoDeposits extends Command
{
    protected $signature = 'deposits:scan';

    protected $description = 'Scan crypto wallets for incoming transfers and match deposits';

    public function handle(CryptoScanService $scanner): int
    {
        $methods = PaymentMethod::query()->active()->where('gateway', PaymentGateway::Crypto)->get();

        foreach ($methods as $method) {
            try {
                $matched = $scanner->scan($method);
                $this->info("[{$method->name}] matched {$matched} deposits");
            } catch (Throwable $e) {
                report($e);
                $this->error("[{$method->name}] {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
