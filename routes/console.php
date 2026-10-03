<?php

use App\Models\ApiLog;
use App\Support\Demo\DemoMode;
use Illuminate\Support\Facades\Schedule;

// Chế độ demo: dữ liệu mẫu đứng yên tới lần reset sau, không gọi provider / blockchain / API tỉ giá.
$live = fn () => ! DemoMode::enabled();

Schedule::command('orders:send')->everyMinute()->withoutOverlapping()->runInBackground()->when($live);
Schedule::command('orders:sync')->everyFiveMinutes()->withoutOverlapping()->when($live);
Schedule::command('refills:sync')->everyTenMinutes()->withoutOverlapping()->when($live);
Schedule::command('providers:sync')->hourly()->withoutOverlapping()->when($live);
Schedule::command('deposits:scan')->everyMinute()->withoutOverlapping()->when($live);
Schedule::command('deposits:expire')->everyFiveMinutes()->when($live);
Schedule::command('currencies:update-rates')->daily()->when($live);
Schedule::command('model:prune', ['--model' => [ApiLog::class]])->daily();

if ($resetAt = config('demo.reset_at')) {
    Schedule::command('demo:reset --force')->dailyAt($resetAt)->when(fn () => DemoMode::enabled());
}
