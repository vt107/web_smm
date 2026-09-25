<?php

namespace App\Http\Controllers\Public;

use App\Enums\ActiveStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\View\View;

/** Bảng giá công khai, render sẵn phía server để Google đọc được. */
class PricingController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::query()
            ->where('status', ActiveStatus::Active)
            ->with(['services' => fn ($q) => $q->active()->orderBy('sort')->orderBy('id')])
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(fn (Category $c) => $c->services->isNotEmpty());

        return view('pricing', ['categories' => $categories]);
    }
}
