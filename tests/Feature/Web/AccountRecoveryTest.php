<?php

namespace Tests\Feature\Web;

use App\Filament\Pages\WebsiteSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_reset_forgotten_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'khach@example.com']);

        $this->get('/login')->assertSee('Forgot password?');
        $this->post('/forgot-password', ['email' => 'khach@example.com'])->assertSessionHas('status');

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token) {
            $token = $notification->token;

            return true;
        });

        $this->get("/reset-password/{$token}?email=khach@example.com")->assertOk()->assertSee('Choose a new password');
        $this->post('/reset-password', [
            'token' => $token, 'email' => 'khach@example.com',
            'password' => 'brandnew123', 'password_confirmation' => 'brandnew123',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('brandnew123', $user->fresh()->password));
    }

    public function test_unknown_email_gets_the_same_message(): void
    {
        Notification::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status', 'If an account exists for this email, a password reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_invalid_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'khach@example.com']);

        $this->from('/reset-password/bad')->post('/reset-password', [
            'token' => 'bad', 'email' => 'khach@example.com', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123',
        ])->assertSessionHasErrors('email');
    }

    public function test_smtp_settings_from_admin_are_applied_and_password_is_encrypted(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(WebsiteSettings::class)
            ->fillForm([
                'mail_host' => 'smtp.example.com', 'mail_port' => 465, 'mail_security' => 'ssl',
                'mail_username' => 'noreply@example.com', 'mail_password' => 'app-secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNotSame('app-secret', Setting::get('site.mail_password'));

        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('app-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@example.com', config('mail.from.address'));

        // Lưu lại mà không nhập mật khẩu → giữ mật khẩu cũ.
        Livewire::actingAs(User::factory()->admin()->create())->test(WebsiteSettings::class)->call('save');
        MailSettings::apply();
        $this->assertSame('app-secret', config('mail.mailers.smtp.password'));
    }

    public function test_legal_pages_render_admin_content_safely(): void
    {
        Setting::set('site.support_email', 'help@example.com');
        Setting::set('site.terms_content', "## Rules\nContact {support_email}. <script>alert(1)</script>");

        $this->get('/terms')->assertOk()
            ->assertSee('<h2>Rules</h2>', false)
            ->assertSee('help@example.com')
            ->assertDontSee('<script>alert(1)</script>', false);

        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
    }

    public function test_registration_requires_accepting_terms(): void
    {
        $this->from('/register')->post('/register', [
            'name' => 'John', 'username' => 'john', 'email' => 'john@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors(['terms' => 'Please accept the Terms of Service and Privacy Policy.']);
    }
}
