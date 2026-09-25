<?php

namespace Database\Seeders;

use App\Enums\ActiveStatus;
use App\Enums\PaymentGateway;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/** Kênh nạp mặc định. Kênh chưa cấu hình tài khoản/ví trong .env sẽ ở trạng thái disabled. */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $sepay = config('services.sepay');
        $crypto = config('services.crypto');

        $this->upsert('Bank Transfer (VietQR)', [
            'gateway' => PaymentGateway::Sepay,
            'currency' => 'VND',
            'exchange_rate' => 26000,
            'min_amount' => 1,
            'max_amount' => 5000,
            'bonus_percent' => 0,
            'instructions' => 'Transfer the exact amount and use the deposit code as the transfer description.',
            'config' => [
                'bank_name' => $sepay['bank_name'],
                'account_number' => $sepay['account_number'],
                'account_name' => $sepay['account_name'],
                'webhook_key' => $sepay['webhook_key'],
            ],
            'sort' => 1,
        ], filled($sepay['account_number']) && filled($sepay['webhook_key']));

        $this->upsert('USDT – TRC20', [
            'gateway' => PaymentGateway::Crypto,
            'currency' => 'USDT',
            'exchange_rate' => 1,
            'min_amount' => 5,
            'max_amount' => 10000,
            'bonus_percent' => 5,
            'instructions' => 'Send USDT on TRON (TRC20) only. Send the exact amount shown, including decimals.',
            'config' => [
                'network' => 'tron',
                'address' => $crypto['usdt_trc20_address'],
                'token_contract' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                'token_decimals' => 6,
                'api_key' => $crypto['trongrid_api_key'],
            ],
            'sort' => 2,
        ], filled($crypto['usdt_trc20_address']));

        $this->upsert('USDT – BEP20', [
            'gateway' => PaymentGateway::Crypto,
            'currency' => 'USDT',
            'exchange_rate' => 1,
            'min_amount' => 5,
            'max_amount' => 10000,
            'bonus_percent' => 5,
            'instructions' => 'Send USDT on BNB Smart Chain (BEP20) only. Send the exact amount shown, including decimals.',
            'config' => [
                'network' => 'bsc',
                'chain_id' => 56,
                'address' => $crypto['usdt_bep20_address'],
                'token_contract' => '0x55d398326f99059fF775485246999027B3197955',
                'token_decimals' => 18,
                'min_confirmations' => 15,
                'api_key' => $crypto['etherscan_api_key'],
            ],
            'sort' => 3,
        ], filled($crypto['usdt_bep20_address']));
    }

    private function upsert(string $name, array $attributes, bool $configured): void
    {
        PaymentMethod::query()->updateOrCreate(['name' => $name], $attributes + [
            'status' => $configured ? ActiveStatus::Active : ActiveStatus::Disabled,
        ]);
    }
}
