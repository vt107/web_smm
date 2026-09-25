<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateApiKey extends Command
{
    protected $signature = 'users:api-key {email}';

    protected $description = 'Tạo lại API key cho user (key cũ mất hiệu lực)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->firstOrFail();

        $this->info("API key for {$user->email}: {$user->generateApiKey()}");
        $this->warn('Key chỉ hiển thị 1 lần, hãy lưu lại.');

        return self::SUCCESS;
    }
}
