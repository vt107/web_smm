<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\SendResult;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class SendPendingOrders extends Command
{
    protected $signature = 'orders:send {--limit=200 : Max orders per run}';

    protected $description = 'Send (or resend) orders that have not reached the provider yet';

    public function handle(OrderService $orders): int
    {
        $due = Order::query()
            ->where('status', OrderStatus::Pending)
            ->whereNull('provider_order_id')
            ->whereNotNull('provider_id')
            ->whereNull('cancel_requested_at')
            ->where('next_send_at', '<=', now())
            ->orderBy('next_send_at')
            ->limit((int) $this->option('limit'))
            ->get();

        $down = [];
        $sent = 0;

        foreach ($due as $order) {
            // Provider vừa lỗi mạng/timeout → bỏ qua các đơn còn lại của nó ở lượt này, khỏi chờ timeout từng đơn.
            if (isset($down[$order->provider_id])) {
                continue;
            }

            $result = $orders->sendToProvider($order);

            if ($result === SendResult::Failed) {
                $down[$order->provider_id] = true;
            }

            $sent += (int) ($result === SendResult::Sent);
        }

        $this->info("Sent {$sent}/{$due->count()} orders");

        return self::SUCCESS;
    }
}
