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

        $this->command?->info("Seeded {$count} demo services.");
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
                ['Instagram Followers [Cheap] [No Refill]', '0.90', 50, 50000, $default, null, false, true, 'Start: 0–1 hour · Speed: 10K/day · Drops possible.'],
                ['Instagram Followers [High Quality] [30 Days Refill]', '2.40', 100, 100000, $default, 30, true, true, 'Start: 0–30 min · Speed: 20K/day · Accounts with profile pictures.'],
            ]],
            [Platform::Instagram, 'Instagram Likes & Views', [
                ['Instagram Likes [Nhanh]', '0.25', 20, 50000, $default, null, true, true, 'Start: instant · Speed: 50K/day.'],
                ['Instagram Reels Views', '0.03', 100, 10000000, $default, null, false, true, 'Start: instant · Speed: 1M/day.'],
                ['Instagram Custom Comments', '12.00', 5, 1000, ServiceType::CustomComments, null, false, false, 'One comment per line · Start: 0–2 hours.'],
            ]],
            [Platform::TikTok, 'TikTok Followers', [
                ['TikTok Followers [30 Days Refill]', '1.80', 100, 100000, $default, 30, true, true, 'Start: 0–1 hour · Speed: 10K/day.'],
            ]],
            [Platform::TikTok, 'TikTok Likes & Views', [
                ['TikTok Likes', '0.35', 50, 100000, $default, null, true, true, 'Start: instant · Speed: 50K/day.'],
                ['TikTok Views', '0.01', 100, 100000000, $default, null, false, true, 'Start: instant · Speed: 5M/day.'],
                ['TikTok Shares', '0.60', 50, 100000, $default, null, false, false, 'Start: 0–1 hour.'],
            ]],
            [Platform::YouTube, 'YouTube', [
                ['YouTube Views [With Watch Time]', '1.50', 500, 1000000, $default, 30, false, true, 'Start: 0–6 hours · Speed: 5K/day · Retention 1–3 min.'],
                ['YouTube Subscribers [30 Days Refill]', '14.00', 50, 20000, $default, 30, false, false, 'Start: 0–12 hours · Speed: 500/day.'],
                ['YouTube Likes', '1.20', 20, 50000, $default, 30, true, false, 'Start: 0–1 hour.'],
                ['YouTube 4000 Watch Hours (package)', '45.00', 1, 1, ServiceType::Package, null, false, false, '4000 watch hours package for videos over 1 hour · Completes in 7–15 days.'],
            ]],
            [Platform::Facebook, 'Facebook', [
                ['Facebook Page Likes + Followers', '2.50', 100, 50000, $default, 30, true, false, 'Start: 0–2 hours · Speed: 5K/day.'],
                ['Facebook Post Likes', '0.90', 50, 50000, $default, null, true, true, 'Start: 0–30 min.'],
                ['Facebook Video Views', '0.40', 500, 5000000, $default, null, false, true, 'Start: 0–1 hour · Speed: 100K/day.'],
            ]],
            [Platform::Telegram, 'Telegram', [
                ['Telegram Channel Members [15 Days Refill]', '1.10', 100, 100000, $default, 15, true, true, 'Start: 0–1 hour · Speed: 20K/day.'],
                ['Telegram Post Views (1 post)', '0.05', 100, 1000000, $default, null, false, false, 'Start: instant.'],
                ['Telegram Poll Votes', '0.80', 50, 100000, ServiceType::Poll, null, false, false, 'Enter the number of the poll answer to vote for.'],
            ]],
            [Platform::Twitter, 'Twitter (X)', [
                ['Twitter Followers', '3.50', 100, 50000, $default, 30, true, false, 'Start: 0–2 hours · Speed: 5K/day.'],
                ['Twitter Likes', '1.50', 20, 20000, $default, null, true, true, 'Start: 0–30 min.'],
                ['Twitter Mentions (custom usernames)', '6.00', 10, 5000, ServiceType::MentionsCustomList, null, false, false, 'One username to mention per line.'],
            ]],
            [Platform::Spotify, 'Spotify', [
                ['Spotify Plays', '0.90', 1000, 10000000, $default, 30, false, true, 'Start: 0–24 hours · Speed: 50K/day · Worldwide plays.'],
                ['Spotify Followers (artist / playlist)', '1.20', 100, 100000, $default, 30, true, false, 'Start: 0–12 hours.'],
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
            'name' => 'Demo Customer',
            'username' => 'demo',
            'password' => self::DEMO_PASSWORD,
        ]);

        if ($user->wasRecentlyCreated) {
            app(BalanceService::class)->change($user, '100', TransactionType::AdminAdd, description: 'Test balance');
        }

        $this->command?->info('Customer account: '.self::DEMO_EMAIL.' / '.self::DEMO_PASSWORD.' (balance $'.Money::format($user->fresh()->balance).')');
    }
}
