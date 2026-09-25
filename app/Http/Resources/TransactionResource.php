<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transaction */
class TransactionResource extends JsonResource
{
    use Presenter;

    public function toArray(Request $request): array
    {
        $positive = Money::cmp($this->amount, 0) > 0;

        return [
            'id' => $this->id,
            'type_label' => $this->type->getLabel(),
            'type_color' => $this->type->getColor(),
            'amount' => ($positive ? '+' : '−').$this->amount($request, Money::abs($this->amount)),
            'positive' => $positive,
            'balance_after' => $this->amount($request, $this->balance_after),
            'description' => $this->description,
            'created_at' => $this->time($request, $this->created_at),
        ];
    }
}
