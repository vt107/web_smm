<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Http\Controllers\Controller;
use App\Models\ServiceUpdate;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /** Danh sách danh mục (nhẹ) + tiền tệ hiển thị; dịch vụ tải riêng theo từng danh mục. */
    public function index(Request $request, CatalogService $catalog): JsonResponse
    {
        return response()->json([
            'currency' => $request->user()->displayCurrency->only(['code', 'symbol', 'symbol_position', 'rate', 'decimals']),
            'categories' => $catalog->categories(),
        ]);
    }

    public function category(Request $request, CatalogService $catalog, int $category): JsonResponse
    {
        return response()->json(['services' => $catalog->servicesInCategory($category, $request->user())]);
    }

    public function search(Request $request, CatalogService $catalog): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'max:100']]);

        return response()->json(['services' => $catalog->search($request->string('q'), $request->user())]);
    }

    public function service(Request $request, CatalogService $catalog, int $service): JsonResponse
    {
        return response()->json(['service' => $catalog->find($service, $request->user()) ?? abort(404)]);
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
