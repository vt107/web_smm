<?php

namespace Tests\Feature;

use App\Models\ApiLog;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/** Các lỗ hổng tìm thấy khi rà soát bảo mật – đảm bảo không tái diễn. */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_link_rejects_script_urls(): void
    {
        Queue::fake();
        $user = User::factory()->withBalance(10)->create();
        $service = Service::factory()->create();
        $key = $user->generateApiKey();

        foreach (['javascript:alert(1)', 'data:text/html,<script>', ' javascript:alert(1)'] as $link) {
            $this->post('/api/v2', ['key' => $key, 'action' => 'add', 'service' => $service->id, 'link' => $link, 'quantity' => 100])
                ->assertJsonStructure(['error']);
        }

        foreach (['https://instagram.com/abc', '@username', 't.me/channel'] as $link) {
            $this->post('/api/v2', ['key' => $key, 'action' => 'add', 'service' => $service->id, 'link' => $link, 'quantity' => 100])
                ->assertJsonStructure(['order']);
        }

        $this->assertSame(3, Order::query()->count());
    }

    public function test_invalid_api_keys_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/api/v2', ['key' => "random-{$i}", 'action' => 'balance'])->assertOk();
        }

        $this->post('/api/v2', ['key' => 'another-random', 'action' => 'balance'])->assertStatus(429);
        $this->assertSame(30, ApiLog::query()->count());
    }

    public function test_api_log_only_keeps_known_parameters(): void
    {
        $this->post('/api/v2', ['key' => 'bad', 'action' => 'add', 'junk' => str_repeat('x', 5000), 'link' => str_repeat('y', 2000)]);

        $log = ApiLog::query()->sole();
        $this->assertArrayNotHasKey('junk', $log->request);
        $this->assertArrayNotHasKey('key', $log->request);
        $this->assertLessThanOrEqual(303, mb_strlen($log->request['link']));
    }

    public function test_two_factor_is_locked_per_account_after_failures(): void
    {
        $user = User::factory()->create(['username' => 'victim']);
        $user->saveAppAuthenticationSecret(app(Google2FA::class)->generateSecretKey(32));

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'victim', 'password' => 'password']);
            $this->post('/two-factor-challenge', ['method' => 'app', 'code' => '000000'])->assertSessionHasErrors('code');
        }

        // Lần thứ 6 (kể cả đăng nhập lại) bị khoá, dù mã đúng.
        $this->post('/login', ['email' => 'victim', 'password' => 'password']);
        $valid = app(Google2FA::class)->getCurrentOtp($user->app_authentication_secret);
        $this->post('/two-factor-challenge', ['method' => 'app', 'code' => $valid])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_change_logs_out_other_devices_but_keeps_current_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/ajax/account/password', [
            'current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->getJson('/ajax/me')->assertOk();
    }
}
