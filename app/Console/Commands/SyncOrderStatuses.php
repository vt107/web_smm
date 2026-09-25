<?php

namespace App\Console\Commands;

use App\Enums\ActiveStatus;
use App\Enums\OrderStatus;
use App\Exceptions\ProviderException;
use App\Models\Order;
use App\Models\Provider;
use App\Services\OrderService;
use App\Services\Providers\ProviderManager;
use Illuminate\Console\Command;

class SyncOrderStatuses extends Command
{
    protected $signature = 'orders:sync {--limit=2000 : Max orders per provider per run}';

    protected $description = 'Sync active order statuses from providers and refund partial/canceled orders';

    public function handle(ProviderManager $providers, OrderService $orders): int
    {
        $batch = (int) config('smm.orders.sync_batch');
        $recheckBefore = now()->subMinutes((int) config('smm.orders.recheck_after_minutes'));

        foreach (Provider::query()->where('status', ActiveStatus::Active)->get() as $provider) {
            $pending = Order::query()
                ->where('provider_id', $provider->id)
                ->whereNotNull('provider_order_id')
                ->whereIn('status', OrderStatus::active())
                ->where(fn ($q) => $q->whereNull('last_checked_at')->orWhere('last_checked_at', '<', $recheckBefore))
                ->orderByRaw('last_checked_at IS NOT NULL, last_checked_at')
                ->limit((int) $this->option('limit'))
                ->get();

            $client = $providers->for($provider);

            foreach ($pending->chunk($batch) as $chunk) {
                try {
                    $rows = $client->status($chunk->pluck('provider_order_id')->all());
                } catch (ProviderException $e) {
                    $this->error("[{$provider->name}] {$e->getMessage()}");

                    continue 2;
                }

                foreach ($chunk as $order) {
                    $orders->applyProviderStatus($order, $rows[$order->provider_order_id]);
                }
            }

            $this->info("[{$provider->name}] checked {$pending->count()} orders");
        }

        return self::SUCCESS;
    }
}
