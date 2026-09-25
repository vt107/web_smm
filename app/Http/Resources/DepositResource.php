<?php

namespace App\Http\Resources;

use App\Enums\PaymentGateway;
use App\Models\Deposit;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Deposit */
class DepositResource extends JsonResource
{
    use Presenter;

    public function toArray(Request $request): array
    {
        $method = $this->paymentMethod;
        $isBank = $method->gateway === PaymentGateway::Sepay;
        $decimals = $isBank ? 0 : 3;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'method' => $method->name,
            'gateway' => $method->gateway->value,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'status_color' => $this->status->getColor(),
            'requested_amount' => Money::format($this->requested_amount),
            'paid_amount' => number_format((float) $this->paid_amount, $decimals, $isBank ? ',' : '.', $isBank ? '.' : ','),
            'paid_amount_raw' => rtrim(rtrim($this->paid_amount, '0'), '.'),
            'paid_currency' => $this->paid_currency,
            'pay_to' => $this->pay_to,
            'bank_name' => $isBank ? $method->configValue('bank_name') : null,
            'account_name' => $isBank ? $method->configValue('account_name') : null,
            'network' => $isBank ? null : $method->configValue('network'),
            'qr_url' => $isBank ? $this->vietQrUrl() : null,
            'instructions' => $method->instructions,
            'credited' => $this->amount ? $this->amount($request, Money::add($this->amount, $this->bonus)) : null,
            'created_at' => $this->time($request, $this->created_at),
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }

    /** Ảnh QR chuyển khoản do SePay tạo sẵn số tiền + nội dung. */
    private function vietQrUrl(): string
    {
        return 'https://qr.sepay.vn/img?'.http_build_query([
            'acc' => $this->pay_to,
            'bank' => $this->paymentMethod->configValue('bank_name'),
            'amount' => (int) $this->paid_amount,
            'des' => $this->code,
        ]);
    }
}
