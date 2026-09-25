<?php

namespace App\Providers;

use App\Models\Deposit;
use App\Models\Order;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\Site;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Tên web do admin đặt (Website settings) dùng cho toàn hệ thống: tiêu đề trang, email, admin.
        if ($proxies = config('smm.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Chạy sau Cloudflare "Flexible" (origin chỉ có HTTP) → vẫn sinh link https (kể cả trong email / queue).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        config(['app.name' => Site::name()]);
        MailSettings::apply();

        // Lưu tên ngắn thay cho tên class trong transactions.reference_type.
        Relation::enforceMorphMap([
            'order' => Order::class,
            'deposit' => Deposit::class,
            'user' => User::class,
        ]);

        RateLimiter::for('smm-api', function (Request $request) {
            $key = (string) $request->input('key');

            return Limit::perMinute((int) config('smm.api.rate_limit_per_minute'))
                ->by($key !== '' ? User::hashApiKey($key) : $request->ip());
        });
    }
}
