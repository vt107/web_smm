<?php

namespace App\Console\Commands;

use App\Support\Demo\DemoMode;
use Illuminate\Console\Command;

/**
 * Dựng lại database demo: migrate:fresh + seed dữ liệu mẫu. Chỉ chạy khi DEMO_MODE=true.
 * Scheduler gọi hằng ngày lúc config('demo.reset_at') để dữ liệu (ngày giờ tương đối) luôn mới.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : Bỏ qua xác nhận}';

    protected $description = 'Xoá toàn bộ database và nạp lại dữ liệu demo (chỉ khi DEMO_MODE=true)';

    public function handle(): int
    {
        if (! DemoMode::enabled()) {
            $this->error('DEMO_MODE đang tắt: không reset database.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Xoá toàn bộ dữ liệu và nạp lại dữ liệu demo?')) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);
        $this->call('optimize:clear');

        $this->info('Đã nạp lại dữ liệu demo.');

        return self::SUCCESS;
    }
}
