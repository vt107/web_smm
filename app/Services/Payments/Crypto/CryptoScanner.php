<?php

namespace App\Services\Payments\Crypto;

use App\Models\PaymentMethod;
use Carbon\CarbonInterface;
use RuntimeException;

interface CryptoScanner
{
    /**
     * Lấy các giao dịch token vào ví kể từ $cursor (đã đủ xác nhận).
     *
     * @return array{transfers: list<array{external_id: string, amount: string, sender: ?string, transferred_at: CarbonInterface, block_number?: ?int, confirmations?: ?int, raw: array}>, cursor: ?string}
     *
     * @throws RuntimeException
     */
    public function fetch(PaymentMethod $method, ?string $cursor): array;
}
