<?php

use App\Http\Controllers\Api\V2Controller;
use App\Http\Controllers\Webhooks\SepayWebhookController;
use Illuminate\Support\Facades\Route;

// API cho khách (reseller) – chuẩn PerfectPanel.
Route::post('/v2', V2Controller::class)->middleware('throttle:smm-api');

Route::post('/webhooks/sepay/{paymentMethod}', SepayWebhookController::class);
