<?php

return [

    // Tài khoản admin tạo bởi AdminUserSeeder.
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    'api' => [
        // Số request / phút cho mỗi API key của khách.
        'rate_limit_per_minute' => 300,
        'log_retention_days' => 30,
        // Tối đa số ID trong 1 lần gọi status/refill/cancel nhiều đơn.
        'max_batch' => 100,
    ],

    'orders' => [
        // Số đơn gửi mỗi lần gọi status sang provider.
        'sync_batch' => 100,
        // Không kiểm tra lại đơn vừa kiểm tra trong vòng X phút.
        'recheck_after_minutes' => 3,
        // Chờ bao lâu (giây) trước khi gửi lại đơn lỗi, theo số lần đã thử.
        'send_backoff' => [30, 60, 300, 900, 1800],
    ],

    'deposits' => [
        'code_prefix' => 'NAP',
        // User tự nhận giao dịch: chênh lệch tối đa so với phiếu nạp.
        'claim_tolerance_percent' => 10,
        // Phần lẻ nhận diện phiếu crypto: 0.001 → 0.999.
        'crypto_fraction_max' => 999,
    ],

    'crypto' => [
        'tron' => [
            'api_url' => 'https://api.trongrid.io',
        ],
        'evm' => [
            // Etherscan API V2 – dùng chung cho nhiều chain qua `chainid` (BSC = 56).
            'api_url' => 'https://api.etherscan.io/v2/api',
        ],
    ],

    'exchange_rates' => [
        'api_url' => 'https://open.er-api.com/v6/latest/USD',
    ],

    // Giá trị mặc định cho bảng `settings` (admin chỉnh được lúc chạy).
    'settings' => [
        'order_max_send_attempts' => 5,
        'deposit_claim_window_hours' => 72,
        'low_provider_balance_alert' => 50,
        // % lãi tự áp khi đồng bộ giá provider; null = không tự đổi giá bán.
        'price_sync_markup_percent' => null,
    ],

];
