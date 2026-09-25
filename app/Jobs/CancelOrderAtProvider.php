<?php

namespace App\Jobs;

use App\Exceptions\ProviderException;
use App\Models\Order;
use App\Services\Providers\ProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Gửi yêu cầu huỷ sang provider. Trạng thái canceled + hoàn tiền đến từ lần đồng bộ status sau đó. */
class CancelOrderAtProvider implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $orderId) {}

    public function handle(ProviderManager $providers): void
    {
        $order = Order::query()->with('provider')->find($this->orderId);

        if (! $order || $order->status->isFinal() || ! $order->isSentToProvider() || ! $order->provider) {
            return;
        }

        try {
            $result = $providers->for($order->provider)->cancel([$order->provider_order_id]);
            $outcome = $result[$order->provider_order_id] ?? 'No response from provider';
        } catch (ProviderException $e) {
            $outcome = $e->getMessage();
        }

        if ($outcome !== true) {
            // Provider từ chối → mở lại để khách có thể yêu cầu huỷ lần nữa.
            $order->forceFill([
                'cancel_requested_at' => null,
                'provider_error' => "Cancel: {$outcome}",
            ])->save();
        }
    }
}
