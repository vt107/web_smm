<?php

namespace App\Jobs;

use App\Enums\RefillStatus;
use App\Exceptions\ProviderException;
use App\Models\OrderRefill;
use App\Services\Providers\ProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendRefillToProvider implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $refillId) {}

    public function handle(ProviderManager $providers): void
    {
        $refill = OrderRefill::query()->with('order.provider')->find($this->refillId);

        if (! $refill || $refill->status !== RefillStatus::Pending || $refill->provider_refill_id) {
            return;
        }

        $order = $refill->order;

        try {
            if (! $order->provider || ! $order->isSentToProvider()) {
                throw new ProviderException('Order was not sent to a provider');
            }

            $refill->forceFill([
                'provider_refill_id' => $providers->for($order->provider)->refill($order->provider_order_id),
                'status' => RefillStatus::Processing,
            ])->save();
        } catch (ProviderException $e) {
            $refill->forceFill([
                'status' => RefillStatus::Rejected,
                'provider_response' => $e->getMessage(),
            ])->save();
        }
    }
}
