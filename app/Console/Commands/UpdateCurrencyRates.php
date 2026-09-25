<?php

namespace App\Console\Commands;

use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'currencies:update-rates';

    protected $description = 'Cập nhật tỉ giá hiển thị (so với USD) cho các tiền tệ bật auto_update';

    public function handle(): int
    {
        $response = Http::timeout(20)->get(config('smm.exchange_rates.api_url'));
        $rates = $response->json('rates');

        if (! $response->successful() || ! is_array($rates)) {
            $this->error('Cannot fetch exchange rates: HTTP '.$response->status());

            return self::FAILURE;
        }

        foreach (Currency::query()->where('auto_update', true)->where('code', '!=', Currency::BASE)->get() as $currency) {
            if (isset($rates[$currency->code]) && $rates[$currency->code] > 0) {
                $currency->update(['rate' => $rates[$currency->code]]);
                $this->info("{$currency->code} = {$currency->rate}");
            }
        }

        return self::SUCCESS;
    }
}
