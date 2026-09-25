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

    // Provider giả lập (App\Http\Controllers\Sandbox) để test luồng gửi đơn mà không tốn tiền thật.
    // TẮT ở production.
    'sandbox' => [
        'enabled' => (bool) env('SANDBOX_PROVIDER_ENABLED', false),
        'key' => env('SANDBOX_PROVIDER_KEY', 'sandbox-secret'),
        // URL mà queue worker gọi tới (trong Docker: tên service "app").
        'url' => env('SANDBOX_PROVIDER_URL', 'http://app:8000/api/sandbox-provider/v2'),
        // Độ dài mỗi giai đoạn trạng thái đơn (phút).
        'stage_minutes' => (int) env('SANDBOX_STAGE_MINUTES', 1),
    ],

    'exchange_rates' => [
        'api_url' => 'https://open.er-api.com/v6/latest/USD',
    ],

    // Cấu hình website mặc định – admin sửa ở trang "Website settings" (lưu vào bảng settings, key "site.*").
    'site' => [
        'name' => env('APP_NAME', 'SMM Panel'),
        'tagline' => 'Cheapest & fastest SMM panel',
        'description' => 'Buy followers, likes, views and more for Instagram, TikTok, YouTube, Facebook, Telegram and X. Instant start, automatic refunds, refill guarantee and a reseller API.',
        'logo' => null,
        'show_name' => true,
        'favicon' => null,
        'seo_title' => null,
        'og_image' => null,
        'allow_indexing' => true,
        'google_analytics_id' => null,
        'hero_title' => 'The fastest & most affordable SMM panel',
        'hero_subtitle' => 'Grow your social media with high-quality followers, likes and views. Orders start within minutes, failed orders are refunded automatically, and our API lets you resell every service.',
        'faq' => [
            ['question' => 'What is an SMM panel?', 'answer' => 'An SMM (social media marketing) panel is an online shop where you can buy social media services such as followers, likes, views and comments for Instagram, TikTok, YouTube, Facebook and more.'],
            ['question' => 'How fast will my order start?', 'answer' => 'Most orders start within a few minutes. The start time and average completion time are shown on every service before you order.'],
            ['question' => 'What happens if an order is not fully delivered?', 'answer' => 'If an order is canceled or only partially delivered, the undelivered part is refunded to your balance automatically.'],
            ['question' => 'Do you offer refills?', 'answer' => 'Services marked with a refill guarantee can be refilled for free from your orders page if the count drops during the guarantee period.'],
            ['question' => 'Which payment methods do you accept?', 'answer' => 'You can add funds by bank transfer (VietQR) or with USDT on TRON (TRC20) and BNB Smart Chain (BEP20). Crypto deposits receive a bonus.'],
            ['question' => 'Can I resell your services?', 'answer' => 'Yes. Generate an API key on your account page and connect it to your own panel – our API follows the standard PerfectPanel format.'],
        ],
        'support_email' => null,
        'telegram_url' => null,
        'whatsapp_url' => null,
        'footer_text' => null,
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
