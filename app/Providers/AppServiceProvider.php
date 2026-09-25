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

        // Production (APP_URL https): mọi link (kể cả link đặt lại mật khẩu trong email) luôn dùng đúng domain
        // + https, không phụ thuộc Host / X-Forwarded-* do client gửi (chống giả mạo host).
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceRootUrl(config('app.url'));
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
            $user = User::findByApiKey((string) $request->input('key'));

            // Key hợp lệ → giới hạn theo tài khoản; key sai/thiếu → giới hạn chặt theo IP
            // (tránh đổi key ngẫu nhiên mỗi request để né giới hạn và làm phình bảng api_logs).
            return $user
                ? Limit::perMinute((int) config('smm.api.rate_limit_per_minute'))->by('user:'.$user->id)
                : Limit::perMinute(30)->by('ip:'.$request->ip());
        });
    }
}
