<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Bảng giá công khai (render phía server để Google đọc được), chia tab theo nền tảng. */
class PricingController extends Controller
{
    public function __invoke(Request $request, CatalogService $catalog): View
    {
        return view('pricing', $catalog->publicPricing($request->query('platform')));
    }
}
