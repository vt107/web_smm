<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderSource;
use App\Enums\TicketCategory;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StaffPermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $support;

    protected function setUp(): void
    {
        parent::setUp();

        $this->support = User::factory()->create();
        $this->support->forceFill(['role' => UserRole::Support])->save();
    }

    public function test_support_can_view_orders_and_answer_tickets(): void
    {
        Queue::fake();
        Http::fake(['provider.test/*' => Http::response(['order' => 9001])]);
        $customer = User::factory()->withBalance(50)->create();
        $order = app(OrderService::class)->place($customer, Service::factory()->create(), ['link' => 'https://x.com/a', 'quantity' => 100], OrderSource::Web);
        $ticket = app(TicketService::class)->open($customer, 'Help', TicketCategory::Other, null, 'Hi');

        $this->actingAs($this->support);

        foreach (['/admin', '/admin/orders', "/admin/orders/{$order->id}", '/admin/refills', '/admin/tickets', "/admin/tickets/{$ticket->id}", '/admin/users', '/admin/services'] as $url) {
            $this->get($url)->assertOk();
        }

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction('reply', ['message' => 'We are on it'])
            ->assertHasNoFormErrors();
        $this->assertSame(2, $ticket->messages()->count());
    }

    public function test_support_cannot_reach_money_or_settings(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($this->support);

        foreach ([
            '/admin/deposits', '/admin/incoming-transfers', '/admin/payment-methods', '/admin/transactions',
            '/admin/providers', '/admin/levels', '/admin/currencies', '/admin/api-logs', '/admin/categories',
            '/admin/settings', '/admin/website-settings', '/admin/services/create', "/admin/users/{$customer->id}/edit",
        ] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_support_does_not_see_money_actions(): void
    {
        Queue::fake();
        $customer = User::factory()->withBalance(50)->create();
        $order = app(OrderService::class)->place($customer, Service::factory()->manual()->create(), ['link' => 'https://x.com/a', 'quantity' => 100], OrderSource::Web);

        Livewire::actingAs($this->support)
            ->test(ViewOrder::class, ['record' => $order->id])
            ->assertActionHidden('updateStatus')
            ->assertActionHidden('cancelRefund')
            ->assertActionVisible('note');

        Livewire::actingAs($this->support)
            ->test(ListUsers::class)
            ->assertTableActionHidden('adjustBalance', $customer);
    }

    public function test_customers_still_cannot_access_admin(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    }
}
