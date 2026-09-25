<?php

namespace Tests\Feature\Admin;

use App\Enums\TelegramEvent;
use App\Enums\TicketCategory;
use App\Filament\Pages\WebsiteSettings;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payments\DepositService;
use App\Services\TelegramNotifier;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);
    }

    private function configure(array $events = ['deposit_completed', 'unmatched_transfer', 'ticket']): void
    {
        Livewire::actingAs($this->admin)
            ->test(WebsiteSettings::class)
            ->fillForm([
                'telegram_bot_token' => '123456:ABC-secret',
                'telegram_chat_ids' => "111\n-100222",
                'telegram_events' => $events,
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    /** @return list<array<string, mixed>> */
    private function sentMessages(): array
    {
        return Http::recorded()
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), '/sendMessage'))
            ->map(fn (Request $r) => $r->data() + ['url' => $r->url()])
            ->values()
            ->all();
    }

    public function test_token_is_encrypted_and_kept_when_left_empty(): void
    {
        $this->configure();

        $this->assertNotSame('123456:ABC-secret', Setting::get('site.telegram_bot_token'));
        $this->assertTrue(app(TelegramNotifier::class)->isConfigured());

        Livewire::actingAs($this->admin)->test(WebsiteSettings::class)->call('save');
        $this->assertTrue(app(TelegramNotifier::class)->isConfigured());
    }

    public function test_deposit_and_unmatched_transfer_are_notified_to_every_chat(): void
    {
        $this->configure();
        $customer = User::factory()->create(['username' => 'khach<b>']);
        $method = PaymentMethod::factory()->sepay()->create();
        $deposits = app(DepositService::class);

        $deposit = $deposits->create($customer, $method, '10');
        $deposits->autoMatch($deposits->recordTransfer($method, [
            'external_id' => 'T1', 'amount' => '250000', 'content' => $deposit->code, 'transferred_at' => now(),
        ]));
        $deposits->autoMatch($deposits->recordTransfer($method, [
            'external_id' => 'T2', 'amount' => '50000', 'content' => 'no code', 'transferred_at' => now(),
        ]));

        $messages = $this->sentMessages();
        $this->assertCount(4, $messages); // 2 sự kiện × 2 chat
        $this->assertSame(['111', '-100222'], array_column(array_slice($messages, 0, 2), 'chat_id'));
        $this->assertStringContainsString('bot123456:ABC-secret/sendMessage', $messages[0]['url']);
        $this->assertStringContainsString('Deposit received', $messages[0]['text']);
        $this->assertStringContainsString('khach&lt;b&gt;', $messages[0]['text']); // dữ liệu khách được escape
        $this->assertStringContainsString('not matched', $messages[2]['text']);
    }

    public function test_disabled_events_are_not_sent(): void
    {
        $this->configure(events: ['deposit_completed']);

        app(TicketService::class)->open(User::factory()->create(), 'Help', TicketCategory::Other, null, 'Hello');

        $this->assertSame([], $this->sentMessages());
    }

    public function test_repeated_alerts_are_deduplicated(): void
    {
        $this->configure(events: ['provider_low_balance']);
        $telegram = app(TelegramNotifier::class);

        $telegram->notify(TelegramEvent::ProviderLowBalance, 'low', dedupeKey: 'provider-low:1');
        $telegram->notify(TelegramEvent::ProviderLowBalance, 'low', dedupeKey: 'provider-low:1');

        $this->assertCount(2, $this->sentMessages()); // 1 thông báo × 2 chat
    }

    public function test_nothing_is_sent_when_not_configured(): void
    {
        app(TicketService::class)->open(User::factory()->create(), 'Help', TicketCategory::Other, null, 'Hello');

        Http::assertNothingSent();
    }
}
