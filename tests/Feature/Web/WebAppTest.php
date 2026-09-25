<?php

namespace Tests\Feature\Web;

use App\Enums\OrderStatus;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebAppTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->user = User::factory()->withBalance(100)->create(['username' => 'khachhang']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Đăng nhập');
    }

    public function test_register_and_login_with_username(): void
    {
        $referrer = User::factory()->create();

        $this->post('/register', [
            'name' => 'Nguyễn Văn A', 'username' => 'nguyenvana', 'email' => 'a@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123', 'ref' => $referrer->ref_code,
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('username', 'nguyenvana')->sole();
        $this->assertSame($referrer->id, $user->referred_by);
        $this->assertSame('Member', $user->level->name);

        $this->post('/logout');
        $this->post('/login', ['email' => 'nguyenvana', 'password' => 'secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_shows_vietnamese_error(): void
    {
        $this->from('/login')->post('/login', ['email' => 'khachhang', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
    }

    public function test_banned_user_is_logged_out(): void
    {
        $this->user->forceFill(['status' => 'banned'])->save();

        $this->actingAs($this->user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_all_pages_render(): void
    {
        $this->actingAs($this->user);

        foreach (['/dashboard', '/new-order', '/orders', '/services', '/add-funds', '/transactions', '/tickets', '/tickets/1', '/updates', '/account', '/api-docs'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_catalog_returns_discounted_price_in_display_currency(): void
    {
        Service::factory()->create(['rate' => '2', 'name' => 'IG Likes']);
        $this->user->forceFill(['currency' => 'VND', 'level_id' => 3])->save(); // Gold −5%

        $this->actingAs($this->user)->getJson('/ajax/services')
            ->assertOk()
            ->assertJsonPath('currency.code', 'VND')
            ->assertJsonPath('categories.0.services.0.rate_usd', '1.90')
            ->assertJsonPath('categories.0.services.0.rate', '49.400 ₫');
    }

    public function test_place_order_and_business_errors_are_localized(): void
    {
        $service = Service::factory()->create(['rate' => '2', 'min' => 100]);
        $this->actingAs($this->user);

        $this->postJson('/ajax/orders', ['service' => $service->id, 'link' => 'https://x.com/a', 'quantity' => 50])
            ->assertStatus(422)
            ->assertJson(['message' => 'Số lượng tối thiểu là 100']);

        $this->postJson('/ajax/orders', ['service' => $service->id, 'quantity' => 500])
            ->assertStatus(422)
            ->assertJson(['message' => 'Vui lòng nhập link.']);

        $this->postJson('/ajax/orders', ['service' => $service->id, 'link' => 'https://x.com/a', 'quantity' => 500])
            ->assertCreated()
            ->assertJsonPath('order.charge', '$1.00');

        $this->getJson('/ajax/orders')->assertJsonPath('data.0.status', 'pending')->assertJsonPath('data.0.can_cancel', true);
        $this->getJson('/ajax/me')->assertJsonPath('balance', '$99.00');
    }

    public function test_mass_order_reports_each_line(): void
    {
        $service = Service::factory()->create(['rate' => '2', 'min' => 10]);

        $this->actingAs($this->user)->postJson('/ajax/orders/mass', [
            'orders' => "{$service->id} | https://x.com/a | 100\n{$service->id} | https://x.com/b | 1\nabc",
        ])
            ->assertOk()
            ->assertJsonPath('results.0.error', null)
            ->assertJsonPath('results.1.error', 'Số lượng tối thiểu là 10')
            ->assertJsonPath('results.2.error', 'Sai định dạng, cần: service_id | link | số lượng');

        $this->assertSame(1, Order::query()->count());
    }

    public function test_cancel_own_order_only(): void
    {
        $service = Service::factory()->create(['rate' => '2']);
        $this->actingAs($this->user)->postJson('/ajax/orders', ['service' => $service->id, 'link' => 'https://x.com/a', 'quantity' => 500]);
        $order = Order::query()->sole();

        $this->actingAs(User::factory()->create())->postJson("/ajax/orders/{$order->id}/cancel")->assertNotFound();

        $this->actingAs($this->user)->postJson("/ajax/orders/{$order->id}/cancel")->assertOk();
        $this->assertSame(OrderStatus::Canceled, $order->fresh()->status);
    }

    public function test_create_deposit_and_claim_error(): void
    {
        $method = PaymentMethod::factory()->sepay()->create();
        $this->actingAs($this->user);

        $deposit = $this->postJson('/ajax/deposits', ['payment_method_id' => $method->id, 'amount' => 10])
            ->assertCreated()
            ->assertJsonPath('paid_amount', '250.000')
            ->assertJsonPath('gateway', 'sepay')
            ->json();

        $this->assertStringContainsString('qr.sepay.vn', $deposit['qr_url']);
        $this->getJson("/ajax/deposits/{$deposit['id']}")->assertJsonPath('data.status', 'pending');

        $this->postJson('/ajax/deposits', ['payment_method_id' => $method->id, 'amount' => 0.5])
            ->assertStatus(422)->assertJson(['message' => 'Số tiền nạp phải từ 1.00 đến 5000.00 USD']);

        $this->postJson('/ajax/deposits/claim', ['payment_method_id' => $method->id, 'txid' => 'nope'])
            ->assertStatus(422)->assertJson(['message' => 'Chưa thấy giao dịch, vui lòng thử lại sau vài phút']);
    }

    public function test_ticket_flow_with_attachment(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user);

        $this->postJson('/ajax/tickets', ['subject' => 'Chậm', 'category' => 'speed_up', 'message' => 'Đơn chạy chậm'])
            ->assertStatus(422)->assertJson(['message' => 'Vui lòng nhập ít nhất 1 mã đơn hàng']);

        $ticket = $this->post('/ajax/tickets', [
            'subject' => 'Hỏi thanh toán', 'category' => 'payment', 'message' => 'Mình đã chuyển tiền',
            'attachments' => [self::fakePng()],
        ], ['Accept' => 'application/json'])->assertCreated()->json('ticket');

        $this->getJson("/ajax/tickets/{$ticket['id']}")
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonCount(1, 'data.messages.0.attachments');

        $this->postJson("/ajax/tickets/{$ticket['id']}/reply", ['message' => 'Bổ sung thông tin'])->assertOk();
        $this->postJson("/ajax/tickets/{$ticket['id']}/close")->assertOk();

        $this->actingAs(User::factory()->create())->getJson("/ajax/tickets/{$ticket['id']}")->assertNotFound();
        $this->assertSame(TicketStatus::Closed, $this->user->tickets()->sole()->status);
    }

    /** Ảnh PNG 1x1 thật (không cần extension GD như UploadedFile::fake()->image()). */
    private static function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bill.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));
    }

    public function test_account_settings_password_and_api_key(): void
    {
        $this->actingAs($this->user);

        $this->putJson('/ajax/account', ['name' => 'Tên mới', 'currency' => 'THB', 'timezone' => 'Asia/Bangkok'])->assertOk();
        $this->getJson('/ajax/me')->assertJsonPath('balance', '฿3,250.00');

        $this->putJson('/ajax/account/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertStatus(422);
        $this->putJson('/ajax/account/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertOk();

        $key = $this->postJson('/ajax/account/api-key')->assertOk()->json('key');
        $this->assertTrue(User::findByApiKey($key)->is($this->user));
    }
}
