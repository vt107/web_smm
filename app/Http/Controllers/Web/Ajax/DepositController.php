<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepositResource;
use App\Models\PaymentMethod;
use App\Services\Payments\DepositService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DepositController extends Controller
{
    public function __construct(private readonly DepositService $deposits) {}

    public function methods(): JsonResponse
    {
        return response()->json(PaymentMethod::query()->active()->orderBy('sort')->get()->map(fn (PaymentMethod $m) => [
            'id' => $m->id,
            'name' => $m->name,
            'gateway' => $m->gateway->value,
            'currency' => $m->currency,
            'exchange_rate' => (float) $m->exchange_rate,
            'min' => Money::format($m->min_amount),
            'max' => Money::format($m->max_amount),
            'bonus' => (float) $m->bonus_percent,
            'instructions' => $m->instructions,
        ]));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DepositResource::collection(
            $request->user()->deposits()->with('paymentMethod')->latest('id')->paginate(15)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $method = PaymentMethod::query()->active()->findOrFail($data['payment_method_id']);
        $deposit = $this->deposits->create($request->user(), $method, (string) $data['amount']);

        return response()->json(new DepositResource($deposit->load('paymentMethod')), 201);
    }

    public function show(Request $request, int $deposit): DepositResource
    {
        return new DepositResource($request->user()->deposits()->with('paymentMethod')->findOrFail($deposit));
    }

    /** Khách dán mã giao dịch / tx hash để nhận giao dịch chưa khớp. */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer'],
            'txid' => ['required', 'string', 'max:150'],
        ]);

        $method = PaymentMethod::query()->findOrFail($data['payment_method_id']);
        $deposit = $this->deposits->claim($request->user(), $method, $data['txid']);

        return response()->json([
            'message' => 'Funds added to your account',
            'deposit' => new DepositResource($deposit->load('paymentMethod')),
        ]);
    }
}
