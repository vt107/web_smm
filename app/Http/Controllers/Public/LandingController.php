<?php

namespace App\Http\Controllers\Public;

use App\Enums\ActiveStatus;
use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Trang chủ công khai (không cần đăng nhập). Số liệu lấy từ dữ liệu thật, cache 10 phút. */
class LandingController extends Controller
{
    public const CACHE_KEY = 'landing';

    public function __invoke(): View
    {
        return view('landing', Cache::remember(self::CACHE_KEY, 600, fn () => $this->data()));
    }

    private function data(): array
    {
        $services = Service::query()
            ->active()
            ->join('categories', 'categories.id', '=', 'services.category_id')
            ->where('categories.status', ActiveStatus::Active)
            ->get(['services.rate', 'services.type', 'categories.platform']);

        $platforms = $services->groupBy('platform')->map(function ($group, string $platform) {
            $rates = $group->reject(fn (Service $s) => $s->type->isPackage())->pluck('rate');

            return [
                'platform' => $platform,
                'label' => Platform::from($platform)->getLabel(),
                'count' => $group->count(),
                'from' => $rates->isEmpty() ? null : Money::format($rates->min(fn ($r) => (float) $r)),
            ];
        })
            // Nhiều dịch vụ lên trước, nhóm "Other" luôn ở cuối.
            ->sortBy([fn ($a, $b) => ($a['platform'] === 'other') <=> ($b['platform'] === 'other'), ['count', 'desc']])
            ->values()->all();

        $minRate = $services->reject(fn (Service $s) => $s->type->isPackage())->min(fn (Service $s) => (float) $s->rate);

        return [
            'platforms' => $platforms,
            'serviceCount' => $services->count(),
            'platformCount' => count($platforms),
            'minRate' => $minRate === null ? null : Money::format($minRate),
            'levels' => Level::query()->orderBy('min_spent')->get()
                ->map(fn (Level $l) => ['name' => $l->name, 'min_spent' => Money::format($l->min_spent), 'discount' => (float) $l->discount_percent, 'color' => $l->color])
                ->all(),
            'paymentMethods' => PaymentMethod::query()->active()->orderBy('sort')->get()
                ->map(fn (PaymentMethod $m) => ['name' => $m->name, 'bonus' => (float) $m->bonus_percent])
                ->all(),
        ];
    }
}
