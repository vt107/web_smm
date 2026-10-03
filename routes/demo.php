<?php

use App\Http\Controllers\DemoController;
use Illuminate\Support\Facades\Route;

// Chỉ nạp khi DEMO_MODE=true (App\Providers\DemoServiceProvider).
Route::middleware('web')->group(function () {
    Route::get('demo/switch/{key}', [DemoController::class, 'switch'])->name('demo.switch');
});
