<?php

namespace Database\Seeders;

use App\Support\Demo\DemoMode;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ReferenceDataSeeder::class,
            PaymentMethodSeeder::class,
        ]);

        // Bản demo: DemoSeeder tự tạo admin / nhân viên / khách với mật khẩu cố định (config/demo.php).
        $this->call(DemoMode::enabled() ? DemoSeeder::class : AdminUserSeeder::class);
    }
}
