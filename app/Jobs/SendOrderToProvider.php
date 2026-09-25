<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Enums\TelegramEvent;
use App\Exceptions\ProviderException;
use App\Models\Order;
use App\Models\Setting;
use App\Services\OrderService;
use App\Services\Providers\ProviderManager;
use App\Services\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gửi đơn sang provider. Lỗi → thử lại theo smm.orders.send_backoff;
 * hết order_max_send_attempts → huỷ đơn và tự hoàn tiền.
 */
class SendOrderToProvider implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public int $orderId) {}

    public function handle(ProviderManager $providers, OrderService $orders): void
    {
        $lock = Cache::lock("order-send:{$this->orderId}", 90);

        if (! $lock->get()) {
            return;
        }

        $retryAfter = null;

        try {
            $order = Order::query()->with(['service', 'provider'])->find($this->orderId);

            if (! $order
                || $order->status !== OrderStatus::Pending
                || $order->isSentToProvider()
                || $order->cancel_requested_at) {
                return;
            }

            try {
                if (! $order->provider) {
                    throw new ProviderException('Provider not found');
                }

                $providerOrderId = $providers->for($order->provider)->addOrder($order);
            } catch (ProviderException $e) {
                $retryAfter = $this->recordFailure($order, $e->getMessage(), $orders);

                return;
            }

            $order->forceFill([
                'provider_order_id' => $providerOrderId,
                'status' => OrderStatus::Processing,
                'provider_error' => null,
                'send_attempts' => $order->send_attempts + 1,
            ])->save();
        } finally {
            $lock->release();

            // Dispatch sau khi nhả khoá, để job kế tiếp lấy được khoá.
            if ($retryAfter !== null) {
                static::dispatch($this->orderId)->delay(now()->addSeconds($retryAfter));
            }
        }
    }

    /** @return int|null Số giây chờ trước lần gửi lại; null nếu đã huỷ đơn. */
    private function recordFailure(Order $order, string $error, OrderService $orders): ?int
    {
        $attempts = $order->send_attempts + 1;

        $order->forceFill([
            'send_attempts' => $attempts,
            'provider_error' => Str::limit($error, 1000),
        ])->save();

        if ($attempts >= (int) Setting::get('order_max_send_attempts')) {
            $orders->applyStatus($order, OrderStatus::Canceled);

            app(TelegramNotifier::class)->notify(TelegramEvent::OrderFailed, implode("\n", [
                "❌ <b>Order #{$order->id} canceled</b> – provider rejected it {$attempts} times (customer refunded)",
                'Provider: '.e($order->provider?->name ?? '—').' · service #'.$order->service_id,
                'Error: '.e(Str::limit($error, 200)),
                url("/admin/orders/{$order->id}"),
            ]));

            return null;
        }

        $backoff = config('smm.orders.send_backoff');

        return $backoff[min($attempts - 1, count($backoff) - 1)];
    }
}
