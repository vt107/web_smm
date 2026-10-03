<?php

namespace Database\Seeders;

use App\Enums\ActiveStatus;
use App\Enums\DepositMatchedBy;
use App\Enums\DepositStatus;
use App\Enums\Fulfillment;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Platform;
use App\Enums\RefillStatus;
use App\Enums\ServiceType;
use App\Enums\TicketCategory;
use App\Enums\TransactionType;
use App\Enums\TransferStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\SmmException;
use App\Models\ApiLog;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Deposit;
use App\Models\Level;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Provider;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\OrderService;
use App\Services\Payments\DepositService;
use App\Services\PricingService;
use App\Services\TicketService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use SplPriorityQueue;

/**
 * Dữ liệu cho bản demo chỉ xem (DEMO_MODE=true, gọi từ DatabaseSeeder / demo:reset).
 *
 * Mô phỏng ~90 ngày hoạt động theo đúng trình tự thời gian (Carbon::setTestNow) và đi qua các service thật
 * (OrderService, BalanceService, DepositService, TicketService) nên số dư = tổng sổ cái, hoàn tiền / lên cấp /
 * service_updates đều khớp. Mọi ngày giờ tương đối so với lúc chạy; provider trong dữ liệu đều là giả
 * (Sandbox + domain .example) và scheduler không đồng bộ provider khi bật demo.
 *
 * Tài khoản phải khớp config/demo.php.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /** API key cố định của tài khoản reseller@example.com (ghi trong mô tả nút Demo để thử /api/v2). */
    public const API_KEY = 'demo1234demo1234demo1234demo1234demo1234';

    private const SIMULATED_DAYS = 90;

    private CarbonImmutable $now;

    private SplPriorityQueue $queue;

    private int $seq = 0;

    private string $passwordHash;

    private OrderService $orders;

    private BalanceService $balance;

    private DepositService $deposits;

    private PricingService $pricing;

    private TicketService $tickets;

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, array<string, mixed>> hồ sơ khách: weight, method, scale, registered_at... */
    private array $profiles = [];

    /** @var array<int, Service> */
    private array $services = [];

    /** @var array<string, PaymentMethod> sepay | trc20 | bep20 */
    private array $methods = [];

    private User $admin;

    /** @var list<User> */
    private array $staff = [];

    private int $sepayTxId = 48213790;

    private int $providerOrderId = 3816402;

    private array $stats = ['orders' => 0, 'skipped' => 0];

    public function run(): void
    {
        $startedAt = microtime(true);
        $this->now = CarbonImmutable::now();
        $this->passwordHash = Hash::make(self::PASSWORD);
        $this->queue = new SplPriorityQueue;
        $this->orders = app(OrderService::class);
        $this->balance = app(BalanceService::class);
        $this->deposits = app(DepositService::class);
        $this->pricing = app(PricingService::class);
        $this->tickets = app(TicketService::class);
        mt_srand(20260925);

        Storage::disk('public')->deleteDirectory('tickets');

        try {
            DB::transaction(function () {
                $this->travel($this->now->subDays(120)->setTime(9, 0));
                $this->seedReferenceData();
                $this->seedStaff();
                $this->seedCatalog();

                $this->scheduleCustomers();
                $this->scheduleOrders();
                $this->scheduleStandaloneDeposits();
                $this->scheduleUnmatchedTransfers();
                $this->scheduleCatalogChanges();
                $this->scheduleAdminAdjustments();
                $this->scheduleTickets();
                $this->scheduleApiErrors();
                $this->scheduleRefillRequests();
                $this->scheduleRecentOrders();

                $this->runTimeline();

                $this->travel($this->now);
                $this->finish();
            });
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        $this->command?->info(sprintf(
            'Demo data: %d customers, %d orders (%d skipped), %d deposits, %d tickets in %.1fs.',
            User::query()->where('role', UserRole::User)->count(),
            Order::query()->count(),
            $this->stats['skipped'],
            Deposit::query()->count(),
            Ticket::query()->count(),
            microtime(true) - $startedAt,
        ));
    }

    // ---------------------------------------------------------------------------------------------
    // Dòng thời gian
    // ---------------------------------------------------------------------------------------------

    private function travel(CarbonImmutable $at): void
    {
        Carbon::setTestNow($at);
        CarbonImmutable::setTestNow($at);
    }

    /** Hẹn 1 sự kiện. Sự kiện sau thời điểm hiện tại thật bị bỏ qua (đơn / phiếu giữ trạng thái đang dở). */
    private function at(CarbonImmutable $at, Closure $event): void
    {
        if ($at->greaterThan($this->now)) {
            return;
        }

        $this->queue->insert([$at, $event], [-$at->getTimestamp(), -(++$this->seq)]);
    }

    private function runTimeline(): void
    {
        while (! $this->queue->isEmpty()) {
            [$at, $event] = $this->queue->extract();
            $this->travel($at);
            $event($at);
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Dữ liệu nền: cấp bậc, tiền tệ, kênh nạp, cài đặt, nhân viên, danh mục
    // ---------------------------------------------------------------------------------------------

    private function seedReferenceData(): void
    {
        // Cấp chỉ admin gán tay (đại lý), không tự lên.
        Level::query()->updateOrCreate(['name' => 'Reseller'], [
            'min_spent' => 5000, 'discount_percent' => 12, 'auto_upgrade' => false, 'is_default' => false, 'sort' => 5, 'color' => '#7c3aed',
        ]);

        Currency::query()->updateOrCreate(['code' => 'EUR'], [
            'name' => 'Euro', 'symbol' => '€', 'symbol_position' => 'before', 'rate' => 0.92, 'decimals' => 2, 'auto_update' => true, 'status' => 'active',
        ]);
        Currency::query()->updateOrCreate(['code' => 'IDR'], [
            'name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'symbol_position' => 'before', 'rate' => 16350, 'decimals' => 0, 'auto_update' => true, 'status' => 'disabled',
        ]);

        // Kênh nạp: thông tin tài khoản / ví giả (không dùng giá trị trong .env), luôn bật để khách xem đủ 3 kênh.
        $fake = [
            'Bank Transfer (VietQR)' => ['sepay', [
                'bank_name' => 'MBBank', 'account_number' => '0000123456789', 'account_name' => 'CONG TY TNHH SMM DEMO', 'webhook_key' => 'demo-webhook-key',
            ]],
            'USDT – TRC20' => ['trc20', ['address' => 'TDemoWa11etAddressForSmmPane1Demo1']],
            'USDT – BEP20' => ['bep20', ['address' => '0xDe00000000000000000000000000000000Dem0']],
        ];

        foreach ($fake as $name => [$key, $config]) {
            $method = PaymentMethod::query()->where('name', $name)->firstOrFail();
            $method->fill([
                'config' => array_merge($method->config ?? [], $config, ['api_key' => null]),
                'status' => ActiveStatus::Active,
                // Bản demo không chạy deposits:expire → phiếu đang chờ trong ngày vẫn còn hạn khi khách xem.
                'deposit_ttl_minutes' => 1440,
            ])->save();
            $this->methods[$key] = $method;
        }

        $this->methods['trc20']->forceFill(['last_scanned_at' => $this->now->subSeconds(40), 'scan_cursor' => (string) $this->now->subSeconds(40)->getTimestampMs()])->save();
        $this->methods['bep20']->forceFill(['last_scanned_at' => $this->now->subSeconds(55), 'scan_cursor' => '62817450'])->save();

        foreach ([
            'site.name' => 'BoostPanel',
            'site.tagline' => 'Cheapest & fastest SMM panel',
            'site.support_email' => 'support@example.com',
            'site.telegram_url' => 'https://t.me/boostpanel_demo',
            'site.whatsapp_url' => 'https://wa.me/84900000000',
            'site.allow_indexing' => false,
            'site.telegram_chat_ids' => "100200300\n-1001002003004",
        ] as $key => $value) {
            Setting::set($key, $value);
        }

        config(['app.name' => 'BoostPanel']);
    }

    private function seedStaff(): void
    {
        $this->admin = $this->makeUser('admin', 'Administrator', 'admin@example.com', role: UserRole::Admin);
        $this->staff = [
            $this->makeUser('support', 'Linh Tran', 'support@example.com', role: UserRole::Support),
            $this->makeUser('minh.support', 'Minh Pham', 'minh.pham@example.com', role: UserRole::Support),
        ];
    }

    private function makeUser(string $username, string $name, string $email, string $currency = 'USD', string $timezone = 'Asia/Ho_Chi_Minh', UserRole $role = UserRole::User, ?int $referredBy = null): User
    {
        $user = User::query()->create([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $this->passwordHash,
            'currency' => $currency,
            'timezone' => $timezone,
            'referred_by' => $referredBy,
        ]);

        $user->forceFill(['role' => $role, 'email_verified_at' => now()])->save();

        return $this->users[$username] = $user;
    }

    private function seedCatalog(): void
    {
        // Provider giả lập (route /api/sandbox-provider) – cần key vì cột api_key không được rỗng.
        config(['smm.sandbox.key' => config('smm.sandbox.key') ?: 'sandbox-demo-key']);

        $this->callSilent([DemoCatalogSeeder::class, SandboxProviderSeeder::class]);

        // Danh mục sandbox chỉ để test kỹ thuật: ẩn với khách, admin vẫn thấy provider + dịch vụ.
        Category::query()->where('name', 'Sandbox (test provider)')->update(['status' => ActiveStatus::Disabled]);

        $sandbox = Provider::query()->where('name', 'Sandbox Provider')->firstOrFail();
        $sandbox->forceFill(['balance' => '1000', 'last_synced_at' => $this->now->subMinutes(38)])->save();

        $wholesale = Provider::query()->create([
            'name' => 'SMM Wholesale',
            'api_url' => 'https://api.smm-wholesale.example/v2',
            'api_key' => 'demo-provider-key-not-real',
            'driver' => 'perfectpanel',
            'status' => ActiveStatus::Active,
            'note' => 'Demo provider (fake domain). Main supplier for comments, mentions and traffic services.',
        ]);
        $wholesale->forceFill(['balance' => '412.35', 'last_synced_at' => $this->now->subMinutes(12)])->save();

        // Panel thật chủ yếu gửi đơn qua provider: chuyển dịch vụ mẫu (DemoCatalogSeeder để "làm tay") sang
        // SMM Wholesale, giữ vài dịch vụ làm tay để admin thấy luồng cập nhật trạng thái thủ công.
        $manual = ['YouTube 4000 Watch Hours (package)', 'Instagram Custom Comments', 'Telegram Poll Votes', 'Spotify Followers (artist / playlist)', 'Twitter Mentions (custom usernames)'];

        Service::query()->whereNull('provider_id')->whereNotIn('name', $manual)->orderBy('id')->get()
            ->each(fn (Service $service, int $i) => $service->forceFill([
                'fulfillment' => Fulfillment::Api,
                'provider_id' => $wholesale->id,
                'provider_service_id' => (string) (1001 + $i * 7),
            ])->save());

        $legacy = Provider::query()->create([
            'name' => 'Legacy Panel',
            'api_url' => 'https://legacy-panel.example/api/v2',
            'api_key' => 'demo-legacy-key-not-real',
            'driver' => 'perfectpanel',
            'status' => ActiveStatus::Disabled,
            'note' => 'Old supplier, replaced by SMM Wholesale. Kept for order history.',
        ]);
        $legacy->forceFill(['balance' => '3.20', 'last_synced_at' => $this->now->subDays(41)])->save();

        // Dịch vụ qua provider, đủ các loại còn thiếu trong DemoCatalogSeeder.
        $categories = [
            'ig' => Category::query()->create(['platform' => Platform::Instagram, 'name' => 'Instagram Comments & Mentions', 'sort' => 2, 'status' => ActiveStatus::Active]),
            'tt' => Category::query()->create(['platform' => Platform::TikTok, 'name' => 'TikTok Live & Saves', 'sort' => 6, 'status' => ActiveStatus::Active]),
            'yt' => Category::query()->create(['platform' => Platform::YouTube, 'name' => 'YouTube Shorts', 'sort' => 8, 'status' => ActiveStatus::Active]),
            'web' => Category::query()->create(['platform' => Platform::Other, 'name' => 'Website Traffic', 'sort' => 30, 'status' => ActiveStatus::Active]),
        ];

        $api = [
            // [danh mục, provider service id, tên, loại, giá vốn, min, max, refill ngày, huỷ, drip-feed, mô tả, thêm vào (ngày trước)]
            ['ig', '2101', 'Instagram Custom Comments Package (10 comments)', ServiceType::CustomCommentsPackage, '1.80', 1, 1, null, false, false, '10 custom comments, one per line · Start: 0–3 hours.'],
            ['ig', '2102', 'Instagram Mentions + Hashtags', ServiceType::MentionsWithHashtags, '3.20', 100, 20000, null, false, false, 'Mentions the usernames you provide together with your hashtags.'],
            ['ig', '2103', 'Instagram Mentions [Followers of a user]', ServiceType::MentionsUserFollowers, '2.60', 100, 50000, null, false, false, 'Mentions followers of the account you enter.'],
            ['ig', '2104', 'Instagram Comment Likes', ServiceType::CommentLikes, '4.10', 20, 5000, null, true, false, 'Likes on a specific comment: enter the comment owner username.'],
            ['ig', '2105', 'Instagram Comment Replies', ServiceType::CommentReplies, '9.50', 5, 500, null, false, false, 'Replies to a comment, one reply per line.'],
            ['tt', '2201', 'TikTok Live Stream Views [15 min]', ServiceType::Default, '2.90', 100, 20000, null, true, false, 'Viewers stay 15 minutes · Start: 0–5 min after you go live.', 64],
            ['tt', '2202', 'TikTok Saves', ServiceType::Default, '0.22', 100, 100000, null, true, true, 'Start: 0–1 hour · Speed: 20K/day.', 64],
            ['yt', '2301', 'YouTube Shorts Views [Non Drop]', ServiceType::Default, '0.75', 1000, 5000000, 30, true, true, 'Start: 0–3 hours · Speed: 200K/day.', 27],
            ['yt', '2302', 'YouTube Shorts Likes', ServiceType::Default, '0.95', 50, 50000, 30, true, false, 'Start: 0–1 hour.', 27],
            ['web', '2401', 'Website Traffic from Google (keyword)', ServiceType::WebTraffic, '0.60', 1000, 1000000, null, true, true, 'Organic-looking visits from Google search for your keyword. Choose country and device.', 8],
            ['web', '2402', 'Website Traffic – Social referrals', ServiceType::WebTraffic, '0.40', 1000, 1000000, null, true, true, 'Visits referred from Facebook, X and Reddit.', 8],
        ];

        foreach ($api as $i => [$cat, $pid, $name, $type, $cost, $min, $max, $refill, $cancel, $drip, $description]) {
            $addedDaysAgo = $api[$i][11] ?? null;
            $create = fn () => Service::query()->create([
                'category_id' => $categories[$cat]->id,
                'fulfillment' => Fulfillment::Api,
                'provider_id' => $wholesale->id,
                'provider_service_id' => $pid,
                'name' => $name,
                'description' => $description,
                'type' => $type,
                'rate' => Money::round(Money::mul($cost, '1.35'), 2),
                'provider_rate' => $cost,
                'min' => $min,
                'max' => $max,
                'refill' => $refill !== null,
                'refill_days' => $refill,
                'cancel' => $cancel,
                'dripfeed' => $drip && $type->supportsDripfeed(),
                'sort' => $i,
                'status' => ActiveStatus::Active,
            ]);

            // Dịch vụ mới thêm dần → trang Updates có mục "New" rải theo thời gian.
            if (! $addedDaysAgo) {
                $create();

                continue;
            }

            $this->at($this->now->subDays($addedDaysAgo)->setTime(10, 30), function () use ($create) {
                $service = $create()->load('category');
                $this->services[$service->id] = $service;
            });
        }

        // Dịch vụ cũ của Legacy Panel: có đơn lịch sử, sau đó bị xoá mềm.
        Service::query()->create([
            'category_id' => Category::query()->where('name', 'Instagram Followers')->value('id'),
            'fulfillment' => Fulfillment::Api,
            'provider_id' => $legacy->id,
            'provider_service_id' => '881',
            'name' => 'Instagram Followers [Old server]',
            'description' => 'Replaced by the High Quality server.',
            'type' => ServiceType::Default,
            'rate' => '1.60',
            'provider_rate' => '1.10',
            'min' => 100,
            'max' => 20000,
            'refill' => false,
            'cancel' => false,
            'dripfeed' => false,
            'sort' => 9,
            'status' => ActiveStatus::Active,
        ]);

        $this->services = Service::query()->with('category')->get()->keyBy('id')->all();
    }

    // ---------------------------------------------------------------------------------------------
    // Khách hàng
    // ---------------------------------------------------------------------------------------------

    /**
     * [username, tên, email, tiền tệ, múi giờ, kênh nạp, đăng ký (ngày trước), mức hoạt động, hệ số số lượng, cờ]
     */
    private function customerList(): array
    {
        return [
            ['demo', 'Minh Anh Nguyen', 'demo@example.com', 'USD', 'Asia/Ho_Chi_Minh', 'sepay', 112, 7, 11.0, []],
            ['reseller', 'Quoc Bao Tran (BT Media)', 'reseller@example.com', 'USD', 'Asia/Ho_Chi_Minh', 'trc20', 110, 18, 11.0, ['api']],
            ['mike.agency', 'Mike Johnson (Growth Agency)', 'mike@growth-agency.example', 'USD', 'America/New_York', 'bep20', 105, 6, 2.5, ['reseller_level', 'mass']],
            ['hoanglong', 'Hoang Long Le', 'hoanglong@example.net', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 104, 3, 5.0, ['ref:demo']],
            ['thuytrang', 'Thuy Trang Pham', 'thuytrang.pham@example.net', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 101, 2.5, 4.5, ['2fa']],
            ['ducminh.mkt', 'Duc Minh Vo', 'ducminh.mkt@example.org', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 98, 3, 6.0, ['mass']],
            ['lanphuong', 'Lan Phuong Do', 'lanphuong@example.net', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 95, 1.5, 0.8, ['ref:demo']],
            ['jake.miller', 'Jake Miller', 'jake.miller@example.com', 'USD', 'America/Los_Angeles', 'trc20', 94, 2, 1.0, []],
            ['sophie.laurent', 'Sophie Laurent', 'sophie.laurent@example.org', 'EUR', 'Europe/Paris', 'bep20', 92, 1.5, 3.0, ['2fa']],
            ['somchai.k', 'Somchai Kittisak', 'somchai.k@example.net', 'THB', 'Asia/Bangkok', 'trc20', 90, 2, 1.0, []],
            ['khanhvy', 'Khanh Vy Bui', 'khanhvy.bui@example.com', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 88, 1.2, 0.6, []],
            ['lucas.schmidt', 'Lucas Schmidt', 'lucas.schmidt@example.de', 'EUR', 'Europe/Berlin', 'trc20', 86, 1.5, 1.2, []],
            ['tuananh_shop', 'Tuan Anh Dang', 'tuananh.shop@example.com', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 84, 2.5, 1.3, ['ref:hoanglong']],
            ['priya.s', 'Priya Sharma', 'priya.sharma@example.in', 'USD', 'Asia/Kolkata', 'bep20', 80, 1.2, 0.8, []],
            ['ngocmai', 'Ngoc Mai Huynh', 'ngocmai.huynh@example.net', 'USD', 'Asia/Ho_Chi_Minh', 'trc20', 78, 1, 0.7, []],
            ['pimchanok', 'Pimchanok Srisuk', 'pimchanok@example.net', 'THB', 'Asia/Bangkok', 'trc20', 76, 1.2, 0.8, []],
            ['tom.h', 'Tom Harris', 'tom.harris@example.com', 'USD', 'Europe/London', 'trc20', 74, 1.5, 1.0, ['ban:30']],
            ['maria.garcia', 'Maria Garcia', 'maria.garcia@example.es', 'EUR', 'Europe/Madrid', 'bep20', 72, 1.2, 0.9, []],
            ['quanghuy', 'Quang Huy Ngo', 'quanghuy.ngo@example.com', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 70, 1.5, 1.0, ['ref:demo']],
            ['ahmed.k', 'Ahmed Khan', 'ahmed.khan@example.ae', 'USD', 'Asia/Dubai', 'bep20', 68, 1.2, 1.0, ['2fa']],
            ['kenji.t', 'Kenji Tanaka', 'kenji.tanaka@example.jp', 'USD', 'Asia/Tokyo', 'trc20', 66, 0.8, 0.8, []],
            ['thanhtam', 'Thanh Tam Duong', 'thanhtam@example.net', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 64, 1, 0.8, []],
            ['emma.w', 'Emma Wilson', 'emma.wilson@example.co.uk', 'USD', 'Europe/London', 'trc20', 62, 1, 0.8, []],
            ['nattapong', 'Nattapong Wongsa', 'nattapong.w@example.net', 'THB', 'Asia/Bangkok', 'trc20', 60, 0.8, 0.7, []],
            ['daniel.r', 'Daniel Rossi', 'daniel.rossi@example.it', 'EUR', 'Europe/Rome', 'bep20', 57, 0.8, 0.8, []],
            ['baochau', 'Bao Chau Ly', 'baochau.ly@example.com', 'USD', 'Asia/Ho_Chi_Minh', 'sepay', 55, 1, 0.8, []],
            ['olivia.b', 'Olivia Brown', 'olivia.brown@example.com', 'USD', 'Australia/Sydney', 'trc20', 52, 0.8, 0.7, []],
            ['carlos.m', 'Carlos Mendes', 'carlos.mendes@example.com.br', 'USD', 'America/Sao_Paulo', 'bep20', 50, 1, 0.9, []],
            ['spam.bot77', 'Spam Account', 'spam.bot77@example.com', 'USD', 'UTC', 'trc20', 48, 0.6, 0.5, ['ban:44']],
            ['vietdung', 'Viet Dung Truong', 'vietdung.truong@example.net', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 45, 1, 0.9, ['ref:ducminh.mkt']],
            ['anna.k', 'Anna Kowalski', 'anna.kowalski@example.pl', 'EUR', 'Europe/Warsaw', 'trc20', 42, 0.7, 0.7, []],
            ['rizky.p', 'Rizky Pratama', 'rizky.pratama@example.id', 'USD', 'Asia/Jakarta', 'bep20', 40, 0.8, 0.7, []],
            ['hailinh', 'Hai Linh Vu', 'hailinh.vu@example.com', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 36, 0.9, 0.7, []],
            ['liam.oconnor', 'Liam O\'Connor', 'liam.oconnor@example.ie', 'EUR', 'Europe/Dublin', 'trc20', 33, 0.6, 0.6, []],
            ['siti.n', 'Siti Nurhaliza', 'siti.n@example.my', 'USD', 'Asia/Kuala_Lumpur', 'bep20', 30, 0.7, 0.7, []],
            ['chloe.martin', 'Chloe Martin', 'chloe.martin@example.fr', 'EUR', 'Europe/Paris', 'bep20', 26, 0.6, 0.6, []],
            ['noah.lee', 'Noah Lee', 'noah.lee@example.com', 'USD', 'America/Chicago', 'trc20', 22, 0.6, 0.6, []],
            ['isabella.c', 'Isabella Costa', 'isabella.costa@example.pt', 'EUR', 'Europe/Lisbon', 'trc20', 18, 0.5, 0.6, []],
            ['ivan.p', 'Ivan Petrov', 'ivan.petrov@example.com', 'USD', 'Europe/Sofia', 'trc20', 14, 0.6, 0.6, []],
            ['fatima.z', 'Fatima Zahra', 'fatima.zahra@example.ma', 'USD', 'Africa/Casablanca', 'bep20', 10, 0.5, 0.5, []],
            ['minhkhoa', 'Minh Khoa Lam', 'minhkhoa.lam@example.net', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 6, 0.6, 0.6, ['ref:demo']],
            ['peter.clark', 'Peter Clark', 'peter.clark@example.com', 'USD', 'America/Toronto', 'trc20', 3, 0, 1, []],
            ['hannah.s', 'Hannah Scott', 'hannah.scott@example.com', 'USD', 'Europe/London', 'trc20', 0.3, 0, 1, []],
            ['giabao', 'Gia Bao Phan', 'giabao.phan@example.net', 'VND', 'Asia/Ho_Chi_Minh', 'sepay', 0.15, 0, 1, []],
        ];
    }

    private function scheduleCustomers(): void
    {
        foreach ($this->customerList() as [$username, $name, $email, $currency, $tz, $method, $daysAgo, $weight, $scale, $flags]) {
            $registeredAt = $this->now->subMinutes((int) round($daysAgo * 1440))->setTime(mt_rand(8, 22), mt_rand(0, 59));
            $registeredAt = $registeredAt->greaterThan($this->now) ? $this->now->subMinutes(mt_rand(20, 200)) : $registeredAt;

            $profile = compact('username', 'method', 'weight', 'scale', 'flags', 'registeredAt');
            $profile['bannedAt'] = null;

            foreach ($flags as $flag) {
                if (str_starts_with($flag, 'ban:')) {
                    $profile['bannedAt'] = $registeredAt->addDays((int) substr($flag, 4));
                }
            }

            $this->profiles[$username] = $profile;

            $this->at($registeredAt, function () use ($username, $name, $email, $currency, $tz, $flags) {
                $referrer = null;

                foreach ($flags as $flag) {
                    if (str_starts_with($flag, 'ref:')) {
                        $referrer = $this->users[substr($flag, 4)]->id ?? null;
                    }
                }

                $user = $this->makeUser($username, $name, $email, $currency, $tz, referredBy: $referrer);

                if (in_array('2fa', $flags, true)) {
                    $user->forceFill(['has_email_authentication' => true])->save();
                }

                if ($username === 'reseller') {
                    $user->forceFill([
                        'api_key_hash' => User::hashApiKey(self::API_KEY),
                        'api_key_last4' => substr(self::API_KEY, -4),
                        'api_key_created_at' => now(),
                    ])->save();
                }
            });

            if (in_array('reseller_level', $flags, true)) {
                $this->at($registeredAt->addDays(45), fn () => $this->users[$username]
                    ->forceFill(['level_id' => Level::query()->where('name', 'Reseller')->value('id')])->save());
            }

            if ($profile['bannedAt']) {
                $this->at($profile['bannedAt'], fn () => $this->users[$username]->forceFill(['status' => UserStatus::Banned])->save());
            }
        }
    }

    /** Khách chọn để đặt đơn lúc $at: đã đăng ký, chưa bị khoá, theo mức hoạt động. */
    private function pickCustomer(CarbonImmutable $at): ?User
    {
        $weights = [];

        foreach ($this->profiles as $username => $p) {
            if ($p['weight'] <= 0 || ! isset($this->users[$username]) || $p['registeredAt']->addHours(2)->greaterThan($at)) {
                continue;
            }

            if ($p['bannedAt'] && $p['bannedAt']->lessThanOrEqualTo($at)) {
                continue;
            }

            $weights[$username] = $p['weight'];
        }

        $username = $this->weighted($weights);

        return $username ? $this->users[$username] : null;
    }

    // ---------------------------------------------------------------------------------------------
    // Đơn hàng
    // ---------------------------------------------------------------------------------------------

    private function scheduleOrders(): void
    {
        $peaks = [61 => 2.4, 33 => 2.0, 12 => 2.6, 4 => 1.6];

        for ($daysAgo = self::SIMULATED_DAYS; $daysAgo >= 0; $daysAgo--) {
            $day = $this->now->subDays($daysAgo)->startOfDay();
            $growth = 0.65 + 0.7 * (1 - $daysAgo / self::SIMULATED_DAYS);
            $weekday = match ($day->dayOfWeekIso) {
                6, 7 => 1.3,
                1 => 0.85,
                default => 1.0,
            };
            $count = (int) round(7 * $growth * $weekday * ($peaks[$daysAgo] ?? 1) + mt_rand(-2, 2));

            if ($daysAgo === 0) {
                $count = max(8, (int) round($count * max(0.35, $this->now->hour / 24)));
            }

            for ($i = 0; $i < $count; $i++) {
                $at = $daysAgo === 0
                    ? $this->now->subMinutes(mt_rand(3, max(30, $this->now->hour * 60 + $this->now->minute - 5)))
                    : $day->addMinutes($this->businessMinute());

                $this->at($at, fn (CarbonImmutable $at) => $this->placeRandomOrder($at));
            }
        }
    }

    /** Phút trong ngày, dồn vào giờ hành chính / buổi tối. */
    private function businessMinute(): int
    {
        $hour = $this->weighted([0 => 1, 1 => 1, 6 => 2, 8 => 4, 9 => 6, 10 => 7, 11 => 6, 13 => 6, 14 => 7, 15 => 6, 16 => 5, 19 => 6, 20 => 8, 21 => 8, 22 => 5, 23 => 3]);

        return $hour * 60 + mt_rand(0, 59);
    }

    private function placeRandomOrder(CarbonImmutable $at): void
    {
        $user = $this->pickCustomer($at);

        if (! $user) {
            return;
        }

        $profile = $this->profiles[$user->username];
        $flags = $profile['flags'];

        $source = in_array('api', $flags, true)
            ? (mt_rand(1, 10) <= 9 ? OrderSource::Api : OrderSource::Mass)
            : (in_array('mass', $flags, true) && mt_rand(1, 4) === 1 ? OrderSource::Mass : OrderSource::Web);

        $count = $source === OrderSource::Mass ? mt_rand(2, 5) : 1;

        for ($i = 0; $i < $count; $i++) {
            $service = $this->pickService($at, $source !== OrderSource::Web);

            if ($service) {
                $this->placeOrder($user, $service, $source, $profile['scale'], $at);
            }
        }
    }

    private function pickService(CarbonImmutable $at, bool $defaultOnly, ?bool $manual = null): ?Service
    {
        $weights = [];

        foreach ($this->services as $id => $service) {
            if (! $service->isActive() || $service->trashed() || $service->category->status !== ActiveStatus::Active) {
                continue;
            }

            if ($manual !== null && $service->isManual() !== $manual) {
                continue;
            }

            if ($defaultOnly && $service->type !== ServiceType::Default) {
                continue;
            }

            $rate = (float) $service->rate;
            $weights[$id] = match (true) {
                $service->type->isPackage() => 1,
                $service->type !== ServiceType::Default => 2,
                $rate < 0.5 => 9,
                $rate < 2 => 7,
                $rate < 5 => 4,
                default => 2,
            };
        }

        $id = $this->weighted($weights);

        return $id === null ? null : $this->services[$id];
    }

    /** Đơn vài giờ gần đây ở đúng trạng thái đang dở (không phụ thuộc ngẫu nhiên). */
    private function scheduleRecentOrders(): void
    {
        $plan = [
            // [khách, phút trước, trạng thái hiện tại]
            ['demo', 35, 'processing'], ['demo', 140, 'pending'], ['reseller', 12, 'processing'], ['reseller', 95, 'send_failed'],
            ['jake.miller', 50, 'send_failed'], ['somchai.k', 260, 'processing'], ['mike.agency', 20, 'pending'],
            ['thuytrang', 230, 'processing'], ['lucas.schmidt', 160, 'pending'], ['ducminh.mkt', 8, 'processing'],
        ];

        foreach ($plan as [$username, $minutesAgo, $state]) {
            $this->at($this->now->subMinutes($minutesAgo), function (CarbonImmutable $at) use ($username, $state) {
                $user = $this->users[$username];
                $api = $username === 'reseller';
                $service = $this->pickService($at, $api, $state === 'pending');

                if ($service) {
                    $this->placeOrder($user, $service, $api ? OrderSource::Api : OrderSource::Web, $this->profiles[$username]['scale'], $at, $state);
                }
            });
        }
    }

    private function placeOrder(User $user, Service $service, OrderSource $source, float $scale, CarbonImmutable $at, ?string $state = null): ?Order
    {
        $input = $this->orderInput($service, $scale);

        try {
            $quantity = $this->quantityOf($service, $input) * (int) ($input['runs'] ?? 1);
            $charge = $this->pricing->charge($service, $user, $quantity);
            $this->ensureFunds($user, $charge, $at);

            $order = $this->createOrder($user, $service, $input, $source);
        } catch (SmmException) {
            $this->stats['skipped']++;

            return null;
        }

        $this->stats['orders']++;

        if ($source === OrderSource::Api && $at->greaterThan($this->now->subDays(30))) {
            $this->apiLog($user, 'add', ['action' => 'add', 'service' => (string) $service->id, 'link' => $order->link, 'quantity' => (string) $order->quantity]);
        }

        $this->planLifecycle($order, $service, $at, $state);

        return $order;
    }

    /**
     * Tạo đơn + trừ tiền qua OrderService (cùng validate, tính giá, snapshot như khi khách đặt).
     * Không dùng place(): place() gửi provider ngay – ở đây phần "provider nhận đơn" được giả lập trong planLifecycle().
     */
    private function createOrder(User $user, Service $service, array $input, OrderSource $source): Order
    {
        return (fn () => $this->create($user, $service, $input, $source))->call($this->orders);
    }

    /** @param  string|null  $state  ép trạng thái hiện tại: pending | processing | send_failed (đơn gần đây) */
    private function planLifecycle(Order $order, Service $service, CarbonImmutable $at, ?string $state = null): void
    {
        $id = $order->id;
        $total = $order->quantity * ($order->runs ?: 1);
        $isApi = ! $service->isManual();

        if ($isApi) {
            // Provider từ chối (link sai / tài khoản private) → huỷ + hoàn tiền ngay như OrderService.
            if ($state === null && mt_rand(1, 100) <= 3) {
                $order->forceFill(['send_attempts' => 1, 'provider_error' => $this->pick(['Link is invalid', 'Account is private', 'Active order with this link already exists'])])->save();
                $this->orders->applyStatus($order, OrderStatus::Canceled);

                return;
            }

            // Chưa gửi được (provider timeout), đang chờ cron gửi lại → mục "Needs attention".
            if ($state === 'send_failed') {
                $order->forceFill([
                    'send_attempts' => 2,
                    'provider_error' => 'cURL error 28: Operation timed out after 60001 milliseconds',
                    'next_send_at' => $this->now->addMinutes(4),
                ])->save();

                return;
            }

            $order->forceFill([
                'provider_order_id' => (string) ($this->providerOrderId += mt_rand(3, 40)),
                'status' => OrderStatus::Processing,
                'send_attempts' => 1,
                'next_send_at' => null,
            ])->save();
        }

        if ($state !== null) {
            return; // pending (làm tay, chờ admin) / processing (provider đã nhận, chưa chạy)
        }

        $roll = mt_rand(1, 100);
        $platform = $service->category->platform;
        $slow = in_array($platform, [Platform::YouTube, Platform::Spotify], true) || $service->type->isPackage();

        // Huỷ (khách yêu cầu / provider huỷ) – trước khi chạy.
        if ($roll <= 8) {
            $requested = $service->cancel && mt_rand(0, 1) === 1;

            if ($requested) {
                $this->at($at->addMinutes(mt_rand(5, 90)), fn () => Order::query()->whereKey($id)->update(['cancel_requested_at' => now()]));
            }

            $this->at($at->addMinutes(mt_rand(100, 600)), fn () => $this->orders->applyStatus($this->orderRef($id), OrderStatus::Canceled));

            return;
        }

        $startAt = $at->addMinutes($isApi ? mt_rand(10, 360) : mt_rand(15, 300));
        $hours = $slow ? mt_rand(30, 260) : mt_rand(2, 54);
        $endAt = $startAt->addMinutes($hours * 60 + mt_rand(0, 59));
        $startCount = $this->startCount($service);

        $this->at($startAt, fn () => $this->orders->applyStatus($this->orderRef($id), OrderStatus::InProgress, $startCount, $total));

        // Đơn còn chạy tới hiện tại: cập nhật số còn lại giữa chừng (đơn đã xong thì bỏ qua cho nhanh).
        if ($endAt->greaterThan($this->now)) {
            $this->at($startAt->addMinutes((int) ($hours * 30)), fn () => $this->orders->applyStatus($this->orderRef($id), OrderStatus::InProgress, null, (int) round($total * mt_rand(25, 70) / 100)));

            // Khách xin huỷ đơn đang chạy, chờ provider / admin xử lý.
            if ($service->cancel && mt_rand(1, 5) === 1) {
                $this->at($startAt->addMinutes(mt_rand(10, 120)), fn () => Order::query()->whereKey($id)->whereIn('status', OrderStatus::active())->update(['cancel_requested_at' => now()]));
            }
        }

        $partial = $roll <= 18 && ! $service->type->isPackage();

        $this->at($endAt, function () use ($id, $total, $partial, $service, $endAt) {
            $order = Order::query()->findOrFail($id);

            if ($order->cancel_requested_at && $order->status !== OrderStatus::Canceled) {
                $this->orders->applyStatus($order, OrderStatus::Partial, null, max(1, (int) round($total * mt_rand(10, 40) / 100)));
            } elseif ($partial) {
                $this->orders->applyStatus($order, OrderStatus::Partial, null, max(1, (int) round($total * mt_rand(8, 45) / 100)));
            } else {
                $this->orders->applyStatus($order, OrderStatus::Completed, null, 0);
            }

            if ($service->isManual() && mt_rand(1, 6) === 1) {
                $order->update(['admin_note' => $this->pick([
                    'Started manually on the supplier dashboard.',
                    'Delivered in 2 batches because of platform limits.',
                    'Customer asked for slow delivery – done over 2 days.',
                    'Checked start count with screenshot before starting.',
                ])]);
            }

            $this->planAfterCompletion($id, $service, $endAt);
        });
    }

    /** Đơn chỉ có id: applyStatus() tự khoá + đọc lại dòng đơn, khỏi tốn thêm 1 câu SELECT (seed nhanh hơn). */
    private function orderRef(int $id): Order
    {
        return (new Order)->forceFill(['id' => $id]);
    }

    /** Hoàn tiền thủ công (khiếu nại) hoặc yêu cầu refill sau khi đơn xong. */
    private function planAfterCompletion(int $id, Service $service, CarbonImmutable $endAt): void
    {
        if (mt_rand(1, 100) <= 3) {
            $this->at($endAt->addHours(mt_rand(20, 120)), function () use ($id) {
                $order = $this->orders->refundByAdmin(Order::query()->findOrFail($id));
                $order->update(['admin_note' => 'Post was deleted by the platform during delivery – refunded after ticket.']);
            });

            return;
        }

        if (! $service->refill || mt_rand(1, 100) > 14) {
            return;
        }

        $refillAt = $endAt->addHours(mt_rand(30, 24 * min(18, max(2, ($service->refill_days ?? 20) - 2))));

        $this->at($refillAt, function () use ($id, $service, $refillAt) {
            $order = Order::query()->findOrFail($id);

            if (! in_array($order->status, [OrderStatus::Completed, OrderStatus::Partial], true)) {
                return;
            }

            $refill = $order->refills()->create([
                'user_id' => $order->user_id,
                'status' => RefillStatus::Pending,
                'provider_refill_id' => $service->isManual() ? null : (string) mt_rand(90000, 99999),
            ]);

            if ($order->source === OrderSource::Api && $refillAt->greaterThan($this->now->subDays(30))) {
                $this->apiLog($order->user, 'refill', ['action' => 'refill', 'order' => (string) $order->id]);
            }

            $this->at($refillAt->addMinutes(mt_rand(60, 600)), fn () => $refill->update(['status' => RefillStatus::Processing]));

            $outcome = $this->weighted(['completed' => 75, 'rejected' => 15, 'error' => 10]);
            $this->at($refillAt->addHours(mt_rand(12, 48)), fn () => $refill->update(match ($outcome) {
                'completed' => ['status' => RefillStatus::Completed],
                'rejected' => ['status' => RefillStatus::Rejected, 'provider_response' => 'Refill rejected: the count has not dropped below the start count + quantity.'],
                default => ['status' => RefillStatus::Error, 'provider_response' => 'Provider error: refill is not available for this order.'],
            }));
        });
    }

    private function startCount(Service $service): int
    {
        return $service->type->isPackage() ? 0 : mt_rand(0, 9) * (10 ** mt_rand(2, 4)) + mt_rand(0, 999);
    }

    /** @return array<string, mixed> */
    private function orderInput(Service $service, float $scale): array
    {
        $platform = $service->category->platform;
        $input = ['link' => $this->link($platform, $service->type)];

        $lines = fn (array $pool, int $min) => implode("\n", array_slice($this->shuffled($pool), 0, min(count($pool), mt_rand($min, $min + 6))));

        $comments = ['Love this! 🔥', 'Amazing content 👏', 'So beautiful 😍', 'Great job, keep it up!', 'Where can I buy this?', 'This is so helpful, thanks!', 'Wow 😮', 'Best post today', 'Need more of this', 'Absolutely stunning', 'Perfect 👌', 'Can\'t stop watching', 'Saved for later!', 'Big fan ❤️', 'Incredible quality'];
        $usernames = ['travel.with.mia', 'daily.coffee.vn', 'fitlife_jen', 'saigon.foodie', 'the.art.corner', 'urban.snaps', 'hanoi.streetstyle', 'minimal.home', 'petlovers.club', 'techreview.daily', 'bookworm.anna', 'surf.bali.life', 'vegan.kitchen', 'run.with.tom', 'studio.lumi'];

        if ($service->type->isPackage()) {
            // số lượng = 1
        } elseif ($field = $service->type->quantityFromLinesOf()) {
            $input[$field] = $lines($field === 'usernames' ? $usernames : $comments, max(1, $service->min));
        } else {
            $input['quantity'] = $this->quantity($service, $scale);
        }

        $input += match ($service->type) {
            ServiceType::CustomCommentsPackage => ['comments' => $lines($comments, 10)],
            ServiceType::MentionsWithHashtags => ['usernames' => $lines($usernames, 5), 'hashtags' => "#summer\n#travelgram\n#vietnam"],
            ServiceType::MentionsUserFollowers, ServiceType::CommentLikes => ['username' => $this->pick($usernames)],
            ServiceType::CommentReplies => ['username' => $this->pick($usernames)],
            ServiceType::Poll => ['answer_number' => mt_rand(1, 4)],
            ServiceType::WebTraffic => [
                'country' => $this->pick(['United States', 'Vietnam', 'Germany', 'Worldwide']),
                'device' => $this->pick(['Desktop', 'Mobile', 'Mixed']),
                'type_of_traffic' => $this->pick(['Google Keyword', 'Social']),
                'google_keyword' => $this->pick(['best coffee beans', 'handmade leather wallet', 'saigon street food tour']),
            ],
            default => [],
        };

        if ($service->dripfeed && isset($input['quantity']) && mt_rand(1, 12) === 1) {
            $input['runs'] = mt_rand(3, 8);
            $input['interval'] = $this->pick([30, 60, 120, 360]);
            $input['quantity'] = max($service->min, (int) ($input['quantity'] / $input['runs']));
        }

        return $input;
    }

    private function quantityOf(Service $service, array $input): int
    {
        if ($service->type->isPackage()) {
            return 1;
        }

        if ($field = $service->type->quantityFromLinesOf()) {
            return count(array_filter(explode("\n", $input[$field])));
        }

        return (int) $input['quantity'];
    }

    private function quantity(Service $service, float $scale): int
    {
        $rate = (float) $service->rate;
        [$lo, $hi] = match (true) {
            $rate < 0.1 => [2000, 50000],
            $rate < 1 => [200, 5000],
            $rate < 5 => [100, 2500],
            default => [50, 600],
        };

        $value = exp(log($lo) + (log($hi) - log($lo)) * mt_rand(0, 1000) / 1000) * $scale;
        $step = $value >= 10000 ? 1000 : ($value >= 1000 ? 100 : 50);
        $value = (int) (round($value / $step) * $step);

        return max($service->min, min($service->max, $value));
    }

    private function link(Platform $platform, ServiceType $type): string
    {
        $handle = $this->pick(['lunacafe.sg', 'mia.travels', 'hoangstudio', 'fitwithjen', 'nail.house.hcm', 'dailyvibes', 'kenji.eats', 'beatsbylucas', 'saigonsneaker', 'the.plant.mom', 'urbanfit.vn', 'bakery.bloom']);
        $code = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789'), 0, 11);

        return match ($platform) {
            Platform::Instagram => mt_rand(0, 2) ? "https://www.instagram.com/p/{$code}/" : "https://www.instagram.com/{$handle}/",
            Platform::TikTok => "https://www.tiktok.com/@{$handle}/video/7".mt_rand(100000000, 999999999).mt_rand(100000000, 999999999),
            Platform::YouTube => "https://www.youtube.com/watch?v={$code}",
            Platform::Facebook => "https://www.facebook.com/{$handle}/posts/".mt_rand(100000000, 999999999),
            Platform::Telegram => "https://t.me/{$handle}/".mt_rand(10, 999),
            Platform::Twitter => "https://x.com/{$handle}/status/18".mt_rand(100000000, 999999999).mt_rand(1000000, 9999999),
            Platform::Spotify => "https://open.spotify.com/track/{$code}".substr($code, 0, 11),
            Platform::Other => $type === ServiceType::WebTraffic ? "https://{$handle}.example/landing" : "https://{$handle}.example/",
        };
    }

    // ---------------------------------------------------------------------------------------------
    // Nạp tiền
    // ---------------------------------------------------------------------------------------------

    /** Khách nạp trước khi đặt đơn nếu không đủ số dư (nạp dư ra như người dùng thật). */
    private function ensureFunds(User $user, string $charge, CarbonImmutable $at): void
    {
        // $user được BalanceService cập nhật tại chỗ khi đặt đơn; hoàn tiền (theo user_id) chỉ làm số dư thật
        // cao hơn → không refresh mỗi đơn (seed nhanh hơn), cùng lắm khách nạp thêm.
        if (Money::cmp($user->balance, $charge) >= 0) {
            return;
        }

        $method = $this->methods[$this->profiles[$user->username]['method']];
        $needed = (float) Money::sub($charge, $user->balance) * mt_rand(20, 50) / 10;
        $amount = collect([5, 10, 20, 30, 50, 100, 150, 200, 300, 500, 1000, 2000])->first(fn ($a) => $a >= $needed) ?? 3000;
        $amount = max($amount, (int) ceil((float) $method->min_amount));

        // SePay: thỉnh thoảng khách quên ghi mã nạp → tự nhận bằng mã giao dịch ngân hàng.
        $flow = $method->gateway->value === 'sepay' && mt_rand(1, 8) === 1 ? 'claim' : 'auto';
        $this->deposit($user, $method, (string) $amount, $at->subMinutes($flow === 'claim' ? mt_rand(55, 90) : mt_rand(6, 25)), $flow);
        $this->travel($at);
        $user->refresh();
    }

    /**
     * 1 phiếu nạp qua DepositService. $flow: auto (khớp tự động), claim (SePay quên ghi mã → khách tự nhận),
     * admin (crypto bị sàn trừ phí → admin gán tay), expired, canceled, pending.
     */
    private function deposit(User $user, PaymentMethod $method, string $usd, CarbonImmutable $at, string $flow = 'auto'): ?Deposit
    {
        $this->travel($at);
        $deposit = $this->deposits->create($user, $method, $usd);
        $sepay = $method->gateway->value === 'sepay';

        if (in_array($flow, ['expired', 'pending'], true)) {
            return $deposit;
        }

        if ($flow === 'canceled') {
            $this->travel($at->addHours(mt_rand(2, 30)));
            $deposit->update(['status' => DepositStatus::Canceled]);

            return $deposit;
        }

        $paidAt = $at->addMinutes(mt_rand(2, 9));
        $this->travel($paidAt);

        $amount = $flow === 'admin' && ! $sepay
            ? Money::sub(Money::round($usd), '1') // sàn rút trừ 1 USDT phí → không khớp số tiền
            : $deposit->paid_amount;

        $transfer = $this->deposits->recordTransfer($method, $this->transferData($method, $amount, $paidAt, $flow === 'claim' ? null : $deposit->code));

        if ($flow === 'auto') {
            $deposit = $this->deposits->autoMatch($transfer) ?? $deposit;
        } elseif ($flow === 'claim') {
            $this->travel($paidAt->addMinutes(mt_rand(10, 40)));
            $deposit = $this->deposits->complete($deposit, $transfer, DepositMatchedBy::UserClaim);
        } else {
            $this->travel($paidAt->addMinutes(mt_rand(90, 600)));
            $deposit = $this->deposits->assignToUser($transfer, $user);
        }

        $this->referralCommission($user, $deposit);

        return $deposit;
    }

    /** Hoa hồng giới thiệu 5% trên mỗi lần nạp của khách được giới thiệu. */
    private function referralCommission(User $user, Deposit $deposit): void
    {
        if (! $user->referred_by || $deposit->status !== DepositStatus::Completed) {
            return;
        }

        $referrer = collect($this->users)->first(fn (User $u) => $u->id === $user->referred_by);

        $this->balance->change(
            $referrer,
            Money::round(Money::mul($deposit->amount, '0.05')),
            TransactionType::Referral,
            $deposit,
            "Referral commission 5% – deposit by {$user->username}",
        );
    }

    /** @return array<string, mixed> */
    private function transferData(PaymentMethod $method, string $amount, CarbonImmutable $at, ?string $code): array
    {
        if ($method->gateway->value === 'sepay') {
            $reference = 'FT'.$at->format('ymd').strtoupper(substr(md5((string) mt_rand()), 0, 6));
            $content = $code
                ? $this->pick(["{$code} chuyen tien", "MBVCB.{$this->sepayTxId}.{$code}.CT tu 1903", "{$code}", "IBFT {$code} nap tien"])
                : $this->pick(['chuyen tien nap smm', 'nap tien tai khoan', 'CT DEN 0000123456789 thanh toan']);

            return [
                'external_id' => (string) (++$this->sepayTxId),
                'amount' => Money::round($amount, 0),
                'content' => $content,
                'sender' => $this->pick(['NGUYEN MINH ANH', 'LE HOANG LONG', 'PHAM THUY TRANG', 'VO DUC MINH', 'DANG TUAN ANH', null]),
                'transferred_at' => $at,
                'raw' => ['gateway' => 'MBBank', 'referenceCode' => $reference, 'transferType' => 'in', 'accountNumber' => '0000123456789'],
            ];
        }

        $evm = $method->configValue('network') === 'bsc';

        return [
            'external_id' => ($evm ? '0x' : '').bin2hex(random_bytes(32)),
            'amount' => $amount,
            'sender' => $evm ? '0x'.bin2hex(random_bytes(20)) : 'T'.substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz123456789', 2)), 0, 33),
            'block_number' => $evm ? mt_rand(62000000, 62817450) : mt_rand(75000000, 76500000),
            'confirmations' => mt_rand(20, 400),
            'transferred_at' => $at,
            'raw' => ['token' => 'USDT', 'network' => $evm ? 'bsc' : 'tron'],
        ];
    }

    /** Phiếu nạp không gắn với đơn: hết hạn, bị huỷ, admin gán tay, đang chờ thanh toán. */
    private function scheduleStandaloneDeposits(): void
    {
        $plan = [
            // [username, kênh|null (= kênh quen), USD, ngày trước, flow]
            ['demo', 'trc20', '50', 64, 'expired'], ['demo', null, '20', 37, 'canceled'], ['demo', 'bep20', '100', 21, 'auto'],
            ['demo', null, '30', 0.04, 'pending'], ['demo', 'trc20', '200', 9, 'admin'],
            ['reseller', null, '500', 47, 'admin'], ['reseller', 'bep20', '1000', 26, 'auto'], ['reseller', null, '300', 2, 'expired'],
            ['mike.agency', null, '300', 70, 'expired'], ['mike.agency', 'trc20', '500', 15, 'admin'],
            ['hoanglong', null, '50', 58, 'expired'], ['thuytrang', null, '20', 44, 'canceled'], ['ducminh.mkt', null, '100', 29, 'expired'],
            ['jake.miller', null, '30', 33, 'expired'], ['sophie.laurent', null, '50', 18, 'canceled'], ['somchai.k', null, '20', 11, 'expired'],
            ['lucas.schmidt', null, '100', 6, 'admin'], ['priya.s', null, '25', 4, 'expired'], ['maria.garcia', null, '40', 3, 'canceled'],
            ['quanghuy', null, '20', 1.5, 'expired'], ['emma.w', null, '15', 0.2, 'pending'], ['hailinh', null, '10', 0.08, 'pending'],
            ['peter.clark', null, '20', 2.5, 'expired'], ['hannah.s', 'trc20', '25', 0.1, 'pending'], ['giabao', null, '10', 0.05, 'pending'],
            ['kenji.t', null, '50', 52, 'auto'], ['olivia.b', null, '20', 20, 'auto'], ['vietdung', null, '30', 8, 'claim'],
        ];

        foreach ($plan as [$username, $methodKey, $usd, $daysAgo, $flow]) {
            $at = $this->now->subMinutes((int) round($daysAgo * 1440));

            $this->at($at, function (CarbonImmutable $at) use ($username, $methodKey, $usd, $flow) {
                $user = $this->users[$username] ?? null;

                if ($user) {
                    $this->deposit($user, $this->methods[$methodKey ?? $this->profiles[$username]['method']], $usd, $at, $flow);
                }
            });
        }
    }

    /** Giao dịch tiền vào chưa khớp phiếu (để admin xử lý), 1 giao dịch crypto khách đang xin nhận, vài giao dịch bỏ qua. */
    private function scheduleUnmatchedTransfers(): void
    {
        $plan = [
            ['sepay', '260000', 0.3, null, 'unmatched'],
            ['sepay', '1300000', 2.2, null, 'unmatched'],
            ['trc20', '49.000000', 0.9, 'jake.miller', 'claimed'],
            ['bep20', '18.500000', 5.5, null, 'unmatched'],
            ['sepay', '10000', 40, null, 'ignored'],
            ['trc20', '1.000000', 63, null, 'ignored'],
        ];

        foreach ($plan as [$methodKey, $amount, $daysAgo, $claimant, $state]) {
            $at = $this->now->subMinutes((int) round($daysAgo * 1440));

            $this->at($at, function (CarbonImmutable $at) use ($methodKey, $amount, $claimant, $state) {
                $transfer = $this->deposits->recordTransfer($this->methods[$methodKey], $this->transferData($this->methods[$methodKey], $amount, $at, null));

                match ($state) {
                    'claimed' => $transfer->forceFill(['claimed_by' => $this->users[$claimant]->id, 'claimed_at' => $at->addMinutes(35)])->save(),
                    'ignored' => $transfer->update(['status' => TransferStatus::Ignored]),
                    default => null,
                };
            });
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Danh mục thay đổi theo thời gian (trang Updates), điều chỉnh số dư của admin
    // ---------------------------------------------------------------------------------------------

    private function scheduleCatalogChanges(): void
    {
        $byName = fn (string $name) => collect($this->services)->first(fn (Service $s) => $s->name === $name);

        $priceChanges = [
            ['Instagram Likes [Nhanh]', '0.22', 84], ['TikTok Views', '0.012', 77], ['YouTube Subscribers [30 Days Refill]', '15.50', 69],
            ['Instagram Followers [High Quality] [30 Days Refill]', '2.20', 55], ['Telegram Channel Members [15 Days Refill]', '1.25', 48],
            ['Twitter Followers', '3.20', 39], ['TikTok Followers [30 Days Refill]', '1.95', 31], ['Spotify Plays', '0.85', 24],
            ['Facebook Page Likes + Followers', '2.30', 17], ['Instagram Reels Views', '0.025', 12], ['YouTube Views [With Watch Time]', '1.65', 7],
            ['TikTok Likes', '0.32', 3], ['Instagram Custom Comments', '11.00', 1],
        ];

        foreach ($priceChanges as [$name, $rate, $daysAgo]) {
            $this->at($this->now->subDays($daysAgo)->setTime(mt_rand(9, 17), mt_rand(0, 59)), function () use ($byName, $name, $rate) {
                $byName($name)?->update(['rate' => $rate, 'provider_rate' => Money::div($rate, '1.35')]);
            });
        }

        $this->at($this->now->subDays(52)->setTime(10, 5), fn () => $byName('TikTok Shares')?->update(['status' => ActiveStatus::Disabled]));
        $this->at($this->now->subDays(41)->setTime(15, 20), fn () => $byName('TikTok Shares')?->update(['status' => ActiveStatus::Active]));
        $this->at($this->now->subDays(19)->setTime(11, 0), fn () => $byName('Telegram Poll Votes')?->update(['status' => ActiveStatus::Disabled]));
        $this->at($this->now->subDays(60)->setTime(9, 30), fn () => $byName('Instagram Followers [Old server]')?->delete());
    }

    private function scheduleAdminAdjustments(): void
    {
        $plan = [
            ['demo', '5', 'Compensation for the slow YouTube order – sorry for the delay!', 46],
            ['reseller', '25', 'Volume bonus – thank you for 500+ orders this month', 28],
            ['hoanglong', '2', 'Welcome credit', 103],
            ['jake.miller', '-10', 'Correction: deposit credited twice (bank duplicate)', 61],
            ['mike.agency', '50', 'Agency credit line (paid by invoice INV-2026-014)', 38],
            ['somchai.k', '-3.5', 'Chargeback fee', 22],
            ['demo', '-2', 'Correction: refund applied twice on order', 14],
        ];

        // Đơn của khách demo được admin hoàn tiền toàn bộ sau khiếu nại (trạng thái Refunded).
        $this->at($this->now->subDays(15)->setTime(16, 10), function () {
            $order = $this->users['demo']->orders()->where('status', OrderStatus::Completed)->latest('id')->first();

            if ($order) {
                $this->orders->refundByAdmin($order)->update(['admin_note' => 'Customer reported the video was removed by YouTube – full refund approved.']);
            }
        });

        foreach ($plan as [$username, $amount, $note, $daysAgo]) {
            $this->at($this->now->subDays($daysAgo)->setTime(mt_rand(9, 18), mt_rand(0, 59)), function () use ($username, $amount, $note) {
                $user = $this->users[$username]->refresh();

                if (Money::cmp(Money::add($user->balance, $amount), 0) < 0) {
                    return;
                }

                $this->balance->change($user, Money::round($amount), Money::isPositive($amount) ? TransactionType::AdminAdd : TransactionType::AdminDeduct, description: $note, createdBy: $this->admin);
            });
        }
    }

    /** Refill đang chờ / đang chạy ở thời điểm hiện tại (refill ngẫu nhiên phần lớn đã xong). */
    private function scheduleRefillRequests(): void
    {
        $plan = [
            // [khách, giờ trước, trạng thái hiện tại]
            ['demo', 2, RefillStatus::Pending],
            ['demo', 20, RefillStatus::Processing],
            ['reseller', 58, RefillStatus::Processing],
            ['reseller', 57, RefillStatus::Rejected],
            ['reseller', 5, RefillStatus::Pending],
            ['mike.agency', 7, RefillStatus::Pending],
            ['hoanglong', 30, RefillStatus::Error],
        ];

        foreach ($plan as [$username, $hoursAgo, $status]) {
            $this->at($this->now->subMinutes($hoursAgo * 60 + mt_rand(0, 40)), function () use ($username, $status) {
                $order = $this->users[$username]->orders()
                    ->whereIn('status', [OrderStatus::Completed, OrderStatus::Partial])
                    ->where('completed_at', '>=', now()->subDays(25))
                    ->whereHas('service', fn ($q) => $q->where('refill', true))
                    ->whereDoesntHave('refills', fn ($q) => $q->whereIn('status', [RefillStatus::Pending, RefillStatus::Processing]))
                    ->latest('id')
                    ->first();

                $order?->refills()->create([
                    'user_id' => $order->user_id,
                    'status' => $status,
                    'provider_refill_id' => $order->provider_id ? (string) mt_rand(90000, 99999) : null,
                    'provider_response' => match ($status) {
                        RefillStatus::Rejected => 'Refill rejected: the count has not dropped below the start count + quantity.',
                        RefillStatus::Error => 'Provider error: refill is not available for this order.',
                        default => null,
                    },
                ]);
            });
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Ticket
    // ---------------------------------------------------------------------------------------------

    /**
     * Kịch bản ticket: [khách, ngày trước, loại, cần đơn (trạng thái), tiêu đề, hội thoại [người nói, sau X phút, nội dung], kết thúc].
     * Người nói: c = khách, s = nhân viên Support, a = admin. Kết thúc 'close' = đóng ticket.
     */
    private function ticketScripts(): array
    {
        return [
            ['demo', 52, TicketCategory::Refill, 'completed', 'Followers dropped after 2 weeks', [
                ['c', 0, 'Hi, my order {orders} dropped about 300 followers since last week. It has a 30-day refill guarantee, can you refill it?'],
                ['s', 95, 'Hello! I have checked the order and requested a refill from our supplier. It usually completes within 24–48 hours.'],
                ['c', 1500, 'Refill done, everything is back. Thanks a lot!'],
                ['s', 1560, 'Great to hear that! Closing this ticket now. Have a nice day 🙌'],
            ], 'close'],
            ['demo', 33, TicketCategory::Payment, null, 'Bank transfer not credited yet', [
                ['c', 0, 'I transferred 520,000 VND about 30 minutes ago but my balance is still the same. I forgot to write the deposit code in the description. Screenshot attached.', 'receipt'],
                ['s', 42, 'Thanks for the screenshot. I found your transfer and credited it to your account manually. Next time please include the NAP code so it is added automatically.'],
            ], 'close'],
            ['demo', 6, TicketCategory::SpeedUp, 'in_progress', 'YouTube views are very slow', [
                ['c', 0, 'Order {orders} has been running for 2 days and only delivered half. Can you speed it up?'],
                ['s', 70, 'Hi! YouTube views with watch time are delivered slowly to stay safe (about 5K/day). I asked the supplier to increase the speed a little.'],
            ], null],
            ['demo', 1.2, TicketCategory::Other, null, 'Discount for bigger orders?', [
                ['c', 0, 'Do you have any discount if I order more than $500 per month?'],
                ['a', 180, 'Yes – discounts are automatic by level: Silver 3%, Gold 5%, Diamond 8% (based on total spent). You are Gold now, only a little more to reach Diamond!'],
                ['c', 260, 'Nice. Is there also a special price for agencies? I manage 10+ client accounts.'],
            ], null],
            ['demo', 0.06, TicketCategory::Cancel, 'active', 'Please cancel – wrong link', [
                ['c', 0, 'I pasted the wrong link in order {orders}. Please cancel it and refund if possible.'],
            ], null],

            ['reseller', 40, TicketCategory::Other, null, 'API: status for 100 orders at once', [
                ['c', 0, 'Can I check the status of more than 100 orders in one API call?'],
                ['s', 30, 'Hello, the limit is 100 IDs per request (orders=1,2,3…). For more, please split into several calls.'],
                ['c', 55, 'Understood, thanks.'],
            ], 'close'],
            ['reseller', 2.4, TicketCategory::Refill, 'completed', 'Refill for 3 orders', [
                ['c', 0, 'Please refill these orders: {orders}. My customers report drops.'],
                ['s', 50, 'Refills requested for all orders. You can follow them with the refill_status API action.'],
                ['c', 140, 'One of them shows "Rejected", can you check why?'],
            ], null],
            ['mike.agency', 25, TicketCategory::Payment, null, 'USDT sent without the decimals', [
                ['c', 0, 'I sent 500 USDT from Binance but the exchange charged a fee so the amount was different. Can you add it?'],
                ['a', 120, 'Found it – 499 USDT received. I assigned it to your account (including the 5% crypto bonus).'],
            ], 'close'],
            ['hoanglong', 70, TicketCategory::Cancel, 'any', 'Cancel order', [
                ['c', 0, 'Cancel order {orders} please, the post was deleted.'],
                ['s', 25, 'The order was canceled and the remaining amount refunded to your balance.'],
            ], 'close'],
            ['thuytrang', 12, TicketCategory::SpeedUp, 'any', 'TikTok followers not started', [
                ['c', 0, 'My order {orders} has not started after 3 hours.'],
                ['s', 40, 'It is started now, please check again. Sorry for the delay!'],
                ['c', 300, 'Yes it is running. Thanks!'],
            ], 'close'],
            ['ducminh.mkt', 0.5, TicketCategory::Payment, null, 'Deposit pending', [
                ['c', 0, 'I paid with VietQR but the deposit still shows pending.'],
            ], null],
            ['jake.miller', 0.8, TicketCategory::Payment, null, 'Claim USDT transfer', [
                ['c', 0, 'I sent 49 USDT (TRC20) but forgot the exact amount with decimals. I submitted a claim with the tx hash.'],
                ['s', 60, 'Thanks, we see your claim. We will verify the sender address and approve it shortly.'],
                ['c', 120, 'Here is my Binance withdrawal history screenshot as proof.', 'receipt'],
            ], null],
            ['sophie.laurent', 15, TicketCategory::Other, null, 'Change display currency', [
                ['c', 0, 'How can I see prices in EUR?'],
                ['s', 20, 'Go to Account → Display currency and choose EUR. Payments are still in USD.'],
            ], null],
            ['tuananh_shop', 20, TicketCategory::Refill, 'completed', 'Facebook likes dropped', [
                ['c', 0, 'Likes dropped on {orders}.'],
                ['s', 45, 'Refill requested. Please allow 24 hours.'],
            ], 'close'],
            ['priya.s', 3, TicketCategory::Other, null, 'Invoice for my deposits', [
                ['c', 0, 'Can I get an invoice for my deposits last month?'],
                ['s', 90, 'Sure – please send your company name and address and we will email the invoice.'],
                ['c', 400, 'Company: Sharma Digital, Bengaluru, India. Thank you.'],
            ], null],
            ['somchai.k', 0.15, TicketCategory::SpeedUp, 'active', 'Order still processing', [
                ['c', 0, 'Order {orders} is still processing, when will it start?'],
            ], null],
            ['maria.garcia', 26, TicketCategory::Cancel, 'any', 'Wrong service selected', [
                ['c', 0, 'I chose the wrong service for {orders}, can you cancel it?'],
                ['s', 35, 'Sorry, this order was already completed so it cannot be canceled.'],
                ['c', 80, 'OK, no problem.'],
            ], 'close'],
            ['tom.h', 31, TicketCategory::Other, null, 'Why is my account suspended?', [
                ['c', 0, 'I cannot log in, it says my account is suspended.'],
                ['a', 200, 'Your account was suspended because of a PayPal chargeback on a reseller site linked to your orders. Please contact us by email.'],
            ], 'close'],
            ['quanghuy', 8, TicketCategory::Refill, 'completed', 'Refill please', [
                ['c', 0, 'Refill {orders}, thanks.'],
                ['s', 60, 'Done, refill requested.'],
                ['c', 2000, 'Still not refilled after 1 day.'],
            ], null],
            ['lanphuong', 0.02, TicketCategory::Other, null, 'Do you have Threads followers?', [
                ['c', 0, 'Do you sell Threads followers? I can not find them in the services list.'],
            ], null],
        ];
    }

    private function scheduleTickets(): void
    {
        foreach ($this->ticketScripts() as [$username, $daysAgo, $category, $orderNeed, $subject, $messages, $end]) {
            $openAt = $this->now->subMinutes((int) round($daysAgo * 1440) + mt_rand(0, 20));

            $this->at($openAt, function (CarbonImmutable $at) use ($username, $category, $orderNeed, $subject, $messages, $end) {
                $user = $this->users[$username] ?? null;

                if (! $user) {
                    return;
                }

                $orderIds = $orderNeed ? $this->ticketOrders($user, $orderNeed, $category === TicketCategory::Refill && $username === 'reseller' ? 3 : 1) : null;

                if ($orderNeed && ! $orderIds) {
                    return;
                }

                $replace = fn (string $text) => str_replace('{orders}', $orderIds ? '#'.str_replace(',', ', #', $orderIds) : '', $text);
                [, , $first] = $messages[0];

                $ticket = $this->tickets->open($user, $subject, $category, $orderIds, $replace($first), $this->attachments($messages[0][3] ?? null));

                foreach (array_slice($messages, 1) as $i => [$who, $after, $text]) {
                    $last = $i === count($messages) - 2;

                    $this->at($at->addMinutes($after), function () use ($ticket, $who, $text, $replace, $user, $messages, $i, $last, $end) {
                        $author = match ($who) {
                            'c' => $user,
                            'a' => $this->admin,
                            default => $this->staff[0],
                        };
                        $this->tickets->reply($ticket->refresh(), $author, $replace($text), $who !== 'c', $this->attachments($messages[$i + 1][3] ?? null));

                        if ($last && $end === 'close') {
                            $this->travel(CarbonImmutable::now()->addMinutes(mt_rand(5, 60)));
                            $this->tickets->close($ticket->refresh());
                        }
                    });
                }
            });
        }
    }

    /** Đơn của khách phù hợp với kịch bản ticket (tại thời điểm mở ticket). */
    private function ticketOrders(User $user, string $need, int $count): ?string
    {
        $query = $user->orders()->latest('id');

        match ($need) {
            'completed' => $query->where('status', OrderStatus::Completed)->whereHas('service', fn ($q) => $q->where('refill', true)),
            'in_progress' => $query->whereIn('status', [OrderStatus::InProgress, OrderStatus::Processing]),
            'active' => $query->whereIn('status', OrderStatus::active()),
            default => null,
        };

        $ids = $query->limit($count)->pluck('id')->all();

        return $ids ? implode(',', $ids) : null;
    }

    /** @return list<UploadedFile> */
    private function attachments(?string $kind): array
    {
        if ($kind !== 'receipt') {
            return [];
        }

        $path = tempnam(sys_get_temp_dir(), 'demo').'.png';
        file_put_contents($path, $this->receiptPng());

        return [new UploadedFile($path, 'screenshot.png', 'image/png', null, true)];
    }

    /** Ảnh PNG đơn giản giả biên lai chuyển khoản (không cần GD / ảnh ngoài). */
    private function receiptPng(): string
    {
        [$w, $h] = [360, 480];
        $rows = '';

        for ($y = 0; $y < $h; $y++) {
            $row = "\0";

            for ($x = 0; $x < $w; $x++) {
                $rgb = match (true) {
                    $y < 70 => [22, 163, 74],
                    $y >= 110 && $y < 150 && $x >= 40 && $x < 320 => [17, 24, 39],
                    $y >= 175 && $y < 180 && $x >= 40 && $x < 320 => [229, 231, 235],
                    ($y - 205) % 45 < 12 && $y >= 205 && $y < 430 && $x >= 40 && $x < 40 + 120 + ($y % 3) * 40 => [156, 163, 175],
                    ($y - 205) % 45 < 12 && $y >= 205 && $y < 430 && $x >= 220 && $x < 320 => [55, 65, 81],
                    default => [255, 255, 255],
                };
                $row .= chr($rgb[0]).chr($rgb[1]).chr($rgb[2]);
            }

            $rows .= $row;
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($rows, 9))
            .$chunk('IEND', '');
    }

    // ---------------------------------------------------------------------------------------------
    // API log, hoàn tất
    // ---------------------------------------------------------------------------------------------

    private function apiLog(?User $user, string $action, array $request, ?string $error = null): void
    {
        ApiLog::query()->create([
            'user_id' => $user?->id,
            'action' => $action,
            'ip' => $user ? ($user->username === 'reseller' ? '203.0.113.45' : '198.51.100.23') : '192.0.2.'.mt_rand(10, 250),
            'request' => $request,
            'response_status' => 200,
            'error' => $error,
            'created_at' => now(),
        ]);
    }

    /** Lỗi API thường gặp của reseller (30 ngày gần nhất) + key sai từ bot dò key. */
    private function scheduleApiErrors(): void
    {
        $errors = [
            ['add', ['service' => '999'], 'Invalid service ID'],
            ['add', ['service' => '7', 'quantity' => '10'], 'Quantity less than minimal 100'],
            ['add', ['service' => '2', 'quantity' => '50000'], 'Not enough funds on balance'],
            ['status', ['order' => '1'], 'Incorrect order ID'],
            ['refill', ['order' => '15'], 'Refill is not available for this service'],
            ['cancel', ['order' => '42'], 'Cancel is not available for this service'],
        ];

        for ($i = 0; $i < 26; $i++) {
            $at = $this->now->subMinutes(mt_rand(10, 29 * 1440));
            [$action, $params, $error] = $errors[array_rand($errors)];
            $invalidKey = mt_rand(1, 4) === 1;

            $this->at($at, fn () => $this->apiLog(
                $invalidKey ? null : $this->users['reseller'],
                $invalidKey ? $this->pick(['balance', 'services', 'add']) : $action,
                ['action' => $action] + $params,
                $invalidKey ? 'Invalid API key' : $error,
            ));
        }
    }

    private function finish(): void
    {
        // Phiếu quá hạn (bản demo không chạy cron deposits:expire).
        $this->deposits->expireOverdue();

        foreach ($this->users as $user) {
            $lastOrder = $user->orders()->max('created_at');
            $lastLogin = $lastOrder ? CarbonImmutable::parse($lastOrder)->subMinutes(mt_rand(1, 30)) : $user->created_at;

            $user->forceFill([
                'last_login_at' => $user->isStaff() ? $this->now->subMinutes(mt_rand(5, 300)) : $lastLogin,
                'last_login_ip' => '203.0.113.'.mt_rand(2, 250),
            ])->save();
        }

        $drift = DB::table('users')
            ->leftJoinSub(DB::table('transactions')->selectRaw('user_id, SUM(amount) AS total')->groupBy('user_id'), 't', 't.user_id', '=', 'users.id')
            ->whereRaw('ABS(users.balance - COALESCE(t.total, 0)) > 0.000001')
            ->count();

        if ($drift > 0) {
            throw new \RuntimeException("Demo data: {$drift} users have a balance that does not match the ledger.");
        }
    }

    // ---------------------------------------------------------------------------------------------
    // Ngẫu nhiên (seed cố định → dữ liệu giống nhau mỗi lần reset, chỉ ngày giờ dịch theo hôm nay)
    // ---------------------------------------------------------------------------------------------

    /** @param  array<int|string, int|float>  $weights */
    private function weighted(array $weights): int|string|null
    {
        $total = array_sum($weights);

        if ($total <= 0) {
            return null;
        }

        $roll = mt_rand(0, 1_000_000) / 1_000_000 * $total;

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    private function pick(array $items): mixed
    {
        return $items[mt_rand(0, count($items) - 1)];
    }

    private function shuffled(array $items): array
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }

        return $items;
    }
}
