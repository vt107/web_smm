<?php

/*
|--------------------------------------------------------------------------
| Chế độ demo (chỉ xem)
|--------------------------------------------------------------------------
|
| Bật DEMO_MODE=true khi deploy bản giới thiệu cho khách xem:
| - Mọi lệnh ghi SQL phát sinh từ request web bị chặn (App\Support\Demo\DemoMode),
|   trừ các bảng hạ tầng trong `writable_tables` và các câu lệnh khớp `allowed_write_patterns`.
| - Form POST / PUT / PATCH / DELETE bị chặn trước khi vào controller, trừ `allowed_routes`.
| - Nút "Demo" nổi ở góc màn hình liệt kê các khu vực + tài khoản đăng nhập sẵn (`portals`).
| - `php artisan demo:reset` dựng lại database với dữ liệu mẫu (lịch chạy hằng ngày).
|
| Lệnh artisan / queue / scheduler / test (chạy CLI) không bị chặn.
|
*/

return [

    'enabled' => (bool) env('DEMO_MODE', false),

    // Site giao diện tiếng Anh → thông báo + widget tiếng Anh (khách xem demo đọc cùng ngôn ngữ với site).
    'message' => 'This is a read-only demo: creating, editing and deleting are disabled.',

    // Chữ trên nút Demo / trang 403 (mặc định tiếng Việt trong resources/views/demo/*.blade.php).
    'texts' => [
        'lang' => 'en',
        'fab' => 'Demo',
        'fab_long' => ' · accounts',
        'panel_label' => 'Demo information',
        'badge' => 'Demo · read only',
        'title' => 'Explore :app',
        'intro' => 'All data is sample data. You can open every screen; creating, editing and deleting are disabled.',
        'close' => 'Close',
        'open' => 'Open',
        'logged_in' => 'signed in',
        'email' => 'Email',
        'password' => 'Password',
        'copy' => 'Copy',
        'copied' => 'Copied: ',
        'login_as' => 'Sign in with this account',
        'footer' => 'Demo data is reset automatically every day.',
        'blocked_title' => 'Read-only demo',
        'back' => 'Go back',
    ],

    // Giờ dựng lại dữ liệu demo mỗi ngày (múi giờ app). null = không tự reset.
    'reset_at' => env('DEMO_RESET_AT', '04:00'),

    // Bảng hạ tầng vẫn cho ghi (phiên đăng nhập, cache, rate limit).
    'writable_tables' => [
        'sessions',
        'cache',
        'cache_locks',
    ],

    // Regex (so với câu SQL) cho phép ghi dù bảng không nằm trong writable_tables.
    'allowed_write_patterns' => [
        // "Ghi nhớ đăng nhập"
        '/^update [`"]?users[`"]? set [`"]?remember_token[`"]? = \?/i',
    ],

    // Route (tên hoặc pattern path) được phép nhận POST / PUT / PATCH / DELETE.
    // Request Livewire (livewire.update) luôn đi qua, lệnh ghi bên trong do guard SQL chặn.
    'allowed_routes' => [
        'login',
        'logout',
        'filament.admin.auth.logout',
        // API reseller: action đọc (services, status, balance...) vẫn chạy; add / refill / cancel
        // trả {"error": ...} trong V2Controller (đúng format API).
        'api/v2',
    ],

    /*
     * Các khu vực hiển thị trong nút Demo.
     * url / login_url là path tương đối (qua helper url()).
     * accounts[].key là duy nhất toàn file: /login?demo=<key> sẽ điền sẵn tài khoản đó.
     * Tài khoản phải khớp Database\Seeders\DemoSeeder.
     */
    'portals' => [
        [
            'label' => 'Admin panel',
            'description' => 'Dashboard, orders in every status, services & providers, deposits, incoming transfers, ledger, customers, levels, tickets, API logs and website settings.',
            'url' => '/admin',
            'login_url' => '/admin/login',
            'accounts' => [
                ['key' => 'admin', 'role' => 'Administrator', 'email' => 'admin@example.com', 'password' => 'password'],
            ],
        ],
        [
            'label' => 'Support staff',
            'description' => 'Limited admin role: view orders, refills, customers and services, read support tickets.',
            'url' => '/admin',
            'login_url' => '/admin/login',
            'accounts' => [
                ['key' => 'support', 'role' => 'Support staff', 'email' => 'support@example.com', 'password' => 'password'],
            ],
        ],
        [
            'label' => 'Customer area',
            'description' => 'Place orders, order history with refill / cancel, add funds (bank transfer, USDT), balance history, tickets, service updates, API docs. Reseller API key for testing: demo1234demo1234demo1234demo1234demo1234 (POST /api/v2).',
            'url' => '/dashboard',
            'login_url' => '/login',
            'accounts' => [
                ['key' => 'customer', 'role' => 'Customer (Gold level)', 'email' => 'demo@example.com', 'password' => 'password'],
                ['key' => 'reseller', 'role' => 'Reseller (Diamond level, uses the API)', 'email' => 'reseller@example.com', 'password' => 'password'],
            ],
        ],
        [
            'label' => 'Public website',
            'description' => 'Landing page and public price list (/pricing) – no sign-in needed.',
            'url' => '/',
            'accounts' => [],
        ],
    ],

];
