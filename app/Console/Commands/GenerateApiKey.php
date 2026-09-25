<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateApiKey extends Command
{
    protected $signature = 'users:api-key {email}';

    protected $description = 'Regenerate a user\'s API key (the old key stops working)';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->firstOrFail();

        $this->info("API key for {$user->email}: {$user->generateApiKey()}");
        $this->warn('The key is shown only once, please save it.');

        return self::SUCCESS;
    }
}
