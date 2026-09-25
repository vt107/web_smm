<?php

namespace App\Services;

use App\Enums\ActiveStatus;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

/**
 * Danh mục dịch vụ đang bán, cache dạng mảng dùng chung cho mọi khách (provider thật có thể có hàng nghìn
 * dịch vụ). Cache tự xoá khi Service / Category thay đổi (xem Model::booted). Giá theo cấp của từng khách
 * được tính lại từ giá gốc trong cache nên vẫn nhanh.
 */
class CatalogService
{
    public const CACHE_KEY = 'catalog:v1';

    public const SEARCH_LIMIT = 50;

    public function __construct(private readonly PricingService $pricing) {}

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return list<array{id: int, name: string, platform: string, platform_label: string, service_count: int}> */
    public function categories(): array
    {
        return array_map(
            fn (array $c) => array_diff_key($c, ['services' => true]) + ['service_count' => count($c['services'])],
            $this->catalog(),
        );
    }

    /** @return list<array<string, mixed>> dịch vụ của 1 danh mục, giá đã áp giảm giá của khách */
    public function servicesInCategory(int $categoryId, User $user): array
    {
        foreach ($this->catalog() as $category) {
            if ($category['id'] === $categoryId) {
                return $this->priced($category['services'], $user);
            }
        }

        return [];
    }

    /** Tìm theo ID hoặc tên dịch vụ trên toàn bộ danh mục. @return list<array<string, mixed>> */
    public function search(string $query, User $user): array
    {
        $query = mb_strtolower(trim($query));

        if ($query === '') {
            return [];
        }

        $matches = [];
        foreach ($this->catalog() as $category) {
            foreach ($category['services'] as $service) {
                if ((string) $service['id'] === $query || str_contains(mb_strtolower($service['name']), $query)) {
                    $matches[] = $service + ['category_id' => $category['id'], 'category_name' => $category['name']];

                    if (count($matches) >= self::SEARCH_LIMIT) {
                        break 2;
                    }
                }
            }
        }

        return $this->priced($matches, $user);
    }

    /** @return array<string, mixed>|null */
    public function find(int $serviceId, User $user): ?array
    {
        foreach ($this->catalog() as $category) {
            foreach ($category['services'] as $service) {
                if ($service['id'] === $serviceId) {
                    return $this->priced([$service + ['category_id' => $category['id']]], $user)[0];
                }
            }
        }

        return null;
    }

    /**
     * Bảng giá công khai (giá gốc, chưa giảm theo cấp) – chỉ 1 nền tảng mỗi trang để HTML không quá nặng.
     *
     * @return array{platforms: list<array{value: string, label: string}>, platform: ?string, categories: list<array<string, mixed>>}
     */
    public function publicPricing(?string $platform): array
    {
        $catalog = $this->catalog();
        $platforms = collect($catalog)->unique('platform')
            ->map(fn (array $c) => ['value' => $c['platform'], 'label' => $c['platform_label']])
            ->values()->all();

        $selected = collect($platforms)->pluck('value')->contains($platform) ? $platform : ($platforms[0]['value'] ?? null);

        return [
            'platforms' => $platforms,
            'platform' => $selected,
            'categories' => array_values(array_filter($catalog, fn (array $c) => $c['platform'] === $selected)),
        ];
    }

    /** Toàn bộ dịch vụ (dùng cho API v2 action=services). @return list<array<string, mixed>> */
    public function allServices(User $user): array
    {
        $all = [];
        foreach ($this->catalog() as $category) {
            foreach ($category['services'] as $service) {
                $all[] = $service + ['category_name' => $category['name']];
            }
        }

        return $this->priced($all, $user);
    }

    /**
     * Dữ liệu gốc (chưa áp giảm giá) – cache đến khi có thay đổi.
     *
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Category::query()
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
                'services' => $c->services->map(fn (Service $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'description' => $s->description,
                    'type' => $s->type->value,
                    'type_label' => $s->type->getLabel(),
                    'is_package' => $s->type->isPackage(),
                    'lines_field' => $s->type->quantityFromLinesOf(),
                    'fields' => $s->type->formFields(),
                    'base_rate' => (string) $s->rate,
                    'min' => $s->min,
                    'max' => $s->max,
                    'refill' => $s->refill,
                    'refill_days' => $s->refill_days,
                    'cancel' => $s->cancel,
                    'dripfeed' => $s->dripfeed && $s->type->supportsDripfeed(),
                    'average_time' => $s->average_time,
                ])->values()->all(),
            ])
            ->values()
            ->all());
    }

    /**
     * Thêm giá theo cấp + tiền tệ hiển thị của khách.
     *
     * @param  list<array<string, mixed>>  $services
     * @return list<array<string, mixed>>
     */
    private function priced(array $services, User $user): array
    {
        $user->loadMissing(['level', 'displayCurrency']);
        $discount = $this->pricing->discountPercent($user);
        $currency = $user->displayCurrency;

        return array_map(function (array $service) use ($discount, $currency) {
            $rate = $this->pricing->applyDiscountTo($service['base_rate'], $discount);
            unset($service['base_rate']);

            return $service + [
                'rate_usd' => Money::format($rate),
                'rate' => $currency->format($rate),
            ];
        }, $services);
    }
}
