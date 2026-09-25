<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Đăng xuất ngay user bị khoá trong lúc đang đăng nhập. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return $request->expectsJson()
                ? response()->json(['message' => __('Your account is suspended')], 403)
                : redirect()->route('login')->withErrors(['email' => __('Your account is suspended')]);
        }

        return $next($request);
    }
}
