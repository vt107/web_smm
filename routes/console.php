<?php

use App\Models\ApiLog;
use Illuminate\Support\Facades\Schedule;

Schedule::command('orders:sync')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('refills:sync')->everyTenMinutes()->withoutOverlapping();
Schedule::command('providers:sync')->hourly()->withoutOverlapping();
Schedule::command('deposits:scan')->everyMinute()->withoutOverlapping();
Schedule::command('deposits:expire')->everyFiveMinutes();
Schedule::command('currencies:update-rates')->daily();
Schedule::command('model:prune', ['--model' => [ApiLog::class]])->daily();
