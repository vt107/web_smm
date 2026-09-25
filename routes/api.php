<?php

use App\Http\Controllers\Api\V2Controller;
use App\Http\Controllers\Sandbox\SandboxProviderController;
use App\Http\Controllers\Webhooks\SepayWebhookController;
use Illuminate\Support\Facades\Route;

// API cho khách (reseller) – chuẩn PerfectPanel.
Route::post('/v2', V2Controller::class)->middleware('throttle:smm-api');

Route::post('/webhooks/sepay/{paymentMethod}', SepayWebhookController::class);

// Provider giả lập cho môi trường test (SANDBOX_PROVIDER_ENABLED=true).
if (config('smm.sandbox.enabled')) {
    Route::post('/sandbox-provider/v2', SandboxProviderController::class);
}
