<?php

namespace Tests\Feature\Web;

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Services\TwoFactorService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['username' => 'khach', 'email' => 'khach@example.com']);
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    /** Mã của bước thời gian kế tiếp (thư viện TOTP dùng time() thật nên travel() không ảnh hưởng). */
    private function nextOtp(string $secret): string
    {
        $google2fa = app(Google2FA::class);

        return $google2fa->oathTotp($secret, $google2fa->getTimestamp() + 1);
    }

    /** @return list<string> mã khôi phục */
    private function enableApp(): array
    {
        $this->actingAs($this->user);
        $secret = $this->postJson('/ajax/two-factor/app/setup')->assertOk()->json('secret');
        $codes = $this->postJson('/ajax/two-factor/app/enable', ['code' => $this->otp($secret)])->assertOk()->json('recovery_codes');
        $this->post('/logout');

        return $codes;
    }

    private function login(): void
    {
        $this->post('/login', ['email' => 'khach', 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
    }

    public function test_enable_authenticator_app_and_log_in_with_code(): void
    {
        $codes = $this->enableApp();
        $this->assertCount(8, $codes);
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());

        $this->login();
        $this->get('/two-factor-challenge')->assertOk()->assertSee('authenticator app');

        $this->post('/two-factor-challenge', ['method' => 'app', 'code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['method' => 'app', 'code' => $this->nextOtp($this->user->fresh()->app_authentication_secret)])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_wrong_code_during_setup_does_not_enable(): void
    {
        $this->actingAs($this->user)->postJson('/ajax/two-factor/app/setup');

        $this->postJson('/ajax/two-factor/app/enable', ['code' => '123456'])
            ->assertStatus(422)->assertJson(['message' => 'The code is invalid, please try again']);
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_recovery_code_works_only_once(): void
    {
        $codes = $this->enableApp();

        $this->login();
        $this->post('/two-factor-challenge', ['method' => 'recovery', 'code' => $codes[0]])->assertRedirect('/dashboard');
        $this->post('/logout');

        $this->login();
        $this->post('/two-factor-challenge', ['method' => 'recovery', 'code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertSame(7, app(TwoFactorService::class)->remainingRecoveryCodes($this->user->fresh()));
    }

    public function test_email_codes_can_be_enabled_and_used_to_log_in(): void
    {
        Notification::fake();
        $this->actingAs($this->user);

        $this->postJson('/ajax/two-factor/email/send')->assertOk();
        $setupCode = null;
        Notification::assertSentTo($this->user, TwoFactorCode::class, function (TwoFactorCode $n) use (&$setupCode) {
            $setupCode = $n->code;

            return true;
        });

        $this->postJson('/ajax/two-factor/email/enable', ['code' => $setupCode])->assertOk();
        $this->assertTrue($this->user->fresh()->has_email_authentication);
        $this->post('/logout');

        // Chỉ bật email → đăng nhập xong hệ thống tự gửi mã.
        $this->travel(61)->seconds();
        $this->login();

        $loginCode = null;
        Notification::assertSentTo($this->user, TwoFactorCode::class, function (TwoFactorCode $n) use (&$loginCode, $setupCode) {
            if ($n->code !== $setupCode) {
                $loginCode = $n->code;
            }

            return true;
        });

        $this->post('/two-factor-challenge', ['method' => 'email', 'code' => $loginCode])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_disabling_requires_password(): void
    {
        $this->enableApp();
        $this->actingAs($this->user);

        $this->postJson('/ajax/two-factor/app/disable', ['password' => 'wrong'])->assertStatus(422);
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());

        $this->postJson('/ajax/two-factor/app/disable', ['password' => 'password'])->assertOk();
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_challenge_without_pending_login_redirects_to_login(): void
    {
        $this->get('/two-factor-challenge')->assertRedirect('/login');
    }

    public function test_admin_can_reset_customer_two_factor(): void
    {
        $this->enableApp();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(ListUsers::class)
            ->callAction(TestAction::make('resetTwoFactor')->table($this->user));

        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_admin_profile_page_offers_two_factor(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('authentication');
    }
}
