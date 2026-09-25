<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Exceptions\SmmException;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Service;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));

        $orders = $request->user()->orders()
            ->with(['service', 'refills'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('id', ctype_digit($search) ? (int) $search : 0)
                ->orWhere('link', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(20);

        return OrderResource::collection($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $service = Service::query()->find((int) $request->input('service'))
            ?? throw new SmmException('Invalid service ID');

        $order = $this->orders->place($request->user(), $service, $request->except(['service']), OrderSource::Web);

        return response()->json([
            'message' => "Đã đặt đơn #{$order->id}",
            'order' => new OrderResource($order->load('service')),
        ], 201);
    }

    public function mass(Request $request): JsonResponse
    {
        $request->validate(['orders' => ['required', 'string', 'max:50000']]);

        $results = collect($this->orders->placeMass($request->user(), $request->input('orders')));

        return response()->json([
            'message' => 'Đã đặt '.$results->whereNotNull('order')->count().'/'.$results->count().' đơn',
            'results' => $results,
        ]);
    }

    public function cancel(Request $request, int $order): JsonResponse
    {
        $this->orders->requestCancel($this->find($request, $order));

        return response()->json(['message' => 'Đã gửi yêu cầu huỷ đơn']);
    }

    public function refill(Request $request, int $order): JsonResponse
    {
        $this->orders->requestRefill($this->find($request, $order));

        return response()->json(['message' => 'Đã gửi yêu cầu refill']);
    }

    private function find(Request $request, int $id): Order
    {
        return $request->user()->orders()->with('service')->findOrFail($id);
    }
}
