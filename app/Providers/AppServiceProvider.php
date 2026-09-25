<?php

namespace App\Providers;

use App\Models\Deposit;
use App\Models\Order;
use App\Models\User;
use App\Support\MailSettings;
use App\Support\Site;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
