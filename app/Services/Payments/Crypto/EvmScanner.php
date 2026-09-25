<?php

namespace App\Services\Payments\Crypto;

use App\Models\PaymentMethod;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Quét giao dịch token ERC20/BEP20 vào ví qua Etherscan API V2 (`chainid` chọn mạng, BSC = 56).
 * Cursor = block number cuối cùng đã đủ xác nhận.
 */
class EvmScanner implements CryptoScanner
{
    public function fetch(PaymentMethod $method, ?string $cursor): array
    {
        $address = strtolower((string) $method->configValue('address'));
        $decimals = (int) $method->configValue('token_decimals', 18);
        $minConfirmations = (int) $method->configValue('min_confirmations', 15);
        $startBlock = $cursor ?? (string) $method->configValue('start_block', 0);

        try {
            $response = $this->requestTokenTransfers($method, $address, $startBlock);
        } catch (ConnectionException) {
            // URL chứa apikey → không đưa thông báo gốc vào log.
            throw new RuntimeException('Could not connect to the block explorer');
        }

        $result = $response->json('result');

        if (! $response->successful() || ! is_array($result)) {
            // status "0" + "No transactions found" là bình thường, các lỗi khác (key sai, rate limit) thì báo lỗi.
            if ($response->json('message') === 'No transactions found') {
                return ['transfers' => [], 'cursor' => $cursor];
            }

            throw new RuntimeException("Explorer error (HTTP {$response->status()}): ".$response->body());
        }

        $transfers = [];
        $lastBlock = $cursor;

        foreach ($result as $tx) {
            // Kết quả tăng dần theo block: gặp giao dịch chưa đủ xác nhận thì dừng, lần sau quét tiếp từ đây.
            if ((int) ($tx['confirmations'] ?? 0) < $minConfirmations) {
                break;
            }

            $lastBlock = (string) $tx['blockNumber'];

            if (strtolower((string) ($tx['to'] ?? '')) !== $address) {
                continue;
            }

            $transfers[] = [
                'external_id' => (string) $tx['hash'],
                'amount' => bcdiv((string) $tx['value'], bcpow('10', (string) ($tx['tokenDecimal'] ?? $decimals)), 8),
                'sender' => $tx['from'] ?? null,
                'transferred_at' => Carbon::createFromTimestamp((int) $tx['timeStamp']),
                'block_number' => (int) $tx['blockNumber'],
                'confirmations' => (int) $tx['confirmations'],
                'raw' => $tx,
            ];
        }

        return ['transfers' => $transfers, 'cursor' => $lastBlock];
    }

    private function requestTokenTransfers(PaymentMethod $method, string $address, ?string $startBlock): Response
    {
        return Http::timeout(20)->get(config('smm.crypto.evm.api_url'), [
            'chainid' => $method->configValue('chain_id', 56),
            'module' => 'account',
            'action' => 'tokentx',
            'contractaddress' => $method->configValue('token_contract'),
            'address' => $address,
            'startblock' => $startBlock,
            'endblock' => 99999999,
            'page' => 1,
            'offset' => 200,
            'sort' => 'asc',
            'apikey' => $method->configValue('api_key'),
        ]);
    }
}
