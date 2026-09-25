<?php

use App\Http\Controllers\Web\Ajax\AccountController;
use App\Http\Controllers\Web\Ajax\CatalogController;
use App\Http\Controllers\Web\Ajax\DepositController;
use App\Http\Controllers\Web\Ajax\OrderController;
use App\Http\Controllers\Web\Ajax\TicketController;
use App\Http\Controllers\Web\Ajax\TransactionController;
use App\Http\Controllers\Web\AuthController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Trang: khung Blade, dữ liệu lấy qua /ajax bằng Alpine.js.
    Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
    Route::view('/new-order', 'pages.new-order')->name('new-order');
    Route::view('/orders', 'pages.orders')->name('orders');
    Route::view('/services', 'pages.services')->name('services');
    Route::view('/add-funds', 'pages.add-funds')->name('add-funds');
    Route::view('/transactions', 'pages.transactions')->name('transactions');
    Route::view('/tickets', 'pages.tickets')->name('tickets');
    Route::get('/tickets/{ticket}', fn (int $ticket) => view('pages.ticket', ['ticketId' => $ticket]))->name('tickets.show');
    Route::view('/updates', 'pages.updates')->name('updates');
    Route::view('/account', 'pages.account')->name('account');
    Route::view('/api-docs', 'pages.api-docs')->name('api-docs');

    // JSON nội bộ cho các trang trên (session + CSRF).
    Route::prefix('ajax')->group(function () {
        Route::get('/me', [AccountController::class, 'me']);
        Route::get('/dashboard', [AccountController::class, 'dashboard']);
        Route::get('/currencies', [AccountController::class, 'currencies']);
        Route::put('/account', [AccountController::class, 'update']);
        Route::put('/account/password', [AccountController::class, 'password']);
        Route::post('/account/api-key', [AccountController::class, 'apiKey'])->middleware('throttle:5,1');

        Route::get('/services', [CatalogController::class, 'services']);
        Route::get('/updates', [CatalogController::class, 'updates']);

        Route::get('/orders', [OrderController::class, 'index']);
        Route::post('/orders', [OrderController::class, 'store'])->middleware('throttle:60,1');
        Route::post('/orders/mass', [OrderController::class, 'mass'])->middleware('throttle:10,1');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
        Route::post('/orders/{order}/refill', [OrderController::class, 'refill']);

        Route::get('/payment-methods', [DepositController::class, 'methods']);
        Route::get('/deposits', [DepositController::class, 'index']);
        Route::post('/deposits', [DepositController::class, 'store'])->middleware('throttle:20,1');
        Route::get('/deposits/{deposit}', [DepositController::class, 'show']);
        Route::post('/deposits/claim', [DepositController::class, 'claim'])->middleware('throttle:10,1');

        Route::get('/transactions', [TransactionController::class, 'index']);

        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store'])->middleware('throttle:10,1');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('/tickets/{ticket}/reply', [TicketController::class, 'reply'])->middleware('throttle:20,1');
        Route::post('/tickets/{ticket}/close', [TicketController::class, 'close']);
    });
});
