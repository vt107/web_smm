<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\RefillStatus;
use App\Enums\ServiceUpdateType;
use App\Enums\TransactionType;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesProvider;
use Tests\TestCase;

class OrderLifecycleTest extends TestCase
{
    use FakesProvider, RefreshDatabase;

    private User $user;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withBalance(100)->create();
        $this->service = Service::factory()->create(['rate' => '2.000000']);
        $this->fakeProvider();
    }

    private function placeOrder(int $quantity = 1000, ?Service $service = null): Order
    {
        return app(OrderService::class)
            ->place($this->user, $service ?? $this->service, ['link' => 'https://x.com/a', 'quantity' => $quantity], OrderSource::Web)
            ->refresh();
    }

    public function test_order_is_canceled_and_refunded_after_max_send_attempts(): void
    {
        $this->providerResponses['add'] = ['error' => 'Not enough funds on balance'];

        $order = $this->placeOrder();

        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertSame(5, $order->send_attempts);
        $this->assertSame('Not enough funds on balance', $order->provider_error);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
        Http::assertSentCount(5);
    }

    public function test_partial_order_refunds_remains_only_once(): void
    {
        $order = $this->placeOrder();
        $this->providerResponses['status'] = ['9001' => ['charge' => '1.5', 'start_count' => '120', 'status' => 'Partial', 'remains' => '250', 'currency' => 'USD']];

        $this->artisan('orders:sync')->assertSuccessful();
        $order->forceFill(['last_checked_at' => now()->subHour()])->save();
        $this->artisan('orders:sync')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Partial, $order->status);
        $this->assertSame(120, $order->start_count);
        $this->assertSame('0.500000', $order->refunded_amount);
        $this->assertNotNull($order->completed_at);
        $this->assertSame('98.500000', $this->user->fresh()->balance);
        $this->assertSame(1, $this->user->transactions()->where('type', TransactionType::PartialRefund)->count());
    }

    public function test_canceled_order_refunds_full_charge(): void
    {
        $order = $this->placeOrder();
        $this->providerResponses['status'] = ['9001' => ['status' => 'Canceled', 'remains' => '1000']];

        $this->artisan('orders:sync')->assertSuccessful();

        $this->assertSame(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
    }

    public function test_completed_order_can_be_refilled(): void
    {
        $order = $this->placeOrder();
        $this->providerResponses['status'] = ['9001' => ['status' => 'Completed', 'remains' => '0']];
        $this->providerResponses['refill'] = ['refill' => '77'];
        $this->providerResponses['refill_status'] = [['refill' => '77', 'status' => 'Completed']];

        $this->artisan('orders:sync');
        $refill = app(OrderService::class)->requestRefill($order->fresh());

        $refill->refresh();
        $this->assertSame(RefillStatus::Processing, $refill->status);
        $this->assertSame('77', $refill->provider_refill_id);

        $this->artisan('refills:sync')->assertSuccessful();
        $this->assertSame(RefillStatus::Completed, $refill->fresh()->status);
    }

    public function test_manual_service_waits_for_admin(): void
    {
        $service = Service::factory()->manual()->create(['rate' => '2.000000']);

        $order = $this->placeOrder(1000, $service);

        $this->assertSame(OrderStatus::Pending, $order->status);
        Http::assertNothingSent();

        app(OrderService::class)->applyStatus($order, OrderStatus::Partial, startCount: 50, remains: 100);

        $this->assertSame('0.200000', $order->fresh()->refunded_amount);
        $this->assertSame('98.200000', $this->user->fresh()->balance);
    }

    public function test_price_changes_are_logged_for_updates_page(): void
    {
        $this->service->update(['rate' => '2.5']);
        $this->service->update(['status' => 'disabled']);

        $types = $this->service->updates()->orderBy('id')->pluck('type')->all();

        $this->assertSame([ServiceUpdateType::New, ServiceUpdateType::PriceIncrease, ServiceUpdateType::Disabled], $types);
    }

    public function test_provider_sync_updates_cost_and_disables_removed_services(): void
    {
        $removed = Service::factory()->create(['provider_id' => $this->service->provider_id]);
        $this->providerResponses['services'] = [[
            'service' => $this->service->provider_service_id, 'name' => 'IG', 'type' => 'Default', 'category' => 'IG',
            'rate' => '1.8', 'min' => '20', 'max' => '5000', 'refill' => false, 'cancel' => true,
        ]];

        $this->artisan('providers:sync')->assertSuccessful();

        $this->service->refresh();
        $this->assertSame('1.800000', $this->service->provider_rate);
        $this->assertSame(20, $this->service->min);
        $this->assertFalse($this->service->refill);
        $this->assertFalse($removed->fresh()->isActive());
        $this->assertSame('100.000000', $this->service->provider->fresh()->balance);
    }
}
