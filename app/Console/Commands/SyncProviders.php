<?php

namespace App\Console\Commands;

use App\Enums\ActiveStatus;
use App\Enums\TelegramEvent;
use App\Exceptions\ProviderException;
use App\Models\Provider;
use App\Models\Setting;
use App\Services\Providers\ServiceSyncService;
use App\Services\TelegramNotifier;
use App\Support\Money;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncProviders extends Command
{
    protected $signature = 'providers:sync {provider? : Provider ID, empty = all}';

    protected $description = 'Sync costs, min/max, service status and balance from providers';

    public function handle(ServiceSyncService $sync, TelegramNotifier $telegram): int
    {
        $providers = Provider::query()
            ->where('status', ActiveStatus::Active)
            ->when($this->argument('provider'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        foreach ($providers as $provider) {
            try {
                $stats = $sync->sync($provider);
            } catch (ProviderException $e) {
                $this->error("[{$provider->name}] {$e->getMessage()}");
                $telegram->notify(
                    TelegramEvent::ProviderError,
                    "🔌 <b>Provider error</b>\n".e($provider->name).': '.e($e->getMessage()),
                    dedupeKey: "provider-error:{$provider->id}",
                    dedupeMinutes: 60,
                );

                continue;
            }

            $this->info("[{$provider->name}] updated {$stats['updated']}, disabled {$stats['disabled']}, balance {$provider->balance}");

            if ($provider->balance !== null && Money::cmp($provider->balance, Setting::get('low_provider_balance_alert')) < 0) {
                Log::warning("Provider [{$provider->name}] balance is low: {$provider->balance}");
                $telegram->notify(
                    TelegramEvent::ProviderLowBalance,
                    "🪫 <b>Low provider balance</b>\n".e($provider->name).': $'.Money::format($provider->balance).'. Top up to keep orders running.',
                    dedupeKey: "provider-low:{$provider->id}",
                );
            }
        }

        return self::SUCCESS;
    }
}
