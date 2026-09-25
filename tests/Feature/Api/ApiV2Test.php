<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\ServiceType;
use App\Enums\TransactionType;
use App\Models\ApiLog;
use App\Models\Level;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\FakesProvider;
use Tests\TestCase;

class ApiV2Test extends TestCase
{
    use FakesProvider, RefreshDatabase;

    private User $user;

    private string $apiKey;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->withBalance(100)->create();
        $this->apiKey = $this->user->generateApiKey();
        $this->service = Service::factory()->create(['rate' => '2.000000', 'min' => 10, 'max' => 10000]);
        $this->fakeProvider();
    }

    private function api(string $action, array $params = []): TestResponse
    {
        return $this->post('/api/v2', ['key' => $this->apiKey, 'action' => $action] + $params);
    }

    public function test_invalid_key_returns_error_and_is_logged(): void
    {
        $this->post('/api/v2', ['key' => 'wrong', 'action' => 'balance'])
            ->assertOk()
            ->assertExactJson(['error' => 'Invalid API key']);

        $this->assertSame(1, ApiLog::query()->count());
        $this->assertArrayNotHasKey('key', ApiLog::query()->first()->request);
    }

    public function test_balance(): void
    {
        $this->api('balance')->assertExactJson(['balance' => '100.00', 'currency' => 'USD']);
    }

    public function test_services_list_applies_level_discount(): void
    {
        $this->user->level()->associate(Level::query()->where('name', 'Gold')->first())->save();

        $this->api('services')
            ->assertOk()
            ->assertJsonPath('0.service', $this->service->id)
            ->assertJsonPath('0.rate', '1.90')
            ->assertJsonPath('0.type', 'Default')
            ->assertJsonPath('0.refill', true);
    }

    public function test_add_order_charges_balance_and_sends_to_provider(): void
    {
        $response = $this->api('add', ['service' => $this->service->id, 'link' => 'https://instagram.com/test', 'quantity' => 1000]);

        $order = Order::query()->sole();
        $response->assertExactJson(['order' => $order->id]);

        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertSame('9001', $order->provider_order_id);
        $this->assertSame('2.000000', $order->charge);
        $this->assertSame('1.500000', $order->cost);

        $this->user->refresh();
        $this->assertSame('98.000000', $this->user->balance);
        $this->assertSame('2.000000', $this->user->total_spent);

        $transaction = $this->user->transactions()->sole();
        $this->assertSame(TransactionType::Order, $transaction->type);
        $this->assertSame('100.000000', $transaction->balance_before);
        $this->assertSame('98.000000', $transaction->balance_after);
        $this->assertTrue($transaction->reference->is($order));

        Http::assertSent(fn (Request $r) => $r['action'] === 'add'
            && $r['service'] === $this->service->provider_service_id
            && (int) $r['quantity'] === 1000);
    }

    public function test_add_order_fails_when_balance_is_insufficient(): void
    {
        $this->user->forceFill(['balance' => '1'])->save();

        $this->api('add', ['service' => $this->service->id, 'link' => 'https://instagram.com/test', 'quantity' => 1000])
            ->assertExactJson(['error' => 'Not enough funds on balance']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame('1.000000', $this->user->fresh()->balance);
    }

    public function test_add_order_validates_quantity_and_service(): void
    {
        $this->api('add', ['service' => $this->service->id, 'link' => 'https://x.com/a', 'quantity' => 5])
            ->assertExactJson(['error' => 'Quantity less than minimal 10']);

        $this->api('add', ['service' => 999999, 'link' => 'https://x.com/a', 'quantity' => 100])
            ->assertExactJson(['error' => 'Invalid service ID']);

        $this->service->update(['status' => 'disabled']);
        $this->api('add', ['service' => $this->service->id, 'link' => 'https://x.com/a', 'quantity' => 100])
            ->assertExactJson(['error' => 'Service is not available']);
    }

    public function test_custom_comments_quantity_is_counted_from_lines(): void
    {
        $service = Service::factory()->type(ServiceType::CustomComments)->create(['rate' => '10', 'min' => 1, 'max' => 100]);

        $this->api('add', ['service' => $service->id, 'link' => 'https://instagram.com/p/1', 'comments' => "Hay quá\nTuyệt\n\nĐẹp"]);

        $order = Order::query()->sole();
        $this->assertSame(3, $order->quantity);
        $this->assertSame('0.030000', $order->charge);
        $this->assertSame(['comments' => "Hay quá\nTuyệt\n\nĐẹp"], $order->extra);

        Http::assertSent(fn (Request $r) => $r['action'] === 'add' && isset($r['comments']) && ! isset($r['quantity']));
    }

    public function test_dripfeed_charges_total_quantity(): void
    {
        $this->api('add', ['service' => $this->service->id, 'link' => 'https://x.com/a', 'quantity' => 100, 'runs' => 3, 'interval' => 10]);

        $order = Order::query()->sole();
        $this->assertSame('0.600000', $order->charge);
        $this->assertSame(3, $order->runs);

        Http::assertSent(fn (Request $r) => $r['action'] === 'add' && (int) $r['runs'] === 3 && (int) $r['interval'] === 10);
    }

    public function test_status_for_single_and_multiple_orders(): void
    {
        $this->api('add', ['service' => $this->service->id, 'link' => 'https://x.com/a', 'quantity' => 500]);
        $order = Order::query()->sole();

        $this->api('status', ['order' => $order->id])->assertExactJson([
            'charge' => '1.00',
            'start_count' => '0',
            'status' => 'Processing',
            'remains' => '500',
            'currency' => 'USD',
        ]);

        $this->api('status', ['orders' => "{$order->id},999999"])
            ->assertJsonPath("{$order->id}.status", 'Processing')
            ->assertJsonPath('999999.error', 'Incorrect order ID');
    }

    public function test_user_cannot_see_orders_of_other_users(): void
    {
        $this->api('add', ['service' => $this->service->id, 'link' => 'https://x.com/a', 'quantity' => 500]);
        $order = Order::query()->sole();

        $other = User::factory()->create();
        $this->post('/api/v2', ['key' => $other->generateApiKey(), 'action' => 'status', 'order' => $order->id])
            ->assertExactJson(['error' => 'Incorrect order ID']);
    }

    public function test_cancel_unsent_order_refunds_immediately(): void
    {
        Queue::fake();

        $this->api('add', ['service' => $this->service->id, 'link' => 'https://x.com/a', 'quantity' => 1000]);
        $order = Order::query()->sole();
        $this->assertSame('98.000000', $this->user->fresh()->balance);

        $this->api('cancel', ['orders' => (string) $order->id])
            ->assertExactJson([['order' => $order->id, 'cancel' => 1]]);

        $order->refresh();
        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertSame('2.000000', $order->refunded_amount);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
        $this->assertSame('0.000000', $this->user->fresh()->total_spent);
    }

    public function test_user_is_upgraded_after_reaching_level_threshold(): void
    {
        $this->user->forceFill(['balance' => '500'])->save();
        $service = Service::factory()->create(['rate' => '20', 'min' => 10, 'max' => 100000]);

        $this->api('add', ['service' => $service->id, 'link' => 'https://x.com/a', 'quantity' => 5000]); // 100$

        $this->assertSame('Silver', $this->user->fresh()->level->name);

        // Đơn tiếp theo được giảm 3%.
        $this->api('add', ['service' => $service->id, 'link' => 'https://x.com/a', 'quantity' => 1000]);
        $this->assertSame('19.400000', Order::query()->latest('id')->first()->charge);
    }
}
