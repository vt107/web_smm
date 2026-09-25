<?php

namespace Tests\Feature\Payments;

use App\Enums\DepositStatus;
use App\Enums\TransferStatus;
use App\Models\Deposit;
use App\Models\IncomingTransfer;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payments\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CryptoDepositTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = 'TWalletAddressForTests000000000000';

    private const USDT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    private User $user;

    private PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->method = PaymentMethod::factory()->tron()->create(); // bonus 5%
    }

    /** Giả lập TronGrid trả về các giao dịch USDT (amount tính theo USDT). */
    private function fakeTronGrid(array $transfers): void
    {
        Http::fake([
            'api.trongrid.io/*' => Http::response([
                'success' => true,
                'meta' => ['page_size' => count($transfers)],
                'data' => array_map(fn (array $t) => [
                    'transaction_id' => $t['hash'],
                    'token_info' => ['address' => self::USDT, 'decimals' => 6, 'symbol' => 'USDT'],
                    'block_timestamp' => now()->getTimestampMs(),
                    'from' => 'TSenderAddress',
                    'to' => $t['to'] ?? self::ADDRESS,
                    'type' => 'Transfer',
                    'value' => bcmul($t['amount'], '1000000', 0),
                ], $transfers),
            ]),
        ]);
    }

    private function createDeposit(string $usd = '10'): Deposit
    {
        return app(DepositService::class)->create($this->user, $this->method, $usd);
    }

    public function test_pending_deposits_get_unique_amounts(): void
    {
        $amounts = collect(range(1, 30))->map(fn () => $this->createDeposit('10')->paid_amount);

        $this->assertCount(30, $amounts->unique());
        $amounts->each(function (string $amount) {
            $this->assertGreaterThan(10, (float) $amount);
            $this->assertLessThan(11, (float) $amount);
        });
    }

    public function test_scan_matches_exact_amount_and_adds_bonus_once(): void
    {
        $deposit = $this->createDeposit('10');
        $this->fakeTronGrid([['hash' => 'tx-1', 'amount' => $deposit->paid_amount]]);

        $this->artisan('deposits:scan')->assertSuccessful();
        $this->artisan('deposits:scan')->assertSuccessful();

        $deposit->refresh();
        $expected = bcadd($deposit->paid_amount, bcmul($deposit->paid_amount, '0.05', 6), 6);

        $this->assertSame(DepositStatus::Completed, $deposit->status);
        $this->assertSame($expected, $this->user->fresh()->balance);
        $this->assertSame(1, IncomingTransfer::query()->count());
        $this->assertNotNull($this->method->fresh()->scan_cursor);
    }

    public function test_transfers_to_other_addresses_are_ignored(): void
    {
        $deposit = $this->createDeposit('10');
        $this->fakeTronGrid([['hash' => 'tx-x', 'amount' => $deposit->paid_amount, 'to' => 'TSomeoneElse']]);

        $this->artisan('deposits:scan');

        $this->assertSame(0, IncomingTransfer::query()->count());
        $this->assertSame(DepositStatus::Pending, $deposit->fresh()->status);
    }

    public function test_transfer_reduced_by_exchange_fee_can_be_claimed(): void
    {
        $this->createDeposit('10');
        $this->fakeTronGrid([['hash' => 'tx-fee', 'amount' => '9.5']]);

        $this->artisan('deposits:scan');
        $this->assertSame(TransferStatus::Unmatched, IncomingTransfer::query()->sole()->status);

        app(DepositService::class)->claim($this->user, $this->method, 'tx-fee');

        $this->assertSame('9.975000', $this->user->fresh()->balance); // 9.5 + 5%
    }
}
