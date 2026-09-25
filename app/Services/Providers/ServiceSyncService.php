<?php

namespace App\Services\Providers;

use App\Enums\ActiveStatus;
use App\Enums\Fulfillment;
use App\Enums\Platform;
use App\Enums\ServiceType;
use App\Exceptions\ProviderException;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Setting;
use App\Support\Money;
use Illuminate\Support\Str;

class ServiceSyncService
{
    public function __construct(private readonly ProviderManager $providers) {}

    /**
     * Cập nhật giá vốn, min/max, cờ refill/cancel/dripfeed cho các dịch vụ đã map với provider.
     * Dịch vụ provider đã bỏ → tắt. Nếu có setting price_sync_markup_percent → tự đặt lại giá bán.
     *
     * @return array{updated: int, disabled: int}
     *
     * @throws ProviderException
     */
    public function sync(Provider $provider): array
    {
        $client = $this->providers->for($provider);
        $remote = collect($client->services())->keyBy('service');
        $markup = Setting::get('price_sync_markup_percent');
        $stats = ['updated' => 0, 'disabled' => 0];

        foreach ($provider->services()->where('fulfillment', Fulfillment::Api)->get() as $service) {
            $row = $remote->get($service->provider_service_id);

            if (! $row) {
                if ($service->isActive()) {
                    $service->update(['status' => ActiveStatus::Disabled]);
                    $stats['disabled']++;
                }

                continue;
            }

            $service->fill([
                'provider_rate' => $row['rate'],
                'min' => $row['min'],
                'max' => $row['max'],
                'refill' => $row['refill'],
                'cancel' => $row['cancel'],
                'dripfeed' => $row['dripfeed'],
            ]);

            if ($markup !== null) {
                $service->rate = $this->applyMarkup($row['rate'], (string) $markup);
            }

            if ($service->isDirty()) {
                $service->save();
                $stats['updated']++;
            }
        }

        $provider->forceFill([
            'balance' => $client->balance()['balance'],
            'last_synced_at' => now(),
        ])->save();

        return $stats;
    }

    /**
     * Tạo dịch vụ mới từ danh sách của provider (bỏ qua dịch vụ đã import).
     *
     * @param  list<string>|null  $onlyIds  chỉ import các service ID này của provider
     * @return int số dịch vụ đã tạo
     *
     * @throws ProviderException
     */
    public function import(Provider $provider, string $markupPercent, ?array $onlyIds = null): int
    {
        $existing = $provider->services()->withTrashed()->pluck('provider_service_id')->flip();
        $created = 0;

        foreach ($this->providers->for($provider)->services() as $row) {
            if ($existing->has($row['service']) || ($onlyIds !== null && ! in_array($row['service'], $onlyIds, true))) {
                continue;
            }

            $category = Category::query()->firstOrCreate(
                ['name' => Str::limit($row['category'] ?: 'Other', 188)],
                ['platform' => $this->guessPlatform($row['category'].' '.$row['name'])],
            );

            Service::query()->create([
                'category_id' => $category->id,
                'fulfillment' => Fulfillment::Api,
                'provider_id' => $provider->id,
                'provider_service_id' => $row['service'],
                'name' => Str::limit($row['name'], 250),
                'type' => ServiceType::fromProvider($row['type']),
                'rate' => $this->applyMarkup($row['rate'], $markupPercent),
                'provider_rate' => $row['rate'],
                'min' => $row['min'],
                'max' => $row['max'],
                'refill' => $row['refill'],
                'cancel' => $row['cancel'],
                'dripfeed' => $row['dripfeed'],
                'status' => ActiveStatus::Active,
            ]);

            $created++;
        }

        return $created;
    }

    private function applyMarkup(string $rate, string $markupPercent): string
    {
        return Money::mul($rate, Money::add(1, Money::div($markupPercent, 100, 12), 12));
    }

    private function guessPlatform(string $text): Platform
    {
        $text = strtolower($text);

        $keywords = [
            'instagram' => Platform::Instagram, 'tiktok' => Platform::TikTok, 'youtube' => Platform::YouTube,
            'facebook' => Platform::Facebook, 'telegram' => Platform::Telegram, 'twitter' => Platform::Twitter,
            'spotify' => Platform::Spotify,
        ];

        foreach ($keywords as $keyword => $platform) {
            if (str_contains($text, $keyword)) {
                return $platform;
            }
        }

        return preg_match('/\bx\b/', $text) ? Platform::Twitter : Platform::Other;
    }
}
