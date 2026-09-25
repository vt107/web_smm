<?php

namespace App\Services\Payments\Crypto;

use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Quét giao dịch TRC20 (vd USDT) vào ví qua TronGrid. Cursor = block_timestamp (ms). */
class TronScanner implements CryptoScanner
{
    private const MAX_PAGES = 5;

    public function fetch(PaymentMethod $method, ?string $cursor): array
    {
        $address = (string) $method->configValue('address');
        $contract = (string) $method->configValue('token_contract');
        $decimals = (int) $method->configValue('token_decimals', 6);
        $minTimestamp = $cursor !== null ? (int) $cursor : now()->subDay()->getTimestampMs();

        $params = [
            'only_to' => 'true',
            'only_confirmed' => 'true',
            'contract_address' => $contract,
            'limit' => 200,
            'order_by' => 'block_timestamp,asc',
            'min_timestamp' => $minTimestamp,
        ];

        $url = rtrim(config('smm.crypto.tron.api_url'), '/')."/v1/accounts/{$address}/transactions/trc20";
        $headers = array_filter(['TRON-PRO-API-KEY' => $method->configValue('api_key')]);
        $transfers = [];
        $maxTimestamp = $minTimestamp;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = Http::withHeaders($headers)->timeout(20)->get($url, $params);

            if (! $response->successful() || $response->json('success') === false) {
                throw new RuntimeException("TronGrid error (HTTP {$response->status()}): ".$response->body());
            }

            foreach ($response->json('data', []) as $tx) {
                if (($tx['type'] ?? 'Transfer') !== 'Transfer'
                    || strcasecmp((string) ($tx['to'] ?? ''), $address) !== 0
                    || ($contract !== '' && strcasecmp((string) ($tx['token_info']['address'] ?? ''), $contract) !== 0)) {
                    continue;
                }

                $transfers[] = [
                    'external_id' => (string) $tx['transaction_id'],
                    'amount' => bcdiv((string) $tx['value'], bcpow('10', (string) $decimals), 8),
                    'sender' => $tx['from'] ?? null,
                    'transferred_at' => Carbon::createFromTimestampMs($tx['block_timestamp']),
                    'raw' => $tx,
                ];

                $maxTimestamp = max($maxTimestamp, (int) $tx['block_timestamp']);
            }

            $fingerprint = $response->json('meta.fingerprint');

            if (! $fingerprint) {
                break;
            }

            $params['fingerprint'] = $fingerprint;
        }

        return ['transfers' => $transfers, 'cursor' => (string) $maxTimestamp];
    }
}
