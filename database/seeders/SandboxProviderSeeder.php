<?php

namespace Database\Seeders;

use App\Enums\ActiveStatus;
use App\Enums\Fulfillment;
use App\Enums\Platform;
use App\Enums\ServiceType;
use App\Models\Category;
use App\Models\Provider;
use App\Models\Service;
use App\Sandbox\SandboxCatalog;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Tạo provider giả lập + dịch vụ tương ứng (lãi 30%) để test luồng gửi đơn tự động.
 * Chạy: php artisan db:seed --class=SandboxProviderSeeder  (cần SANDBOX_PROVIDER_ENABLED=true)
 */
class SandboxProviderSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('SandboxProviderSeeder chỉ dùng để test – không chạy trên production.');

            return;
        }

        $provider = Provider::query()->updateOrCreate(['name' => 'Sandbox Provider'], [
            'api_url' => config('smm.sandbox.url'),
            'api_key' => config('smm.sandbox.key'),
            'driver' => 'perfectpanel',
            'status' => ActiveStatus::Active,
            'note' => 'Local test provider – not a real panel. Order statuses advance automatically over time.',
        ]);

        $category = Category::query()->updateOrCreate(
            ['name' => 'Sandbox (test provider)'],
            ['platform' => Platform::Other, 'sort' => 99, 'status' => ActiveStatus::Active],
        );

        foreach (SandboxCatalog::SERVICES as $id => $s) {
            Service::query()->updateOrCreate(
                ['provider_id' => $provider->id, 'provider_service_id' => (string) $id],
                [
                    'category_id' => $category->id,
                    'fulfillment' => Fulfillment::Api,
                    'name' => $s['name'],
                    'description' => 'Test service from the sandbox provider – no real delivery.',
                    'type' => ServiceType::fromProvider($s['type']),
                    'rate' => Money::mul($s['rate'], '1.3'),
                    'provider_rate' => $s['rate'],
                    'min' => $s['min'],
                    'max' => $s['max'],
                    'refill' => $s['refill'],
                    'refill_days' => $s['refill'] ? 30 : null,
                    'cancel' => $s['cancel'],
                    'dripfeed' => $s['dripfeed'],
                    'sort' => (int) $id,
                    'status' => ActiveStatus::Active,
                ],
            );
        }

        $this->command?->info('Sandbox provider ready with '.count(SandboxCatalog::SERVICES).' services ('.config('smm.sandbox.url').').');

        if (! config('smm.sandbox.enabled')) {
            $this->command?->warn('SANDBOX_PROVIDER_ENABLED is false – the sandbox endpoint is not registered.');
        }
    }
}
