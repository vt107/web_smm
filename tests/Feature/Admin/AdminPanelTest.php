<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\TicketCategory;
use App\Enums\TransactionType;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\PaymentMethod;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use App\Services\TicketService;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_customers_cannot_access_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }

    public function test_all_admin_pages_render(): void
    {
        Queue::fake();
        $customer = User::factory()->withBalance(50)->create();
        $service = Service::factory()->create();
        $order = app(OrderService::class)->place($customer, $service, ['link' => 'https://x.com/a', 'quantity' => 100], OrderSource::Web);
        $ticket = app(TicketService::class)->open($customer, 'Đơn chậm', TicketCategory::SpeedUp, (string) $order->id, 'Giúp mình với');
        $method = PaymentMethod::factory()->sepay()->create();

        $this->actingAs($this->admin);

        $urls = [
            '/admin', '/admin/orders', "/admin/orders/{$order->id}", '/admin/refills',
            '/admin/services', '/admin/services/create', "/admin/services/{$service->id}/edit",
            '/admin/categories', '/admin/providers',
            '/admin/deposits', '/admin/incoming-transfers', '/admin/payment-methods',
            '/admin/payment-methods/create', "/admin/payment-methods/{$method->id}/edit", '/admin/transactions',
            '/admin/users', '/admin/users/create', "/admin/users/{$customer->id}/edit", '/admin/levels',
            '/admin/tickets', "/admin/tickets/{$ticket->id}",
            '/admin/currencies', '/admin/api-logs', '/admin/settings',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_can_adjust_customer_balance(): void
    {
        $customer = User::factory()->withBalance(10)->create();

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->callAction(TestAction::make('adjustBalance')->table($customer), [
                'type' => TransactionType::AdminAdd->value,
                'amount' => '5.5',
                'description' => 'Tặng thưởng',
            ])
            ->assertHasNoFormErrors();

        $this->assertSame('15.500000', $customer->fresh()->balance);
        $this->assertSame($this->admin->id, $customer->transactions()->sole()->created_by);
    }

    public function test_admin_can_complete_manual_order_partially(): void
    {
        $customer = User::factory()->withBalance(10)->create();
        $service = Service::factory()->manual()->create(['rate' => '2']);
        $order = app(OrderService::class)->place($customer, $service, ['link' => 'https://x.com/a', 'quantity' => 1000], OrderSource::Web);

        Livewire::actingAs($this->admin)
            ->test(ViewOrder::class, ['record' => $order->id])
            ->callAction('updateStatus', ['status' => OrderStatus::Partial->value, 'start_count' => 10, 'remains' => 500])
            ->assertHasNoFormErrors();

        $this->assertSame(OrderStatus::Partial, $order->fresh()->status);
        $this->assertSame('9.000000', $customer->fresh()->balance);
    }

    public function test_admin_can_save_settings(): void
    {
        Livewire::actingAs($this->admin)
            ->test(Settings::class)
            ->fillForm(['order_max_send_attempts' => 3, 'price_sync_markup_percent' => 25])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(3, (int) Setting::get('order_max_send_attempts'));
        $this->assertSame(25, (int) Setting::get('price_sync_markup_percent'));
    }
}
