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

    protected $description = 'Quét giao dịch vào các ví crypto và tự khớp phiếu nạp';

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
