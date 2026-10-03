<?php

namespace App\Support\Demo;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thao tác ghi bị chặn ở chế độ demo. Tự render, không ghi log.
 * Trong request Livewire, DemoServiceProvider bắt trước và hiện thông báo trên trang.
 */
class DemoModeException extends RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? DemoMode::message());
    }

    public function report(): void
    {
        // Không ghi log: đây là hành vi mong đợi của bản demo.
    }

    public function render(Request $request): Response
    {
        // isJson(): webhook / API gửi body JSON (vd SePay) nhận 403 JSON thay vì redirect.
        if ($request->is('api/*') || $request->expectsJson() || $request->isJson()) {
            return response()->json(['error' => $this->getMessage(), 'message' => $this->getMessage()], 403);
        }

        if ($request->isMethodSafe()) {
            return response()->view('demo.blocked', ['message' => $this->getMessage()], 403);
        }

        return back()->withInput($request->except(['password', 'password_confirmation', '_token']))
            ->with('demo_blocked', $this->getMessage());
    }
}
