<?php

namespace App\Console\Commands;

use App\Exceptions\ProviderException;
use App\Models\Provider;
use App\Services\Providers\ServiceSyncService;
use Illuminate\Console\Command;

class ImportProviderServices extends Command
{
    protected $signature = 'providers:import
        {provider : ID provider}
        {--markup=30 : % lãi cộng vào giá vốn}
        {--only= : Chỉ import các service ID của provider, phân tách bằng dấu phẩy}';

    protected $description = 'Tạo dịch vụ mới từ danh sách dịch vụ của provider (bỏ qua dịch vụ đã có)';

    public function handle(ServiceSyncService $sync): int
    {
        $provider = Provider::query()->findOrFail($this->argument('provider'));
        $only = $this->option('only') ? array_map('trim', explode(',', $this->option('only'))) : null;

        try {
            $created = $sync->import($provider, (string) $this->option('markup'), $only);
        } catch (ProviderException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Imported {$created} services from [{$provider->name}]");

        return self::SUCCESS;
    }
}
