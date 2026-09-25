<?php

namespace Tests\Feature\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\RefillStatus;
use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use App\Services\OrderService;
use Database\Seeders\SandboxProviderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Chạy trọn luồng thật (queue → PerfectPanelClient → HTTP → provider giả lập → đồng bộ → hoàn tiền). */
class SandboxProviderTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SandboxProviderSeeder::class);
        $this->user = User::factory()->withBalance(100)->create();

        // Chuyển request HTTP của PerfectPanelClient vào chính app (endpoint giả lập).
        Http::fake(['sandbox.test/*' => function (Request $request) {
            $response = $this->post('/api/sandbox-provider/v2', $request->data());

            return Http::response($response->json(), $response->status());
        }]);
    }

    private function place(string $providerServiceId, array $input = ['quantity' => 1000]): Order
    {
        $service = Service::query()->where('provider_service_id', $providerServiceId)->sole();

        return app(OrderService::class)
            ->place($this->user, $service, ['link' => 'https://instagram.com/sandbox'] + $input, OrderSource::Web)
            ->refresh();
    }

    private function syncAfter(int $minutes): void
    {
        $this->travel($minutes)->minutes();
        $this->artisan('orders:sync')->assertSuccessful();
    }

    public function test_order_progresses_to_completed_and_can_be_refilled(): void
    {
        $order = $this->place('101');
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertNotNull($order->provider_order_id);

        $this->syncAfter(2);
        $order->refresh();
        $this->assertSame(OrderStatus::InProgress, $order->status);
        $this->assertGreaterThan(0, $order->remains);
        $this->assertLessThan(1000, $order->remains);

        $this->syncAfter(4);
        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);

        $refill = app(OrderService::class)->requestRefill($order->fresh())->refresh();
        $this->assertSame(RefillStatus::Processing, $refill->status);

        $this->travel(3)->minutes();
        $this->artisan('refills:sync')->assertSuccessful();
        $this->assertSame(RefillStatus::Completed, $refill->fresh()->status);
    }

    public function test_partial_order_refunds_undelivered_part(): void
    {
        $order = $this->place('102'); // 1000 × $0.65 / 1000 = $0.65

        $this->syncAfter(10);

        $order->refresh();
        $this->assertSame(OrderStatus::Partial, $order->status);
        $this->assertSame(300, $order->remains);
        $this->assertSame('0.195000', $order->refunded_amount);
        $this->assertSame('99.545000', $this->user->fresh()->balance);
    }

    public function test_order_canceled_by_provider_is_refunded(): void
    {
        $order = $this->place('103');

        $this->syncAfter(2);

        $this->assertSame(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
    }

    public function test_rejected_order_is_refunded_after_retries(): void
    {
        $order = $this->place('104');

        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertSame('Not enough funds on balance', $order->provider_error);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
    }

    public function test_customer_cancel_goes_through_provider(): void
    {
        $order = $this->place('101');

        app(OrderService::class)->requestCancel($order);
        $this->assertNotNull($order->fresh()->cancel_requested_at);

        $this->syncAfter(5);

        $this->assertSame(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertSame('100.000000', $this->user->fresh()->balance);
    }

    public function test_custom_comments_and_package_services(): void
    {
        $comments = $this->place('105', ['comments' => "Great!\nLove it\nNice"]);
        $package = $this->place('106', []);

        $this->syncAfter(10);

        $this->assertSame(OrderStatus::Completed, $comments->fresh()->status);
        $this->assertSame(3, $comments->quantity);
        $this->assertSame(OrderStatus::Completed, $package->fresh()->status);
    }

    public function test_sandbox_rejects_wrong_key(): void
    {
        $this->post('/api/sandbox-provider/v2', ['key' => 'wrong', 'action' => 'balance'])
            ->assertExactJson(['error' => 'Invalid API key']);
    }
}
