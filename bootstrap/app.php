<?php

use App\Exceptions\SmmException;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['active' => EnsureUserIsActive::class]);
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Lỗi nghiệp vụ là lỗi của khách (số dư không đủ, sai số lượng…), không cần ghi log.
        $exceptions->dontReport(SmmException::class);

        // Lỗi nghiệp vụ trên các endpoint JSON của web → 422 kèm thông báo tiếng Việt.
        $exceptions->render(function (SmmException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->localized()], 422);
            }
        });
    })->create();
