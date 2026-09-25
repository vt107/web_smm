<?php

namespace Database\Factories;

use App\Enums\ActiveStatus;
use App\Enums\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    public function definition(): array
    {
        return $this->sepayAttributes();
    }

    public function sepay(): static
    {
        return $this->state(fn () => $this->sepayAttributes());
    }

    public function tron(): static
    {
        return $this->state(fn () => [
            'name' => 'USDT – TRC20',
            'gateway' => PaymentGateway::Crypto,
            'currency' => 'USDT',
            'exchange_rate' => 1,
            'min_amount' => 5,
            'max_amount' => 10000,
            'bonus_percent' => 5,
            'config' => [
                'network' => 'tron',
                'address' => 'TWalletAddressForTests000000000000',
                'token_contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'token_decimals' => 6,
            ],
        ]);
    }

    private function sepayAttributes(): array
    {
        return [
            'name' => 'Chuyển khoản ngân hàng (VietQR)',
            'gateway' => PaymentGateway::Sepay,
            'currency' => 'VND',
            'exchange_rate' => 25000,
            'min_amount' => 1,
            'max_amount' => 5000,
            'bonus_percent' => 0,
            'deposit_ttl_minutes' => 60,
            'config' => [
                'bank_name' => 'MBBank',
                'account_number' => '0123456789',
                'account_name' => 'NGUYEN VAN A',
                'webhook_key' => 'sepay-test-key',
            ],
            'status' => ActiveStatus::Active,
        ];
    }
}
