<?php

namespace App\Observers;

use App\Enums\ActiveStatus;
use App\Enums\ServiceUpdateType;
use App\Http\Controllers\Public\LandingController;
use App\Models\Service;
use App\Services\CatalogService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;

/** Ghi lịch sử thay đổi dịch vụ cho trang "Updates" của khách. */
class ServiceObserver
{
    /** Mọi thay đổi (kể cả xoá mềm) → danh mục cache và landing phải tính lại. */
    public function saved(Service $service): void
    {
        self::flushCaches();
    }

    public function deleted(Service $service): void
    {
        self::flushCaches();
    }

    public function restored(Service $service): void
    {
        self::flushCaches();
    }

    public static function flushCaches(): void
    {
        CatalogService::flush();
        Cache::forget(LandingController::CACHE_KEY);
    }

    public function created(Service $service): void
    {
        $service->updates()->create(['type' => ServiceUpdateType::New, 'new_value' => $service->rate]);
    }

    public function updated(Service $service): void
    {
        if ($service->wasChanged('rate')) {
            $old = (string) $service->getOriginal('rate');
            $new = (string) $service->rate;

            $service->updates()->create([
                'type' => Money::cmp($new, $old) > 0 ? ServiceUpdateType::PriceIncrease : ServiceUpdateType::PriceDecrease,
                'old_value' => $old,
                'new_value' => $new,
            ]);
        }

        if ($service->wasChanged('status')) {
            $service->updates()->create([
                'type' => $service->status === ActiveStatus::Active ? ServiceUpdateType::Enabled : ServiceUpdateType::Disabled,
            ]);
        }
    }
}
