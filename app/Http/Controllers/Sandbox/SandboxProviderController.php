<?php

namespace App\Http\Controllers\Sandbox;

use App\Http\Controllers\Controller;
use App\Sandbox\SandboxCatalog;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Provider giả lập nói chuẩn PerfectPanel v2 để test toàn bộ luồng (queue, đồng bộ, hoàn tiền, refill)
 * mà không tốn tiền thật. Trạng thái đơn tự tiến theo thời gian, mỗi giai đoạn dài smm.sandbox.stage_minutes:
 * giai đoạn 1 Pending, giai đoạn 2–3 In progress (remains giảm dần), sau đó Completed / Partial.
 * Chỉ đăng ký route khi SANDBOX_PROVIDER_ENABLED=true.
 */
class SandboxProviderController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! hash_equals((string) config('smm.sandbox.key'), (string) $request->input('key'))) {
            return response()->json(['error' => 'Invalid API key']);
        }

        return response()->json(match ((string) $request->input('action')) {
            'services' => $this->services(),
            'add' => $this->add($request),
            'status' => $this->status($request),
            'refill' => $this->refill($request),
            'refill_status' => $this->refillStatus($request),
            'cancel' => $this->cancel($request),
            'balance' => ['balance' => '1000.00', 'currency' => 'USD'],
            default => ['error' => 'Incorrect request'],
        });
    }

    private function services(): array
    {
        return collect(SandboxCatalog::SERVICES)->map(fn (array $s, string $id) => [
            'service' => (int) $id,
            'category' => 'Sandbox',
        ] + array_diff_key($s, ['outcome' => true]))->values()->all();
    }

    private function add(Request $request): array
    {
        $service = SandboxCatalog::find((string) $request->input('service'));

        if (! $service) {
            return ['error' => 'Invalid service ID'];
        }

        if ($service['outcome'] === 'reject') {
            return ['error' => 'Not enough funds on balance'];
        }

        $quantity = match ($service['type']) {
            'Package' => 1,
            'Custom Comments' => count(array_filter(array_map('trim', preg_split('/\R/', (string) $request->input('comments'))))),
            default => (int) $request->input('quantity'),
        };

        if ($quantity < $service['min'] || $quantity > $service['max']) {
            return ['error' => "Quantity must be between {$service['min']} and {$service['max']}"];
        }

        $id = DB::table('sandbox_orders')->insertGetId([
            'service_id' => (string) $request->input('service'),
            'link' => (string) $request->input('link'),
            'quantity' => $quantity * max(1, (int) $request->input('runs', 1)),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['order' => $id];
    }

    private function status(Request $request): array
    {
        if ($request->filled('orders')) {
            $ids = $this->ids($request->input('orders'));
            $rows = DB::table('sandbox_orders')->whereIn('id', $ids)->get()->keyBy('id');

            return collect($ids)->mapWithKeys(fn (int $id) => [
                $id => $rows->has($id) ? $this->orderState($rows[$id]) : ['error' => 'Incorrect order ID'],
            ])->all();
        }

        $row = DB::table('sandbox_orders')->find((int) $request->input('order'));

        return $row ? $this->orderState($row) : ['error' => 'Incorrect order ID'];
    }

    private function refill(Request $request): array
    {
        $row = DB::table('sandbox_orders')->find((int) $request->input('order'));

        if (! $row) {
            return ['error' => 'Incorrect order ID'];
        }

        if (! SandboxCatalog::find($row->service_id)['refill'] || ! in_array($this->orderState($row)['status'], ['Completed', 'Partial'], true)) {
            return ['refill' => ['error' => 'Refill is not available for this order']];
        }

        return ['refill' => DB::table('sandbox_refills')->insertGetId([
            'sandbox_order_id' => $row->id,
            'created_at' => now(),
            'updated_at' => now(),
        ])];
    }

    private function refillStatus(Request $request): array
    {
        $ids = $this->ids($request->input('refills', $request->input('refill')));
        $rows = DB::table('sandbox_refills')->whereIn('id', $ids)->get()->keyBy('id');

        $result = array_map(fn (int $id) => [
            'refill' => $id,
            'status' => $rows->has($id) ? $this->refillState($rows[$id]) : ['error' => 'Refill not found'],
        ], $ids);

        return $request->filled('refills') ? $result : ['status' => $result[0]['status']];
    }

    private function cancel(Request $request): array
    {
        $ids = $this->ids($request->input('orders', $request->input('order')));

        return array_map(function (int $id) {
            $row = DB::table('sandbox_orders')->find($id);

            if (! $row) {
                return ['order' => $id, 'cancel' => ['error' => 'Incorrect order ID']];
            }

            $finished = in_array($this->orderState($row)['status'], ['Completed', 'Partial', 'Canceled'], true);

            if (! SandboxCatalog::find($row->service_id)['cancel'] || $finished) {
                return ['order' => $id, 'cancel' => ['error' => 'Cancel is not available for this order']];
            }

            DB::table('sandbox_orders')->where('id', $id)->update(['canceled_at' => now(), 'updated_at' => now()]);

            return ['order' => $id, 'cancel' => 1];
        }, $ids);
    }

    /** Trạng thái đơn suy ra từ thời gian đã trôi qua kể từ lúc đặt. */
    private function orderState(object $row): array
    {
        $service = SandboxCatalog::find($row->service_id);
        $stage = max(1, (int) config('smm.sandbox.stage_minutes')) * 60;
        $elapsed = abs(now()->diffInSeconds($row->created_at));
        $quantity = (int) $row->quantity;
        $delivered = $service['outcome'] === 'partial' ? SandboxCatalog::PARTIAL_DELIVERED : 1;

        [$status, $remains] = match (true) {
            $row->canceled_at !== null => ['Canceled', $quantity],
            $service['outcome'] === 'canceled' && $elapsed >= $stage => ['Canceled', $quantity],
            $elapsed < $stage => ['Pending', $quantity],
            $elapsed < 3 * $stage => ['In progress', $quantity - (int) floor($quantity * $delivered * ($elapsed - $stage) / (2 * $stage))],
            $service['outcome'] === 'partial' => ['Partial', $quantity - (int) floor($quantity * $delivered)],
            default => ['Completed', 0],
        };

        $charge = $service['type'] === 'Package'
            ? $service['rate']
            : Money::div(Money::mul($service['rate'], $quantity, 12), 1000);

        return [
            'charge' => Money::format($charge),
            'start_count' => (string) (1000 + ($row->id * 37) % 5000),
            'status' => $status,
            'remains' => (string) $remains,
            'currency' => 'USD',
        ];
    }

    private function refillState(object $row): string
    {
        $stage = max(1, (int) config('smm.sandbox.stage_minutes')) * 60;
        $elapsed = abs(now()->diffInSeconds($row->created_at));

        return match (true) {
            $elapsed < $stage => 'Pending',
            $elapsed < 2 * $stage => 'In progress',
            default => 'Completed',
        };
    }

    /** @return list<int> */
    private function ids(mixed $value): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $value))));
    }
}
