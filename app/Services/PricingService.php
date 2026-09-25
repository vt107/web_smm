<?php

namespace App\Services;

use App\Models\Service;
use App\Models\User;
use App\Support\Money;

/** Giá bán áp giảm giá theo cấp bậc của user. */
class PricingService
{
    public function discountPercent(User $user): string
    {
        return (string) ($user->level?->discount_percent ?? '0');
    }

    /** Giá / 1000 (package: giá / gói) sau giảm giá của user. */
    public function rateFor(Service $service, User $user): string
    {
        return $this->applyDiscount($service->rate, $this->discountPercent($user));
    }

    /** Tổng tiền cho đơn. $totalQuantity đã nhân số runs nếu drip-feed. */
    public function charge(Service $service, User $user, int $totalQuantity): string
    {
        return $this->applyDiscount($this->baseAmount($service->rate, $service, $totalQuantity), $this->discountPercent($user));
    }

    /** Giá vốn ước tính tại provider (cập nhật lại bằng charge thật khi đồng bộ trạng thái). */
    public function cost(Service $service, int $totalQuantity): ?string
    {
        return $service->provider_rate === null
            ? null
            : Money::round($this->baseAmount($service->provider_rate, $service, $totalQuantity));
    }

    private function baseAmount(string $rate, Service $service, int $totalQuantity): string
    {
        if ($service->type->isPackage()) {
            return $rate;
        }

        return Money::div(Money::mul($rate, $totalQuantity, 12), 1000, 12);
    }

    private function applyDiscount(string $amount, string $discountPercent): string
    {
        $factor = Money::div(Money::sub(100, $discountPercent, 12), 100, 12);

        return Money::mul($amount, $factor);
    }
}
