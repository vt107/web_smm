<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Currency;
use App\Models\Level;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    /** Thông tin header: số dư, cấp bậc, tiến độ lên cấp. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['level', 'displayCurrency']);
        $next = Level::query()->where('auto_upgrade', true)->where('min_spent', '>', $user->level->min_spent)->orderBy('min_spent')->first();

        return response()->json([
            'username' => $user->username,
            'balance' => $user->displayCurrency->format($user->balance, roundUp: false),
            'balance_usd' => Money::format($user->balance),
            'total_spent' => $user->displayCurrency->format($user->total_spent, roundUp: false),
            'level' => ['name' => $user->level->name, 'discount' => (float) $user->level->discount_percent, 'color' => $user->level->color],
            'next_level' => $next ? [
                'name' => $next->name,
                'discount' => (float) $next->discount_percent,
                'remaining' => $user->displayCurrency->format(Money::sub($next->min_spent, $user->total_spent)),
                'progress' => min(100, (int) round((float) $user->total_spent / max((float) $next->min_spent, 0.000001) * 100)),
            ] : null,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $orders = $request->user()->orders();

        return response()->json([
            'stats' => [
                'total' => (clone $orders)->count(),
                'active' => (clone $orders)->whereIn('status', OrderStatus::active())->count(),
                'completed' => (clone $orders)->where('status', OrderStatus::Completed)->count(),
            ],
            'recent' => OrderResource::collection((clone $orders)->with('service')->latest('id')->limit(5)->get()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'currency' => ['required', Rule::exists('currencies', 'code')->where('status', 'active')],
            'timezone' => ['required', 'timezone'],
        ]);

        $request->user()->update($data);

        return response()->json(['message' => 'Account updated']);
    }

    public function password(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);
        // Giữ phiên hiện tại, đăng xuất mọi thiết bị khác (middleware auth.session so khớp hash mật khẩu).
        Auth::logoutOtherDevices($data['password']);

        return response()->json(['message' => 'Password changed']);
    }

    public function apiKey(Request $request): JsonResponse
    {
        return response()->json([
            'key' => $request->user()->generateApiKey(),
            'message' => 'New API key created. It is shown only once, please save it.',
        ]);
    }

    public function currencies(): JsonResponse
    {
        return response()->json(Currency::query()->where('status', 'active')->get(['code', 'name', 'symbol']));
    }
}
