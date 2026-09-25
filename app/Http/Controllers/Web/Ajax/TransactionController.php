<?php

namespace App\Http\Controllers\Web\Ajax;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return TransactionResource::collection($request->user()->transactions()->latest('id')->paginate(30));
    }
}
