<?php

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\RefillStatus;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    use Presenter;

    public function toArray(Request $request): array
    {
        $service = $this->service;
        $total = $this->quantity * ($this->runs ?: 1);
        $refillWindowOk = ! $service->refill_days || ! $this->completed_at || $this->completed_at->gte(now()->subDays($service->refill_days));
        $activeRefill = $this->relationLoaded('refills')
            ? $this->refills->first(fn ($r) => in_array($r->status, [RefillStatus::Pending, RefillStatus::Processing], true))
            : null;

        return [
            'id' => $this->id,
            'service_id' => $this->service_id,
            'service_name' => $service->name,
            'link' => $this->link,
            'quantity' => $total,
            'start_count' => $this->start_count,
            'remains' => $this->remains,
            'charge' => $this->amount($request, Money::sub($this->charge, $this->refunded_amount)),
            'refunded' => Money::isPositive($this->refunded_amount) ? $this->amount($request, $this->refunded_amount) : null,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'status_color' => $this->status->getColor(),
            'runs' => $this->runs,
            'interval' => $this->interval,
            'created_at' => $this->time($request, $this->created_at),
            'can_cancel' => in_array($this->status, OrderStatus::active(), true) && ! $this->cancel_requested_at
                && ($service->cancel || ! $this->isSentToProvider()),
            'cancel_requested' => $this->cancel_requested_at !== null,
            'can_refill' => $service->refill && $refillWindowOk && ! $activeRefill
                && in_array($this->status, [OrderStatus::Completed, OrderStatus::Partial], true),
            'refilling' => $activeRefill !== null,
        ];
    }
}
