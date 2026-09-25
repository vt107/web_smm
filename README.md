# SMM Panel

Panel bán lại dịch vụ SMM (Laravel 12 + MySQL 8), logic tham khảo smmraja.com.
Thiết kế database: [docs/database-design.md](docs/database-design.md).

## Chạy local (Docker)

```bash
cp .env.example .env            # lần đầu
docker compose up -d            # app :8088, MySQL :3307, queue worker, scheduler
docker compose exec app composer install
docker compose exec app php artisan key:generate   # lần đầu
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link  # ảnh đính kèm ticket
npm install && npm run build                      # CSS/JS giao diện khách (dev: npm run dev)
```

- Web khách: http://localhost:8088 (đăng ký / đăng nhập bằng email hoặc username)
- Trang admin (Filament): http://localhost:8088/admin — chỉ tài khoản `role = admin`

Seeder tạo cấp bậc, tiền tệ (USD/VND/THB), 3 kênh nạp (SePay, USDT TRC20, USDT BEP20) và tài khoản admin
(`ADMIN_EMAIL` / `ADMIN_PASSWORD`; bỏ trống password thì mật khẩu ngẫu nhiên được in ra khi seed).
Kênh nạp nào chưa khai báo tài khoản/ví trong `.env` sẽ ở trạng thái `disabled`.

Chạy test (dùng database `smm_testing`):

```bash
docker compose exec app php artisan test
```

## Cấu trúc chính

| Thư mục | Nội dung |
|---|---|
| `app/Services/BalanceService.php` | Cửa duy nhất thay đổi số dư, ghi sổ cái `transactions`, cập nhật `total_spent` + lên cấp |
| `app/Services/OrderService.php` | Đặt đơn (single/mass/API), huỷ, refill, cập nhật trạng thái + hoàn tiền |
| `app/Services/PricingService.php` | Giá theo cấp bậc |
| `app/Services/Providers/` | Client API PerfectPanel, đồng bộ/import dịch vụ từ provider |
| `app/Services/Payments/` | Phiếu nạp, khớp giao dịch, quét ví crypto (TronGrid, Etherscan V2) |
| `app/Http/Controllers/Api/V2Controller.php` | API cho khách (reseller) |
| `app/Http/Controllers/Webhooks/SepayWebhookController.php` | Webhook SePay |
| `app/Jobs/` | Gửi đơn / huỷ / refill sang provider (chạy trong queue) |
| `app/Console/Commands/` | Lệnh đồng bộ, quét ví, tỉ giá (lịch chạy ở `routes/console.php`) |

## Giao diện

**Web khách** – Blade + Alpine.js + Tailwind (Vite). Mỗi trang là khung Blade trong `resources/views/pages/`,
dữ liệu lấy qua JSON nội bộ `/ajax/*` (session + CSRF, `app/Http/Controllers/Web/Ajax/`).
Hàm dùng chung ở `resources/js/app.js`: `api.get/post/put`, `toast()`, `formatMoney()`, `$store.me` (số dư ở header).
Lỗi nghiệp vụ trả 422 kèm thông báo tiếng Việt (`lang/vi.json`); API khách (`/api/v2`) vẫn trả tiếng Anh.

**Trang admin** – Filament v5 (`app/Filament/`): đơn hàng (cập nhật trạng thái, gửi lại, huỷ/hoàn tiền), refill,
dịch vụ, danh mục, provider (đồng bộ / import), phiếu nạp, giao dịch tiền vào (gán cho khách), kênh nạp,
sổ cái, khách hàng (cộng/trừ tiền), cấp bậc, ticket, tiền tệ, API log, cài đặt, dashboard thống kê.

## Lệnh artisan

| Lệnh | Lịch | Mô tả |
|---|---|---|
| `orders:sync` | 5 phút | Đồng bộ trạng thái đơn, hoàn tiền partial/canceled |
| `refills:sync` | 10 phút | Đồng bộ trạng thái refill |
| `providers:sync {provider?}` | 1 giờ | Cập nhật giá vốn, min/max, tắt dịch vụ provider đã bỏ, số dư provider |
| `deposits:scan` | 1 phút | Quét ví crypto, tự khớp phiếu nạp |
| `deposits:expire` | 5 phút | Phiếu quá hạn → `expired` |
| `currencies:update-rates` | Hằng ngày | Tỉ giá hiển thị VND/THB |
| `providers:import {provider} --markup=30 [--only=1,2]` | Tay | Import dịch vụ mới từ provider |
| `users:api-key {email}` | Tay | Tạo lại API key cho user |

## API cho khách

`POST /api/v2` (form hoặc JSON) với `key`, `action` – tương thích chuẩn PerfectPanel:

| action | Tham số |
|---|---|
| `services` | – |
| `add` | `service`, `link`, `quantity`, [`runs`, `interval`], tham số theo loại (`comments`, `usernames`, `hashtags`, `username`, `answer_number`…) |
| `status` | `order` hoặc `orders` (tối đa 100, phẩy) |
| `refill` | `order` hoặc `orders` |
| `refill_status` | `refill` hoặc `refills` |
| `cancel` | `order` hoặc `orders` |
| `balance` | – |

Tiền luôn tính bằng USD; `rate` trong `services` là giá đã giảm theo cấp của user gọi API.

## Nạp tiền

- **SePay**: cấu hình webhook trên SePay trỏ tới `POST /api/webhooks/sepay/{payment_method_id}`, chứng thực kiểu *API Key*
  bằng giá trị `SEPAY_WEBHOOK_KEY`. Phiếu được khớp theo mã `NAPxxxxxxx` trong nội dung chuyển khoản.
- **Crypto**: không qua cổng trung gian. Mỗi phiếu có số tiền lẻ riêng (vd `10.137 USDT`), `deposits:scan` quét ví
  và khớp theo đúng số tiền. Giao dịch không khớp (sàn trừ phí, chuyển trễ…) khách dán tx hash để nhận
  (`DepositService::claim`, lệch tối đa ±10%) hoặc admin gán tay (`DepositService::assignToUser`).
