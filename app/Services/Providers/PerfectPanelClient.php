<?php

namespace App\Services\Providers;

use App\Exceptions\ProviderException;
use App\Exceptions\ProviderRejectedException;
use App\Models\Order;
use App\Models\Provider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** Client cho các panel dùng chuẩn API v2 của PerfectPanel (POST form: key + action). */
class PerfectPanelClient implements ProviderClient
{
    public function __construct(private readonly Provider $provider) {}

    public function services(): array
    {
        $rows = $this->call('services');

        if (! array_is_list($rows)) {
            throw new ProviderException('Unexpected services response');
        }

        return array_map(fn (array $row) => [
            'service' => (string) $row['service'],
            'name' => (string) ($row['name'] ?? ''),
            'type' => (string) ($row['type'] ?? 'Default'),
            'category' => (string) ($row['category'] ?? ''),
            'rate' => (string) ($row['rate'] ?? '0'),
            'min' => (int) ($row['min'] ?? 0),
            'max' => (int) ($row['max'] ?? 0),
            'refill' => filter_var($row['refill'] ?? false, FILTER_VALIDATE_BOOL),
            'cancel' => filter_var($row['cancel'] ?? false, FILTER_VALIDATE_BOOL),
            'dripfeed' => filter_var($row['dripfeed'] ?? false, FILTER_VALIDATE_BOOL),
        ], $rows);
    }

    public function addOrder(Order $order): string
    {
        $params = [
            'service' => $order->service->provider_service_id,
            'link' => $order->link,
        ];

        if (! $order->service->type->isPackage() && $order->service->type->quantityFromLinesOf() === null) {
            $params['quantity'] = $order->quantity;
        }

        if ($order->runs) {
            $params['runs'] = $order->runs;
            $params['interval'] = $order->interval;
        }

        $response = $this->call('add', $params + ($order->extra ?? []), (int) config('smm.orders.send_timeout'));

        if (empty($response['order'])) {
            throw new ProviderException('Provider did not return an order ID');
        }

        return (string) $response['order'];
    }

    public function status(array $orderIds): array
    {
        $response = $this->call('status', ['orders' => implode(',', $orderIds)]);

        $result = [];
        foreach ($orderIds as $id) {
            $row = $response[$id] ?? null;
            $result[(string) $id] = is_array($row) ? $row : ['error' => 'Missing in provider response'];
        }

        return $result;
    }

    public function refill(string $orderId): string
    {
        $response = $this->call('refill', ['order' => $orderId]);

        if (empty($response['refill']) || is_array($response['refill'])) {
            throw new ProviderException($response['refill']['error'] ?? 'Provider did not return a refill ID');
        }

        return (string) $response['refill'];
    }

    public function refillStatus(array $refillIds): array
    {
        $response = $this->call('refill_status', ['refills' => implode(',', $refillIds)]);

        $result = [];
        foreach ($response as $key => $row) {
            // Dạng list: [{"refill": 1, "status": "Completed"}] hoặc dạng map: {"1": {"status": "Completed"}}
            $id = (string) ($row['refill'] ?? $key);
            $status = $row['status'] ?? $row;
            $result[$id] = is_array($status) ? ['error' => (string) ($status['error'] ?? 'Unknown error')] : (string) $status;
        }

        return $result;
    }

    public function cancel(array $orderIds): array
    {
        $response = $this->call('cancel', ['orders' => implode(',', $orderIds)]);

        $result = [];
        foreach ($response as $row) {
            if (! is_array($row) || ! isset($row['order'])) {
                continue;
            }
            $cancel = $row['cancel'] ?? null;
            $result[(string) $row['order']] = is_array($cancel) ? (string) ($cancel['error'] ?? 'Unknown error') : true;
        }

        return $result;
    }

    public function balance(): array
    {
        $response = $this->call('balance');

        return [
            'balance' => (string) ($response['balance'] ?? '0'),
            'currency' => (string) ($response['currency'] ?? 'USD'),
        ];
    }

    /** @return array<mixed> */
    private function call(string $action, array $params = [], int $timeout = 30): array
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout($timeout)
                ->post($this->provider->api_url, ['key' => $this->provider->api_key, 'action' => $action] + $params);
        } catch (ConnectionException $e) {
            throw new ProviderException("Connection failed: {$e->getMessage()}", previous: $e);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new ProviderException("Invalid response (HTTP {$response->status()})");
        }

        if (isset($data['error']) && is_string($data['error'])) {
            throw new ProviderRejectedException($data['error']);
        }

        return $data;
    }
}
