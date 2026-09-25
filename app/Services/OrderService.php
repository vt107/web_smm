<?php

namespace App\Services;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\RefillStatus;
use App\Enums\TransactionType;
use App\Exceptions\SmmException;
use App\Jobs\CancelOrderAtProvider;
use App\Jobs\SendOrderToProvider;
use App\Jobs\SendRefillToProvider;
use App\Models\Order;
use App\Models\OrderRefill;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderService
{
    public const MASS_ORDER_MAX_LINES = 100;

    public function __construct(
        private readonly BalanceService $balance,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Đặt 1 đơn: validate → trừ tiền + tạo đơn trong 1 transaction → gửi provider sau khi commit.
     *
     * @param  array<string, mixed>  $input  link, quantity, runs, interval + tham số riêng theo loại dịch vụ
     *
     * @throws SmmException
     */
    public function place(User $user, Service $service, array $input, OrderSource $source = OrderSource::Web): Order
    {
        if (! $user->isActive()) {
            throw new SmmException('Your account is suspended');
        }

        if (! $service->isActive() || $service->trashed()) {
            throw new SmmException('Service is not available');
        }

        $data = $this->validate($service, $input);
        $quantity = $this->resolveQuantity($service, $data);
        $runs = isset($data['runs']) ? (int) $data['runs'] : null;
        $totalQuantity = $quantity * ($runs ?: 1);

        $charge = $this->pricing->charge($service, $user, $totalQuantity);

        if (! Money::isPositive($charge)) {
            throw new SmmException('Order amount is too small');
        }

        $order = DB::transaction(function () use ($user, $service, $data, $quantity, $runs, $totalQuantity, $charge, $source) {
            $order = new Order;
            $order->forceFill([
                'user_id' => $user->id,
                'service_id' => $service->id,
                'provider_id' => $service->isManual() ? null : $service->provider_id,
                'link' => $data['link'],
                'quantity' => $quantity,
                'rate' => $service->rate,
                'discount_percent' => $this->pricing->discountPercent($user),
                'charge' => $charge,
                'cost' => $this->pricing->cost($service, $totalQuantity),
                'status' => OrderStatus::Pending,
                'runs' => $runs,
                'interval' => $runs ? (int) $data['interval'] : null,
                'extra' => $this->extractExtra($service, $data),
                'source' => $source,
            ])->save();

            $this->balance->change($user, Money::sub(0, $charge), TransactionType::Order, $order, "Order #{$order->id}");

            return $order;
        });

        if (! $service->isManual()) {
            SendOrderToProvider::dispatch($order->id)->afterCommit();
        }

        return $order;
    }

    /**
     * Mass order: mỗi dòng "service_id | link | quantity". Mỗi dòng là 1 đơn độc lập,
     * dòng lỗi không ảnh hưởng dòng khác.
     *
     * @return list<array{line: int, order: int|null, error: string|null}>
     */
    public function placeMass(User $user, string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), fn ($l) => $l !== ''));

        if (count($lines) > self::MASS_ORDER_MAX_LINES) {
            throw new SmmException('Maximum :max orders per request', ['max' => self::MASS_ORDER_MAX_LINES]);
        }

        $results = [];
        foreach ($lines as $i => $line) {
            $parts = array_map('trim', explode('|', $line));

            try {
                if (count($parts) !== 3) {
                    throw new SmmException('Invalid format, expected: service_id | link | quantity');
                }

                $service = Service::query()->find((int) $parts[0]) ?? throw new SmmException('Invalid service ID');
                $order = $this->place($user, $service, ['link' => $parts[1], 'quantity' => $parts[2]], OrderSource::Mass);

                $results[] = ['line' => $i + 1, 'order' => $order->id, 'error' => null];
            } catch (SmmException $e) {
                $results[] = ['line' => $i + 1, 'order' => null, 'error' => $e->localized()];
            }
        }

        return $results;
    }

    /**
     * Huỷ đơn. Đơn chưa gửi provider → huỷ + hoàn tiền ngay. Đơn đã gửi → gửi yêu cầu huỷ sang provider,
     * trạng thái cập nhật ở lần đồng bộ sau. Đơn làm tay → đánh dấu chờ admin xử lý.
     *
     * @throws SmmException
     */
    public function requestCancel(Order $order): void
    {
        if (! in_array($order->status, OrderStatus::active(), true) || $order->cancel_requested_at) {
            throw new SmmException('Order cannot be canceled');
        }

        if (! $order->isSentToProvider() && ! $order->service->isManual()) {
            // Dùng chung khoá với job gửi đơn để không huỷ đúng lúc đơn đang được gửi đi.
            $lock = Cache::lock("order-send:{$order->id}", 10);

            if (! $lock->get()) {
                throw new SmmException('Order is being processed, please try again');
            }

            try {
                $order->refresh();

                if (! $order->isSentToProvider() && $order->status === OrderStatus::Pending) {
                    $this->applyStatus($order, OrderStatus::Canceled);

                    return;
                }
            } finally {
                $lock->release();
            }
        }

        if (! $order->service->cancel) {
            throw new SmmException('Cancel is not available for this service');
        }

        $order->forceFill(['cancel_requested_at' => now()])->save();

        if ($order->isSentToProvider()) {
            CancelOrderAtProvider::dispatch($order->id)->afterCommit();
        }
    }

    /**
     * @throws SmmException
     */
    public function requestRefill(Order $order): OrderRefill
    {
        $service = $order->service;

        if (! $service->refill) {
            throw new SmmException('Refill is not available for this service');
        }

        if (! in_array($order->status, [OrderStatus::Completed, OrderStatus::Partial], true)) {
            throw new SmmException('Order is not completed yet');
        }

        if ($service->refill_days && $order->completed_at?->lt(now()->subDays($service->refill_days))) {
            throw new SmmException('Refill period has expired');
        }

        $refill = DB::transaction(function () use ($order) {
            Order::query()->whereKey($order->id)->lockForUpdate()->first();

            $hasActive = $order->refills()
                ->whereIn('status', [RefillStatus::Pending, RefillStatus::Processing])
                ->exists();

            if ($hasActive) {
                throw new SmmException('Refill is already in progress');
            }

            return $order->refills()->create([
                'user_id' => $order->user_id,
                'status' => RefillStatus::Pending,
            ]);
        });

        if (! $service->isManual()) {
            SendRefillToProvider::dispatch($refill->id)->afterCommit();
        }

        return $refill;
    }

    /**
     * Áp dữ liệu 1 đơn trong response `status` của provider.
     *
     * @param  array{status?: string, start_count?: mixed, remains?: mixed, charge?: mixed, error?: string}  $row
     */
    public function applyProviderStatus(Order $order, array $row): Order
    {
        if (isset($row['error'])) {
            $order->forceFill(['provider_error' => (string) $row['error'], 'last_checked_at' => now()])->save();

            return $order;
        }

        return $this->applyStatus(
            $order,
            OrderStatus::fromProvider($row['status'] ?? null) ?? $order->status,
            is_numeric($row['start_count'] ?? null) ? (int) $row['start_count'] : null,
            is_numeric($row['remains'] ?? null) ? (int) $row['remains'] : null,
            is_numeric($row['charge'] ?? null) ? Money::round($row['charge']) : null,
        );
    }

    /**
     * Cập nhật trạng thái đơn (từ đồng bộ provider hoặc admin xử lý tay).
     * Khi đơn kết thúc: partial → hoàn phần còn thiếu, canceled/refunded → hoàn toàn bộ.
     * Hoàn tiền tính theo refunded_amount nên gọi lại nhiều lần không hoàn trùng.
     */
    public function applyStatus(
        Order $order,
        OrderStatus $status,
        ?int $startCount = null,
        ?int $remains = null,
        ?string $cost = null,
    ): Order {
        $updated = DB::transaction(function () use ($order, $status, $startCount, $remains, $cost) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status->isFinal()) {
                return $locked;
            }

            $total = $locked->quantity * ($locked->runs ?: 1);

            $locked->status = $status;
            $locked->last_checked_at = now();
            $locked->start_count = $startCount ?? $locked->start_count;
            $locked->remains = $remains === null ? $locked->remains : max(0, min($remains, $total));
            $locked->cost = $cost ?? $locked->cost;

            if ($status->isFinal()) {
                $this->finalize($locked, $status, $total);
            }

            $locked->save();

            return $locked;
        });

        if ($updated->status === OrderStatus::Completed && $updated->wasChanged('status')) {
            $this->refreshAverageTime($updated->service_id);
        }

        return $updated;
    }

    /** Admin hoàn toàn bộ phần tiền chưa hoàn, kể cả đơn đã kết thúc → trạng thái refunded. */
    public function refundByAdmin(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === OrderStatus::Refunded) {
                return $locked;
            }

            $delta = Money::sub($locked->charge, $locked->refunded_amount);

            if (Money::isPositive($delta)) {
                $this->balance->change($locked->user_id, $delta, TransactionType::Refund, $locked, "Refund order #{$locked->id}");
                $locked->refunded_amount = $locked->charge;
            }

            $locked->status = OrderStatus::Refunded;
            $locked->save();

            return $locked;
        });
    }

    /** Admin gửi lại đơn chưa gửi được sang provider (đặt lại số lần thử). */
    public function resend(Order $order): void
    {
        if ($order->status !== OrderStatus::Pending || $order->isSentToProvider() || $order->service->isManual()) {
            throw new SmmException('Only pending orders that were not sent can be resent');
        }

        $order->forceFill(['send_attempts' => 0, 'provider_error' => null, 'cancel_requested_at' => null])->save();

        SendOrderToProvider::dispatch($order->id);
    }

    private function finalize(Order $order, OrderStatus $status, int $total): void
    {
        if (in_array($status, [OrderStatus::Completed, OrderStatus::Partial], true)) {
            $order->completed_at ??= now();
        }

        if (in_array($status, [OrderStatus::Canceled, OrderStatus::Refunded], true)) {
            $order->remains ??= $total;
        }

        $target = match ($status) {
            OrderStatus::Partial => $order->remains
                ? Money::div(Money::mul($order->charge, $order->remains, 12), $total)
                : '0',
            OrderStatus::Canceled, OrderStatus::Refunded => $order->charge,
            default => '0',
        };

        $delta = Money::sub(Money::min($target, $order->charge), $order->refunded_amount);

        if (! Money::isPositive($delta)) {
            return;
        }

        $this->balance->change(
            $order->user_id,
            $delta,
            $status === OrderStatus::Partial ? TransactionType::PartialRefund : TransactionType::Refund,
            $order,
            "Refund order #{$order->id}",
        );

        $order->refunded_amount = Money::add($order->refunded_amount, $delta);
    }

    /** Thời gian hoàn thành trung bình (phút) của 10 đơn gần nhất – hiển thị cho khách. */
    private function refreshAverageTime(int $serviceId): void
    {
        $minutes = Order::query()
            ->where('service_id', $serviceId)
            ->where('status', OrderStatus::Completed)
            ->whereNotNull('completed_at')
            ->latest('id')
            ->limit(10)
            ->get(['created_at', 'completed_at'])
            ->map(fn (Order $o) => abs($o->created_at->diffInMinutes($o->completed_at)))
            ->avg();

        Service::query()->whereKey($serviceId)->update(['average_time' => (int) round($minutes ?? 0)]);
    }

    /** @return array<string, mixed> */
    private function validate(Service $service, array $input): array
    {
        $type = $service->type;

        $rules = [
            // http(s) URL hoặc giá trị không có scheme (vd @username) – chặn javascript:/data: … (XSS khi hiển thị).
            'link' => ['required', 'string', 'max:1000', 'regex:/^(https?:\/\/\S+|[^\s:]+)$/i'],
            'runs' => ['nullable', 'integer', 'min:2', 'required_with:interval'],
            'interval' => ['nullable', 'integer', 'min:1', 'required_with:runs'],
        ] + $type->extraRules();

        if (! $type->isPackage() && $type->quantityFromLinesOf() === null) {
            $rules['quantity'] = ['required', 'integer', 'min:1'];
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            throw new SmmException($validator->errors()->first());
        }

        $data = $validator->validated();

        if (! empty($data['runs']) && (! $service->dripfeed || ! $type->supportsDripfeed())) {
            throw new SmmException('Drip-feed is not available for this service');
        }

        return $data;
    }

    private function resolveQuantity(Service $service, array $data): int
    {
        $type = $service->type;

        if ($type->isPackage()) {
            return 1;
        }

        $quantity = ($field = $type->quantityFromLinesOf())
            ? count(array_filter(array_map('trim', preg_split('/\R/', (string) $data[$field])), fn ($l) => $l !== ''))
            : (int) $data['quantity'];

        if ($quantity < $service->min) {
            throw new SmmException('Quantity less than minimal :min', ['min' => $service->min]);
        }

        if ($quantity > $service->max) {
            throw new SmmException('Quantity more than maximal :max', ['max' => $service->max]);
        }

        return $quantity;
    }

    /** @return array<string, mixed>|null */
    private function extractExtra(Service $service, array $data): ?array
    {
        $extra = array_filter(
            array_intersect_key($data, $service->type->extraRules()),
            fn ($v) => $v !== null && $v !== '',
        );

        return $extra ?: null;
    }
}
