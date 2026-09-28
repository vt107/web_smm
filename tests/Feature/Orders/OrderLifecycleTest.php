<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\RefillStatus;
use App\Enums\ServiceUpdateType;
use App\Enums\TransactionType;
use App\Exceptions\SmmException;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

    public function test_web_order_rejected_by_provider_is_refunded_immediately(): void
    {
        $this->providerResponses['add'] = ['error' => 'Not enough funds on balance'];

        try {
            $this->placeOrder();
            $this->fail('Expected SmmException');
        } catch (SmmException $e) {
            $this->assertStringContainsString('was rejected and refunded', $e->getMessage());
        }

        $order = Order::query()->sole();
        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertSame(1, $order->send_attempts);
        $this->assertSame('Not enough funds on balance', $order->provider_error);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
        Http::assertSentCount(1);
    }

    public function test_web_order_with_connection_error_is_resent_by_cron(): void
    {
        $this->providerResponses['add'] = fn () => throw new ConnectionException('timeout');

        $order = $this->placeOrder();

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(1, $order->send_attempts);
        $this->assertStringContainsString('timeout', $order->provider_error);
        $this->assertSame('98.000000', $this->user->fresh()->balance);

        // Chưa tới giờ gửi lại.
        $this->artisan('orders:send')->assertSuccessful();
        $this->assertSame(1, $order->fresh()->send_attempts);

        $this->providerResponses['add'] = ['order' => 9001];
        $this->travel(31)->seconds();
        $this->artisan('orders:send')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertSame('9001', $order->provider_order_id);
        $this->assertNull($order->provider_error);
        $this->assertNull($order->next_send_at);
    }

    public function test_cron_retries_timed_out_order_until_max_attempts_then_refunds(): void
    {
        $this->providerResponses['add'] = fn () => throw new ConnectionException('timeout');

        $order = $this->placeOrder();

        for ($i = 0; $i < 5; $i++) {
            $this->travel(31)->minutes();
            $this->artisan('orders:send')->assertSuccessful();
        }

        $order->refresh();
        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertSame(5, $order->send_attempts);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
    }

    public function test_cron_refunds_immediately_when_provider_rejects_resend(): void
    {
        $this->providerResponses['add'] = fn () => throw new ConnectionException('timeout');
        $order = $this->placeOrder();

        $this->providerResponses['add'] = ['error' => 'Incorrect link'];
        $this->travel(1)->minutes();
        $this->artisan('orders:send')->assertSuccessful();

        $order->refresh();
        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertSame(2, $order->send_attempts);
        $this->assertSame('Incorrect link', $order->provider_error);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
    }

    public function test_cron_skips_remaining_orders_of_unreachable_provider(): void
    {
        $this->providerResponses['add'] = fn () => throw new ConnectionException('timeout');
        $this->placeOrder(100);
        $this->placeOrder(100);

        $this->travel(1)->minutes();
        $this->artisan('orders:send')->assertSuccessful();

        $this->assertSame([2, 1], Order::query()->orderBy('id')->pluck('send_attempts')->all());
    }

    public function test_mass_order_sends_each_line_and_reports_rejections(): void
    {
        $this->providerResponses['add'] = fn (array $data) => $data['link'] === 'https://x.com/bad'
            ? ['error' => 'Incorrect link']
            : ['order' => 9001];

        $results = app(OrderService::class)->placeMass($this->user, implode("\n", [
            "{$this->service->id} | https://x.com/a | 100",
            "{$this->service->id} | https://x.com/bad | 100",
        ]));

        $this->assertNull($results[0]['error']);
        $this->assertStringContainsString('rejected and refunded', $results[1]['error']);
        $this->assertSame(
            [OrderStatus::Processing, OrderStatus::Canceled],
            Order::query()->orderBy('id')->pluck('status')->all(),
        );
        $this->assertSame('99.800000', $this->user->fresh()->balance);
    }

    public function test_mass_order_stops_calling_unreachable_provider_and_cron_sends_the_rest(): void
    {
        $this->providerResponses['add'] = fn () => throw new ConnectionException('timeout');

        $results = app(OrderService::class)->placeMass($this->user, implode("\n", [
            "{$this->service->id} | https://x.com/a | 100",
            "{$this->service->id} | https://x.com/b | 100",
        ]));

        $this->assertSame([null, null], array_column($results, 'error'));
        $this->assertSame([1, 0], Order::query()->orderBy('id')->pluck('send_attempts')->all());

        $this->providerResponses['add'] = ['order' => 9001];
        $this->travel(3)->minutes();
        $this->artisan('orders:send')->assertSuccessful();

        $this->assertSame(2, Order::query()->where('status', OrderStatus::Processing)->count());
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
