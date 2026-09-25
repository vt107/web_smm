<?php

namespace Tests\Feature\Payments;

use App\Enums\DepositMatchedBy;
use App\Enums\DepositStatus;
use App\Enums\TransferStatus;
use App\Exceptions\SmmException;
use App\Models\Deposit;
use App\Models\IncomingTransfer;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\Payments\DepositService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SepayDepositTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private PaymentMethod $method;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->method = PaymentMethod::factory()->sepay()->create(); // 25 000 VND = 1 USD
    }

    private function webhook(array $payload, string $key = 'sepay-test-key'): TestResponse
    {
        static $id = 1000;

        $payload += ['id' => ++$id];

        return $this->postJson("/api/webhooks/sepay/{$this->method->id}", $payload + [
            'gateway' => 'MBBank',
            'transactionDate' => now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'),
            'accountNumber' => '0123456789',
            'transferType' => 'in',
            'referenceCode' => 'FT'.$payload['id'],
        ], ['Authorization' => "Apikey {$key}"]);
    }

    private function createDeposit(string $usd = '10'): Deposit
    {
        return app(DepositService::class)->create($this->user, $this->method, $usd);
    }

    public function test_deposit_amount_is_converted_to_vnd(): void
    {
        $deposit = $this->createDeposit('10.5');

        $this->assertSame('262500.000000', $deposit->paid_amount);
        $this->assertSame('0123456789', $deposit->pay_to);
        $this->assertStringStartsWith('NAP', $deposit->code);
    }

    public function test_webhook_rejects_invalid_api_key(): void
    {
        $this->webhook(['transferAmount' => 1000, 'content' => 'x'], 'wrong-key')->assertUnauthorized();

        $this->assertSame(0, IncomingTransfer::query()->count());
    }

    public function test_webhook_matches_deposit_code_and_is_idempotent(): void
    {
        $deposit = $this->createDeposit('10');

        $payload = ['id' => 555, 'transferAmount' => 250000, 'content' => strtolower($deposit->code).' chuyen tien'];
        $this->webhook($payload)->assertOk()->assertJson(['success' => true]);
        $this->webhook($payload)->assertOk();

        $deposit->refresh();
        $this->assertSame(DepositStatus::Completed, $deposit->status);
        $this->assertSame(DepositMatchedBy::Auto, $deposit->matched_by);
        $this->assertSame('10.000000', $deposit->amount);
        $this->assertSame('10.000000', $this->user->fresh()->balance);
        $this->assertSame(1, IncomingTransfer::query()->count());
    }

    public function test_underpaid_transfer_credits_actual_amount(): void
    {
        $deposit = $this->createDeposit('10');

        $this->webhook(['transferAmount' => 125000, 'content' => $deposit->code]);

        $this->assertSame('5.000000', $this->user->fresh()->balance);
    }

    public function test_outgoing_transfer_is_ignored(): void
    {
        $this->webhook(['transferAmount' => 250000, 'content' => 'x', 'transferType' => 'out'])->assertOk();

        $this->assertSame(0, IncomingTransfer::query()->count());
    }

    public function test_transfer_without_code_can_be_claimed_once(): void
    {
        $this->createDeposit('10');
        $this->webhook(['id' => 777, 'transferAmount' => 250000, 'content' => 'chuyen tien nap smm']);

        $transfer = IncomingTransfer::query()->sole();
        $this->assertSame(TransferStatus::Unmatched, $transfer->status);

        // Khách nhập mã tham chiếu trên biên lai ngân hàng (không phải id nội bộ của SePay).
        $this->expectsClaimError('777', 'Transaction not found yet, please try again in a few minutes');
        $deposit = app(DepositService::class)->claim($this->user, $this->method, 'FT777');

        $this->assertSame(DepositMatchedBy::UserClaim, $deposit->matched_by);
        $this->assertSame('10.000000', $this->user->fresh()->balance);

        $this->expectExceptionMessage('This transaction has already been used');
        app(DepositService::class)->claim($this->user, $this->method, 'FT777');
    }

    private function expectsClaimError(string $reference, string $message): void
    {
        try {
            app(DepositService::class)->claim($this->user, $this->method, $reference);
            $this->fail('Claim should have failed');
        } catch (SmmException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_claim_is_rejected_when_amount_differs_too_much(): void
    {
        $this->createDeposit('10');
        $this->webhook(['id' => 778, 'transferAmount' => 200000, 'content' => 'no code']); // lệch 20%

        $this->expectException(SmmException::class);
        $this->expectExceptionMessage('No matching deposit request found for this transaction');

        app(DepositService::class)->claim($this->user, $this->method, 'FT778');
    }

    public function test_other_user_cannot_claim_transfer(): void
    {
        $this->createDeposit('10');
        $this->webhook(['id' => 779, 'transferAmount' => 250000, 'content' => 'no code']);

        $this->expectExceptionMessage('No matching deposit request found for this transaction');

        app(DepositService::class)->claim(User::factory()->create(), $this->method, 'FT779');
    }
}
