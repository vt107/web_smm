<?php

namespace App\Services\Payments\Crypto;

use App\Models\PaymentMethod;
use App\Services\Payments\DepositService;
use InvalidArgumentException;

class CryptoScanService
{
    public function __construct(private readonly DepositService $deposits) {}

    /** Quét ví của 1 kênh crypto, lưu giao dịch mới và tự khớp phiếu nạp. Trả về số phiếu đã khớp. */
    public function scan(PaymentMethod $method): int
    {
        $result = $this->scannerFor($method)->fetch($method, $method->scan_cursor);
        $matched = 0;

        foreach ($result['transfers'] as $data) {
            $transfer = $this->deposits->recordTransfer($method, $data);

            if ($transfer->wasRecentlyCreated && $this->deposits->autoMatch($transfer)) {
                $matched++;
            }
        }

        $method->forceFill([
            'scan_cursor' => $result['cursor'],
            'last_scanned_at' => now(),
        ])->save();

        return $matched;
    }

    private function scannerFor(PaymentMethod $method): CryptoScanner
    {
        return match ($method->configValue('network')) {
            'tron' => app(TronScanner::class),
            'bsc', 'evm' => app(EvmScanner::class),
            default => throw new InvalidArgumentException("Unsupported crypto network for payment method #{$method->id}"),
        };
    }
}
