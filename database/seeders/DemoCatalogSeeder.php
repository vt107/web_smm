<?php

namespace Database\Seeders;

use App\Enums\ActiveStatus;
use App\Enums\Fulfillment;
use App\Enums\Platform;
use App\Enums\ServiceType;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Service;
use App\Models\User;
use App\Services\BalanceService;
use App\Support\Money;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu để test local: danh mục + dịch vụ của các nền tảng phổ biến và 1 tài khoản khách có sẵn số dư.
 * Dịch vụ để chế độ "làm tay" nên đơn không gửi đi đâu – admin tự cập nhật trạng thái trong /admin.
 *
 * Chạy: php artisan db:seed --class=DemoCatalogSeeder  (chạy lại nhiều lần không bị trùng)
 */
class DemoCatalogSeeder extends Seeder
{
    private const DEMO_EMAIL = 'demo@example.com';

    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $count = 0;

        foreach ($this->catalog() as $sort => [$platform, $categoryName, $services]) {
            $category = Category::query()->updateOrCreate(
                ['name' => $categoryName],
                ['platform' => $platform, 'sort' => $sort, 'status' => ActiveStatus::Active],
            );

            foreach ($services as $i => $service) {
                $this->upsertService($category, $i, ...$service);
                $count++;
            }
        }

        $this->command?->info("Đã tạo/cập nhật {$count} dịch vụ mẫu.");
        $this->seedDemoCustomer();
    }

    /**
     * [nền tảng, tên danh mục, [[tên, giá/1000 USD, min, max, loại, refill (ngày|null), huỷ, drip-feed, mô tả]]]
     */
    private function catalog(): array
    {
        $default = ServiceType::Default;

        return [
            [Platform::Instagram, 'Instagram Followers', [
                ['Instagram Followers [Giá rẻ] [Không bảo hành]', '0.90', 50, 50000, $default, null, false, true, 'Bắt đầu: 0–1 giờ · Tốc độ: 10K/ngày · Có thể tụt.'],
                ['Instagram Followers [Chất lượng cao] [Bảo hành 30 ngày]', '2.40', 100, 100000, $default, 30, true, true, 'Bắt đầu: 0–30 phút · Tốc độ: 20K/ngày · Tài khoản có ảnh đại diện.'],
            ]],
            [Platform::Instagram, 'Instagram Likes & Views', [
                ['Instagram Likes [Nhanh]', '0.25', 20, 50000, $default, null, true, true, 'Bắt đầu: tức thì · Tốc độ: 50K/ngày.'],
                ['Instagram Reels Views', '0.03', 100, 10000000, $default, null, false, true, 'Bắt đầu: tức thì · Tốc độ: 1M/ngày.'],
                ['Instagram Custom Comments', '12.00', 5, 1000, ServiceType::CustomComments, null, false, false, 'Mỗi dòng 1 bình luận · Bắt đầu: 0–2 giờ.'],
            ]],
            [Platform::TikTok, 'TikTok Followers', [
                ['TikTok Followers [Bảo hành 30 ngày]', '1.80', 100, 100000, $default, 30, true, true, 'Bắt đầu: 0–1 giờ · Tốc độ: 10K/ngày.'],
            ]],
            [Platform::TikTok, 'TikTok Likes & Views', [
                ['TikTok Likes', '0.35', 50, 100000, $default, null, true, true, 'Bắt đầu: tức thì · Tốc độ: 50K/ngày.'],
                ['TikTok Views', '0.01', 100, 100000000, $default, null, false, true, 'Bắt đầu: tức thì · Tốc độ: 5M/ngày.'],
                ['TikTok Shares', '0.60', 50, 100000, $default, null, false, false, 'Bắt đầu: 0–1 giờ.'],
            ]],
            [Platform::YouTube, 'YouTube', [
                ['YouTube Views [Có thời gian xem]', '1.50', 500, 1000000, $default, 30, false, true, 'Bắt đầu: 0–6 giờ · Tốc độ: 5K/ngày · Retention 1–3 phút.'],
                ['YouTube Subscribers [Bảo hành 30 ngày]', '14.00', 50, 20000, $default, 30, false, false, 'Bắt đầu: 0–12 giờ · Tốc độ: 500/ngày.'],
                ['YouTube Likes', '1.20', 20, 50000, $default, 30, true, false, 'Bắt đầu: 0–1 giờ.'],
                ['YouTube 4000 giờ xem (gói)', '45.00', 1, 1, ServiceType::Package, null, false, false, 'Gói 4000 giờ xem cho video dài trên 1 giờ · Hoàn thành 7–15 ngày.'],
            ]],
            [Platform::Facebook, 'Facebook', [
                ['Facebook Page Likes + Followers', '2.50', 100, 50000, $default, 30, true, false, 'Bắt đầu: 0–2 giờ · Tốc độ: 5K/ngày.'],
                ['Facebook Post Likes', '0.90', 50, 50000, $default, null, true, true, 'Bắt đầu: 0–30 phút.'],
                ['Facebook Video Views', '0.40', 500, 5000000, $default, null, false, true, 'Bắt đầu: 0–1 giờ · Tốc độ: 100K/ngày.'],
            ]],
            [Platform::Telegram, 'Telegram', [
                ['Telegram Channel Members [Bảo hành 15 ngày]', '1.10', 100, 100000, $default, 15, true, true, 'Bắt đầu: 0–1 giờ · Tốc độ: 20K/ngày.'],
                ['Telegram Post Views (1 bài)', '0.05', 100, 1000000, $default, null, false, false, 'Bắt đầu: tức thì.'],
                ['Telegram Poll Votes', '0.80', 50, 100000, ServiceType::Poll, null, false, false, 'Nhập số thứ tự đáp án cần bình chọn.'],
            ]],
            [Platform::Twitter, 'Twitter (X)', [
                ['Twitter Followers', '3.50', 100, 50000, $default, 30, true, false, 'Bắt đầu: 0–2 giờ · Tốc độ: 5K/ngày.'],
                ['Twitter Likes', '1.50', 20, 20000, $default, null, true, true, 'Bắt đầu: 0–30 phút.'],
                ['Twitter Mentions (danh sách username)', '6.00', 10, 5000, ServiceType::MentionsCustomList, null, false, false, 'Mỗi dòng 1 username cần mention.'],
            ]],
            [Platform::Spotify, 'Spotify', [
                ['Spotify Plays', '0.90', 1000, 10000000, $default, 30, false, true, 'Bắt đầu: 0–24 giờ · Tốc độ: 50K/ngày · Lượt nghe từ nhiều quốc gia.'],
                ['Spotify Followers (nghệ sĩ / playlist)', '1.20', 100, 100000, $default, 30, true, false, 'Bắt đầu: 0–12 giờ.'],
            ]],
        ];
    }

    private function upsertService(
        Category $category, int $sort, string $name, string $rate, int $min, int $max, ServiceType $type,
        ?int $refillDays, bool $cancel, bool $dripfeed, string $description,
    ): void {
        Service::query()->updateOrCreate(['name' => $name], [
            'category_id' => $category->id,
            'fulfillment' => Fulfillment::Manual,
            'provider_id' => null,
            'provider_service_id' => null,
            'type' => $type,
            'rate' => $rate,
            'provider_rate' => Money::div($rate, '1.35'), // giả lập giá vốn ~ lãi 35%
            'min' => $min,
            'max' => $max,
            'refill' => $refillDays !== null,
            'refill_days' => $refillDays,
            'cancel' => $cancel,
            'dripfeed' => $dripfeed && $type->supportsDripfeed(),
            'description' => $description,
            'sort' => $sort,
            'status' => ActiveStatus::Active,
        ]);
    }

    /** Tài khoản khách demo@example.com / password với $100 số dư (chỉ cộng khi tạo lần đầu). */
    private function seedDemoCustomer(): void
    {
        $user = User::query()->firstOrCreate(['email' => self::DEMO_EMAIL], [
            'name' => 'Khách Demo',
            'username' => 'demo',
            'password' => self::DEMO_PASSWORD,
        ]);

        if ($user->wasRecentlyCreated) {
            app(BalanceService::class)->change($user, '100', TransactionType::AdminAdd, description: 'Số dư test');
        }

        $this->command?->info('Tài khoản khách: '.self::DEMO_EMAIL.' / '.self::DEMO_PASSWORD.' (số dư $'.Money::format($user->fresh()->balance).')');
    }
}
