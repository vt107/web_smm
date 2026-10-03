<?php

namespace App\Http\Middleware;

use App\Support\Demo\DemoMode;
use App\Support\Demo\DemoModeException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chế độ demo: chặn form ghi dữ liệu trước khi vào controller (tránh tác dụng phụ như gửi mail).
 * Request Livewire đi qua, lệnh ghi bên trong bị guard SQL chặn.
 */
class DemoReadOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! DemoMode::enabled() || $request->isMethodSafe() || $this->allowed($request)) {
            return $next($request);
        }

        throw new DemoModeException;
    }

    protected function allowed(Request $request): bool
    {
        // Livewire 4 đặt tên route có tiền tố (default-livewire.update), Livewire 3 thì không.
        if ($request->routeIs('*livewire.upload-file')) {
            return false;
        }

        if ($request->routeIs('*livewire.update')) {
            return true;
        }

        foreach (config('demo.allowed_routes', []) as $route) {
            if ($request->routeIs($route) || $request->is(ltrim($route, '/'))) {
                return true;
            }
        }

        return false;
    }
}
