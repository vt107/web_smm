<?php

namespace App\Http\Resources;

use App\Models\Currency;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/** Tiện ích hiển thị theo tiền tệ + múi giờ của user đang đăng nhập. */
trait Presenter
{
    protected function user(Request $request): User
    {
        return $request->user();
    }

    protected function currency(Request $request): Currency
    {
        return once(fn () => $this->user($request)->displayCurrency ?? Currency::query()->findOrFail(Currency::BASE));
    }

    /** Giá hiển thị (làm tròn lên). */
    protected function price(Request $request, mixed $usd): ?string
    {
        return $usd === null ? null : $this->currency($request)->format($usd);
    }

    /** Số tiền đã phát sinh (làm tròn thường). */
    protected function amount(Request $request, mixed $usd): ?string
    {
        return $usd === null ? null : $this->currency($request)->format($usd, roundUp: false);
    }

    protected function time(Request $request, ?CarbonInterface $time): ?string
    {
        return $time?->copy()->timezone($this->user($request)->timezone)->format('d/m/Y H:i');
    }
}
