<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('smm.admin.email');

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $password = config('smm.admin.password') ?: Str::password(16, symbols: false);

        $admin = User::query()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => $email,
            'password' => $password,
        ]);

        $admin->forceFill(['role' => UserRole::Admin, 'email_verified_at' => now()])->save();

        if (! config('smm.admin.password')) {
            $this->command?->warn("Admin {$email} created with password: {$password}");
        }
    }
}
