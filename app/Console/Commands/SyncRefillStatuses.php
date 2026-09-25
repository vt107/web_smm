<?php

namespace App\Console\Commands;

use App\Enums\RefillStatus;
use App\Exceptions\ProviderException;
use App\Models\OrderRefill;
use App\Services\Providers\ProviderManager;
use Illuminate\Console\Command;

class SyncRefillStatuses extends Command
{
    protected $signature = 'refills:sync';

    protected $description = 'Đồng bộ trạng thái refill đang xử lý từ provider';

    public function handle(ProviderManager $providers): int
    {
        $refills = OrderRefill::query()
            ->with('order.provider')
            ->where('status', RefillStatus::Processing)
            ->whereNotNull('provider_refill_id')
            ->get()
            ->filter(fn (OrderRefill $r) => $r->order->provider !== null);

        foreach ($refills->groupBy('order.provider_id') as $group) {
            $provider = $group->first()->order->provider;

            foreach ($group->chunk((int) config('smm.orders.sync_batch')) as $chunk) {
                try {
                    $rows = $providers->for($provider)->refillStatus($chunk->pluck('provider_refill_id')->all());
                } catch (ProviderException $e) {
                    $this->error("[{$provider->name}] {$e->getMessage()}");

                    continue 2;
                }

                foreach ($chunk as $refill) {
                    $row = $rows[$refill->provider_refill_id] ?? null;
                    $status = is_string($row) ? RefillStatus::fromProvider($row) : null;

                    if ($status && $status !== $refill->status) {
                        $refill->update(['status' => $status]);
                    }
                }
            }
        }

        return self::SUCCESS;
    }
}
