<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceUpdate;
use App\Services\PricingService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /** Toàn bộ danh mục + dịch vụ đang bán, giá đã giảm theo cấp của user. */
    public function services(Request $request, PricingService $pricing): JsonResponse
    {
        $user = $request->user()->load(['level', 'displayCurrency']);
        $currency = $user->displayCurrency;

        $categories = Category::query()
            ->where('status', ActiveStatus::Active)
            ->with(['services' => fn ($q) => $q->active()->orderBy('sort')->orderBy('id')])
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(fn (Category $c) => $c->services->isNotEmpty())
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'platform' => $c->platform->value,
                'platform_label' => $c->platform->getLabel(),
                'services' => $c->services->map(function (Service $s) use ($pricing, $user, $currency) {
                    $rate = $pricing->rateFor($s, $user);

                    return [
                        'id' => $s->id,
                        'name' => $s->name,
                        'description' => $s->description,
                        'type' => $s->type->value,
                        'type_label' => $s->type->getLabel(),
                        'is_package' => $s->type->isPackage(),
                        'lines_field' => $s->type->quantityFromLinesOf(),
                        'fields' => $s->type->formFields(),
                        'rate_usd' => Money::format($rate),
                        'rate' => $currency->format($rate),
                        'min' => $s->min,
                        'max' => $s->max,
                        'refill' => $s->refill,
                        'refill_days' => $s->refill_days,
                        'cancel' => $s->cancel,
                        'dripfeed' => $s->dripfeed && $s->type->supportsDripfeed(),
                        'average_time' => $s->average_time,
                    ];
                })->values(),
            ])
            ->values();

        return response()->json([
            'currency' => $currency->only(['code', 'symbol', 'symbol_position', 'rate', 'decimals']),
            'categories' => $categories,
        ]);
    }

    public function updates(Request $request): JsonResponse
    {
        $user = $request->user();
        $currency = $user->displayCurrency;

        $updates = ServiceUpdate::query()->with('service')->latest('id')->paginate(30);

        return response()->json([
            'data' => $updates->getCollection()->map(fn (ServiceUpdate $u) => [
                'id' => $u->id,
                'service_id' => $u->service_id,
                'service_name' => $u->service?->name,
                'type_label' => $u->type->getLabel(),
                'type_color' => $u->type->getColor(),
                'old_value' => is_numeric($u->old_value) ? $currency->format($u->old_value) : null,
                'new_value' => is_numeric($u->new_value) ? $currency->format($u->new_value) : null,
                'created_at' => $u->created_at?->timezone($user->timezone)->format('d/m/Y H:i'),
            ]),
            'meta' => ['current_page' => $updates->currentPage(), 'last_page' => $updates->lastPage()],
        ]);
    }
}
