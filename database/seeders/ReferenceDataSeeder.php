<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Level;
use Illuminate\Database\Seeder;

/** Dữ liệu nền bắt buộc: cấp bậc và tiền tệ hiển thị. */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Member', 'min_spent' => 0, 'discount_percent' => 0, 'is_default' => true, 'sort' => 1, 'color' => '#9ca3af'],
            ['name' => 'Silver', 'min_spent' => 100, 'discount_percent' => 3, 'is_default' => false, 'sort' => 2, 'color' => '#94a3b8'],
            ['name' => 'Gold', 'min_spent' => 500, 'discount_percent' => 5, 'is_default' => false, 'sort' => 3, 'color' => '#eab308'],
            ['name' => 'Diamond', 'min_spent' => 2000, 'discount_percent' => 8, 'is_default' => false, 'sort' => 4, 'color' => '#06b6d4'],
        ];

        foreach ($levels as $level) {
            Level::query()->updateOrCreate(['name' => $level['name']], $level + ['auto_upgrade' => true]);
        }

        // Tỉ giá ban đầu – lệnh currencies:update-rates cập nhật hằng ngày.
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'symbol_position' => 'before', 'rate' => 1, 'decimals' => 4, 'auto_update' => false],
            ['code' => 'VND', 'name' => 'Vietnamese Dong', 'symbol' => '₫', 'symbol_position' => 'after', 'rate' => 26000, 'decimals' => 0, 'auto_update' => true],
            ['code' => 'THB', 'name' => 'Thai Baht', 'symbol' => '฿', 'symbol_position' => 'before', 'rate' => 32.5, 'decimals' => 2, 'auto_update' => true],
        ];

        foreach ($currencies as $currency) {
            // Tỉ giá chỉ đặt lần đầu (sau đó cron cập nhật), tên/ký hiệu luôn đồng bộ theo seeder.
            Currency::query()
                ->firstOrCreate(['code' => $currency['code']], $currency + ['status' => 'active'])
                ->update(collect($currency)->only(['name', 'symbol', 'symbol_position', 'decimals'])->all());
        }
    }
}
