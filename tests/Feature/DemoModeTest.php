<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Settings;
use App\Filament\Pages\WebsiteSettings;
use App\Models\ApiLog;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Providers\DemoServiceProvider;
use App\Support\Demo\DemoMode;
use App\Support\Demo\DemoModeException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/** Bản demo chỉ xem (DEMO_MODE=true): chặn ghi từ web, nút Demo, điền sẵn tài khoản. */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->app->register(DemoServiceProvider::class, force: true);
        Route::getRoutes()->refreshNameLookups();
    }

    protected function tearDown(): void
    {
        DemoMode::forceGuard(false);

        parent::tearDown();
    }

    public function test_sql_writes_are_blocked_but_reads_and_infrastructure_tables_work(): void
    {
        $user = User::factory()->create(['name' => 'Before']);
        DemoMode::forceGuard();

        $this->assertSame(1, User::query()->count());
        Cache::put('demo-test', 1, 60); // bảng cache vẫn ghi được

        $this->expectException(DemoModeException::class);
        DB::table('users')->where('id', $user->id)->update(['name' => 'After']);
    }

    public function test_bypass_allows_writes(): void
    {
        $user = User::factory()->create();
        DemoMode::forceGuard();

        DemoMode::bypass(fn () => $user->forceFill(['name' => 'Bypassed'])->save());

        $this->assertSame('Bypassed', $user->fresh()->name);
    }

    public function test_public_form_post_is_blocked_with_flash_message(): void
    {
        DemoMode::forceGuard();

        $this->from('/register')->post('/register', [
            'name' => 'Visitor', 'username' => 'visitor', 'email' => 'visitor@example.com',
            'password' => 'secret12345', 'password_confirmation' => 'secret12345', 'terms' => '1',
        ])->assertRedirect('/register')->assertSessionHas('demo_blocked');

        $this->assertDatabaseMissing('users', ['email' => 'visitor@example.com']);
    }

    public function test_blocked_form_message_is_shown_after_redirect(): void
    {
        DemoMode::forceGuard();

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'demo@example.com'])->assertRedirect('/forgot-password');

        $this->get('/forgot-password')->assertSee('data-flash="'.e(DemoMode::message()).'"', false);
    }

    public function test_customer_ajax_writes_return_403_json(): void
    {
        $user = User::factory()->create();
        DemoMode::forceGuard();

        $this->actingAs($user)
            ->postJson('/ajax/tickets', ['subject' => 'Hi', 'category' => 'other', 'message' => 'Hello'])
            ->assertForbidden()
            ->assertJsonPath('message', DemoMode::message());

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_reseller_api_reads_work_and_writes_return_api_error_without_logging(): void
    {
        $user = User::factory()->create();
        $key = $user->generateApiKey();
        DemoMode::forceGuard();

        $this->post('/api/v2', ['key' => $key, 'action' => 'balance'])->assertOk()->assertJsonStructure(['balance', 'currency']);
        $this->post('/api/v2', ['key' => $key, 'action' => 'add', 'service' => 1])->assertOk()->assertExactJson(['error' => DemoMode::message()]);
        $this->post('/api/v2', ['key' => 'wrong', 'action' => 'balance'])->assertOk()->assertExactJson(['error' => 'Invalid API key']);

        $this->assertSame(0, ApiLog::query()->count());
    }

    public function test_payment_webhook_is_rejected(): void
    {
        $method = PaymentMethod::factory()->sepay()->create();
        DemoMode::forceGuard();

        $this->postJson("/api/webhooks/sepay/{$method->id}", ['id' => 1])->assertForbidden();
        $this->assertDatabaseCount('incoming_transfers', 0);
    }

    public function test_filament_save_is_blocked_and_shows_toast(): void
    {
        $admin = User::factory()->admin()->create();
        DemoMode::forceGuard();

        Livewire::actingAs($admin)->test(Settings::class)
            ->fillForm(['order_max_send_attempts' => 9])
            ->call('save')
            ->assertDispatched('demo-blocked');

        $this->assertNull(Setting::query()->find('order_max_send_attempts'));
    }

    public function test_external_side_effects_are_blocked(): void
    {
        Http::fake();
        $admin = User::factory()->admin()->create();
        DemoMode::forceGuard();

        Livewire::actingAs($admin)->test(WebsiteSettings::class)
            ->callAction(TestAction::make('testTelegram'))
            ->assertDispatched('demo-blocked');

        Http::assertNothingSent();
    }

    public function test_widget_is_injected_and_login_forms_are_prefilled(): void
    {
        $this->get('/')->assertOk()->assertSee('id="dmw"', false)->assertSee('admin@example.com');
        $this->get('/login?demo=reseller')->assertOk()->assertSee('value="reseller@example.com"', false);
        $this->get('/login')->assertSee('value="demo@example.com"', false);

        Livewire::test(Login::class)->assertSet('data.email', 'admin@example.com');
        Livewire::withQueryParams(['demo' => 'support'])->test(Login::class)->assertSet('data.email', 'support@example.com');
    }

    public function test_switch_logs_out_and_opens_portal_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/demo/switch/support')
            ->assertRedirect(url('/admin/login').'?demo=support');

        $this->assertGuest();
        $this->get('/demo/switch/unknown')->assertNotFound();
    }

    public function test_nothing_changes_when_demo_mode_is_off(): void
    {
        config(['demo.enabled' => false]);

        $this->assertNull(DemoMode::credentials('admin'));
        $this->assertFalse(DemoMode::guarding());
    }
}
