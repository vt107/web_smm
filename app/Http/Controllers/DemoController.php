<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Chế độ demo: "Đăng nhập nhanh" từ nút Demo. Đăng xuất phiên hiện tại rồi mở trang đăng nhập
 * của khu vực tương ứng với tài khoản đã điền sẵn (?demo=<key>).
 */
class DemoController extends Controller
{
    public function switch(Request $request, string $key): RedirectResponse
    {
        foreach (config('demo.portals', []) as $portal) {
            foreach ($portal['accounts'] ?? [] as $account) {
                if ($account['key'] !== $key) {
                    continue;
                }

                foreach (array_keys(config('auth.guards')) as $guard) {
                    if (config("auth.guards.{$guard}.driver") === 'session' && Auth::guard($guard)->check()) {
                        Auth::guard($guard)->logout();
                    }
                }

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                $loginUrl = url($portal['login_url'] ?? $portal['url']);

                return redirect()->to($loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').'demo='.urlencode($key));
            }
        }

        abort(404);
    }
}
