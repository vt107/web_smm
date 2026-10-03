<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderSource;
use App\Exceptions\SmmException;
use App\Http\Controllers\Controller;
use App\Models\ApiLog;
use App\Models\Currency;
use App\Models\Order;
use App\Models\OrderRefill;
use App\Models\Service;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\OrderService;
use App\Support\Demo\DemoMode;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * API cho khách (reseller), tương thích chuẩn PerfectPanel v2:
 * POST /api/v2 với key + action. Lỗi nghiệp vụ trả HTTP 200 + {"error": "..."} như các panel khác.
 */
class V2Controller extends Controller
{
    /** Chỉ ghi log các action làm thay đổi dữ liệu (và mọi request lỗi) để bảng log không phình vì polling. */
    private const LOGGED_ACTIONS = ['add', 'refill', 'cancel'];

    private const LOGGED_PARAMS = ['action', 'service', 'link', 'quantity', 'runs', 'interval', 'order', 'orders', 'refill', 'refills'];

    public function __construct(
        private readonly OrderService $orders,
        private readonly CatalogService $catalog,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        // API luôn trả tiếng Anh, kể cả thông báo validate.
        app()->setLocale('en');

        $user = User::findByApiKey($request->input('key'));
        $action = (string) $request->input('action');
        $error = null;

        try {
            if (! $user) {
                throw new SmmException('Invalid API key');
            }

            if (! $user->isActive()) {
                throw new SmmException('Your account is suspended');
            }

            // Bản demo chỉ xem: action ghi (đặt / refill / huỷ đơn) trả lỗi đúng format API, action đọc vẫn chạy.
            if (in_array($action, self::LOGGED_ACTIONS, true) && DemoMode::guarding()) {
                throw new SmmException(DemoMode::message());
            }

            $payload = match ($action) {
                'services' => $this->services($user),
                'add' => $this->add($user, $request),
                'status' => $this->status($user, $request),
                'refill' => $this->refill($user, $request),
                'refill_status' => $this->refillStatus($user, $request),
                'cancel' => $this->cancel($user, $request),
                'balance' => ['balance' => Money::format($user->balance), 'currency' => Currency::BASE],
                default => throw new SmmException('Incorrect request'),
            };
        } catch (SmmException $e) {
            $error = $e->getMessage();
            $payload = ['error' => $error];
        }

        // Bản demo không ghi log (trang API logs dùng dữ liệu mẫu).
        if (! DemoMode::guarding() && ($error !== null || in_array($action, self::LOGGED_ACTIONS, true))) {
            ApiLog::query()->create([
                'user_id' => $user?->id,
                'action' => Str::limit($action, 20, ''),
                'ip' => (string) $request->ip(),
                // Chỉ lưu các tham số chuẩn, cắt ngắn giá trị (không để request rác làm phình log).
                'request' => collect($request->only(self::LOGGED_PARAMS))
                    ->map(fn ($v) => is_scalar($v) ? Str::limit((string) $v, 300) : null)
                    ->all(),
                'response_status' => 200,
                'error' => $error ? Str::limit($error, 250) : null,
            ]);
        }

        return response()->json($payload);
    }

    private function services(User $user): array
    {
        return array_map(fn (array $s) => [
            'service' => $s['id'],
            'name' => $s['name'],
            'type' => $s['type_label'],
            'category' => $s['category_name'],
            'rate' => $s['rate_usd'],
            'min' => $s['min'],
            'max' => $s['max'],
            'refill' => $s['refill'],
            'cancel' => $s['cancel'],
            'dripfeed' => $s['dripfeed'],
        ], $this->catalog->allServices($user));
    }

    private function add(User $user, Request $request): array
    {
        $service = Service::query()->find((int) $request->input('service'))
            ?? throw new SmmException('Invalid service ID');

        $order = $this->orders->place($user, $service, $request->except(['key', 'action', 'service']), OrderSource::Api);

        return ['order' => $order->id];
    }

    private function status(User $user, Request $request): array
    {
        if ($request->filled('orders')) {
            $ids = $this->ids($request->input('orders'));
            $orders = $user->orders()->whereIn('id', $ids)->get()->keyBy('id');

            $result = [];
            foreach ($ids as $id) {
                $result[$id] = $orders->has($id) ? $this->orderStatus($orders[$id]) : ['error' => 'Incorrect order ID'];
            }

            return $result;
        }

        return $this->orderStatus($this->findOrder($user, $request->input('order')));
    }

    private function refill(User $user, Request $request): array
    {
        if ($request->filled('orders')) {
            $ids = $this->ids($request->input('orders'));
            $orders = $user->orders()->with('service')->whereIn('id', $ids)->get()->keyBy('id');

            return array_map(function (int $id) use ($orders) {
                try {
                    $order = $orders->get($id) ?? throw new SmmException('Incorrect order ID');

                    return ['order' => $id, 'refill' => $this->orders->requestRefill($order)->id];
                } catch (SmmException $e) {
                    return ['order' => $id, 'refill' => ['error' => $e->getMessage()]];
                }
            }, $ids);
        }

        return ['refill' => $this->orders->requestRefill($this->findOrder($user, $request->input('order')))->id];
    }

    private function refillStatus(User $user, Request $request): array
    {
        $query = fn (array $ids) => OrderRefill::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($request->filled('refills')) {
            $ids = $this->ids($request->input('refills'));
            $refills = $query($ids);

            return array_map(fn (int $id) => [
                'refill' => $id,
                'status' => $refills->has($id) ? $refills[$id]->status->label() : ['error' => 'Refill not found'],
            ], $ids);
        }

        $refill = $query([(int) $request->input('refill')])->first()
            ?? throw new SmmException('Refill not found');

        return ['status' => $refill->status->label()];
    }

    private function cancel(User $user, Request $request): array
    {
        if ($request->filled('orders')) {
            $ids = $this->ids($request->input('orders'));
            $orders = $user->orders()->with('service')->whereIn('id', $ids)->get()->keyBy('id');

            return array_map(function (int $id) use ($orders) {
                try {
                    $this->orders->requestCancel($orders->get($id) ?? throw new SmmException('Incorrect order ID'));

                    return ['order' => $id, 'cancel' => 1];
                } catch (SmmException $e) {
                    return ['order' => $id, 'cancel' => ['error' => $e->getMessage()]];
                }
            }, $ids);
        }

        $this->orders->requestCancel($this->findOrder($user, $request->input('order')));

        return ['success' => 'Your order will be canceled asap.'];
    }

    private function orderStatus(Order $order): array
    {
        return [
            'charge' => Money::format(Money::sub($order->charge, $order->refunded_amount)),
            'start_count' => (string) ($order->start_count ?? 0),
            'status' => $order->status->label(),
            'remains' => (string) ($order->remains ?? $order->quantity * ($order->runs ?: 1)),
            'currency' => Currency::BASE,
        ];
    }

    private function findOrder(User $user, mixed $id): Order
    {
        return $user->orders()->with('service')->find((int) $id)
            ?? throw new SmmException('Incorrect order ID');
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $value)), fn ($id) => $id > 0)));
        $max = (int) config('smm.api.max_batch');

        if ($ids === []) {
            throw new SmmException('Incorrect order ID');
        }

        if (count($ids) > $max) {
            throw new SmmException('Maximum :max IDs per request', ['max' => $max]);
        }

        return $ids;
    }
}
