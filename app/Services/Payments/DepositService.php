<?php

namespace App\Services\Payments;

use App\Enums\ActiveStatus;
use App\Enums\DepositMatchedBy;
use App\Enums\DepositStatus;
use App\Enums\PaymentGateway;
use App\Enums\TransactionType;
use App\Enums\TransferStatus;
use App\Exceptions\SmmException;
use App\Models\Deposit;
use App\Models\IncomingTransfer;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Services\BalanceService;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Luồng nạp tiền dùng chung cho SePay và crypto:
 * phiếu nạp (deposits) ← khớp → giao dịch tiền vào (incoming_transfers).
 */
class DepositService
{
    public function __construct(private readonly BalanceService $balance) {}

    /**
     * Tạo phiếu nạp. $usdAmount là số USD khách muốn nạp.
     *
     * @throws SmmException
     */
    public function create(User $user, PaymentMethod $method, string $usdAmount): Deposit
    {
        if ($method->status !== ActiveStatus::Active) {
            throw new SmmException('Payment method is not available');
        }

        if (Money::cmp($usdAmount, $method->min_amount) < 0 || Money::cmp($usdAmount, $method->max_amount) > 0) {
            throw new SmmException("Amount must be between {$method->min_amount} and {$method->max_amount} USD");
        }

        $base = Money::mul($usdAmount, $method->exchange_rate);

        return DB::transaction(function () use ($user, $method, $usdAmount, $base) {
            // Khoá kênh nạp để 2 phiếu tạo cùng lúc không bị trùng số tiền crypto.
            PaymentMethod::query()->lockForUpdate()->find($method->id);

            return $user->deposits()->create([
                'payment_method_id' => $method->id,
                'code' => $this->uniqueCode(),
                'requested_amount' => $usdAmount,
                'paid_amount' => $method->gateway === PaymentGateway::Crypto
                    ? $this->uniqueCryptoAmount($method, $base)
                    : Money::ceil($base, 0),
                'paid_currency' => $method->currency,
                'exchange_rate' => $method->exchange_rate,
                'pay_to' => $method->payTo(),
                'status' => DepositStatus::Pending,
                'expires_at' => now()->addMinutes($method->deposit_ttl_minutes),
            ]);
        });
    }

    /**
     * Lưu giao dịch tiền vào. Trùng external_id → trả về bản ghi cũ (không xử lý lại).
     *
     * @param  array{external_id: string, amount: string, transferred_at: CarbonInterface, sender?: ?string, content?: ?string, block_number?: ?int, confirmations?: ?int, raw?: ?array}  $data
     */
    public function recordTransfer(PaymentMethod $method, array $data): IncomingTransfer
    {
        return IncomingTransfer::query()->firstOrCreate(
            ['payment_method_id' => $method->id, 'external_id' => $data['external_id']],
            [
                'amount' => $data['amount'],
                'sender' => $data['sender'] ?? null,
                'content' => isset($data['content']) ? mb_substr($data['content'], 0, 500) : null,
                'block_number' => $data['block_number'] ?? null,
                'confirmations' => $data['confirmations'] ?? null,
                'transferred_at' => $data['transferred_at'],
                'status' => TransferStatus::Unmatched,
                'raw' => $data['raw'] ?? null,
            ],
        );
    }

    /**
     * Tự khớp giao dịch với phiếu nạp.
     * SePay: tìm mã phiếu trong nội dung CK. Crypto: số tiền trùng đúng phiếu (có phần lẻ riêng).
     */
    public function autoMatch(IncomingTransfer $transfer): ?Deposit
    {
        if ($transfer->status !== TransferStatus::Unmatched) {
            return null;
        }

        $method = $transfer->paymentMethod;
        $candidates = $this->claimableDeposits($method, $transfer->transferred_at);

        if ($method->gateway === PaymentGateway::Sepay) {
            $prefix = preg_quote(config('smm.deposits.code_prefix'), '/');

            if (! preg_match("/{$prefix}\d+/i", (string) $transfer->content, $m)) {
                return null;
            }

            $deposit = $candidates->where('code', strtoupper($m[0]))->first();
        } else {
            $matches = $candidates->where('paid_amount', Money::round($transfer->amount))->limit(2)->get();
            $deposit = $matches->count() === 1 ? $matches->first() : null;
        }

        return $deposit ? $this->complete($deposit, $transfer, DepositMatchedBy::Auto) : null;
    }

    /**
     * Khách dán mã giao dịch / tx hash để nhận giao dịch chưa khớp.
     * Điều kiện: có phiếu cùng kênh tạo trước giao dịch, còn trong thời hạn nhận,
     * và số tiền lệch không quá claim_tolerance_percent.
     *
     * @throws SmmException
     */
    public function claim(User $user, PaymentMethod $method, string $externalId): Deposit
    {
        $transfer = $method->incomingTransfers()
            ->where('external_id', trim($externalId))
            ->first();

        if (! $transfer) {
            throw new SmmException('Transaction not found yet, please try again in a few minutes');
        }

        if ($transfer->status !== TransferStatus::Unmatched) {
            throw new SmmException('This transaction has already been used');
        }

        $tolerance = (string) config('smm.deposits.claim_tolerance_percent');

        $deposit = $this->claimableDeposits($method, $transfer->transferred_at)
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->first(function (Deposit $d) use ($transfer, $tolerance) {
                // So với số tiền gốc (không tính phần lẻ nhận diện của crypto).
                $base = Money::mul($d->requested_amount, $d->exchange_rate);
                $diff = Money::abs(Money::sub($transfer->amount, $base));

                return Money::cmp($diff, Money::mul($base, Money::div($tolerance, 100, 12))) <= 0;
            });

        if (! $deposit) {
            throw new SmmException('No matching deposit request found for this transaction');
        }

        return $this->complete($deposit, $transfer, DepositMatchedBy::UserClaim);
    }

    /** Admin gán giao dịch chưa khớp cho 1 user: tạo phiếu mới và cộng tiền. */
    public function assignToUser(IncomingTransfer $transfer, User $user): Deposit
    {
        $method = $transfer->paymentMethod;

        $deposit = $user->deposits()->create([
            'payment_method_id' => $method->id,
            'code' => $this->uniqueCode(),
            'requested_amount' => Money::div($transfer->amount, $method->exchange_rate),
            'paid_amount' => Money::round($transfer->amount),
            'paid_currency' => $method->currency,
            'exchange_rate' => $method->exchange_rate,
            'pay_to' => $method->payTo(),
            'status' => DepositStatus::Pending,
            'expires_at' => now(),
        ]);

        return $this->complete($deposit, $transfer, DepositMatchedBy::Admin);
    }

    /**
     * Khớp phiếu + giao dịch và cộng tiền theo số THỰC NHẬN (quy đổi USD) + bonus.
     *
     * @throws SmmException
     */
    public function complete(Deposit $deposit, IncomingTransfer $transfer, DepositMatchedBy $by): Deposit
    {
        return DB::transaction(function () use ($deposit, $transfer, $by) {
            /** @var Deposit $deposit */
            $deposit = Deposit::query()->lockForUpdate()->findOrFail($deposit->id);
            /** @var IncomingTransfer $transfer */
            $transfer = IncomingTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($deposit->status === DepositStatus::Completed || $transfer->status !== TransferStatus::Unmatched) {
                throw new SmmException('Deposit or transaction has already been processed');
            }

            $method = $deposit->paymentMethod;
            $received = Money::round($transfer->amount);
            $amount = Money::div($received, $deposit->exchange_rate);
            $bonus = Money::mul($amount, Money::div($method->bonus_percent, 100, 12));

            $deposit->fill([
                'received_amount' => $received,
                'amount' => $amount,
                'bonus' => $bonus,
                'status' => DepositStatus::Completed,
                'matched_by' => $by,
                'paid_at' => $transfer->transferred_at,
            ])->save();

            $transfer->fill(['status' => TransferStatus::Matched, 'deposit_id' => $deposit->id])->save();

            $this->balance->change($deposit->user_id, $amount, TransactionType::Deposit, $deposit, "Deposit #{$deposit->code} via {$method->name}");

            if (Money::isPositive($bonus)) {
                $this->balance->change($deposit->user_id, $bonus, TransactionType::DepositBonus, $deposit, "Bonus {$method->bonus_percent}% deposit #{$deposit->code}");
            }

            return $deposit;
        });
    }

    /** Chuyển phiếu quá hạn sang expired. Phiếu expired vẫn khớp được trong deposit_claim_window_hours. */
    public function expireOverdue(): int
    {
        return Deposit::query()
            ->where('status', DepositStatus::Pending)
            ->where('expires_at', '<', now())
            ->update(['status' => DepositStatus::Expired, 'updated_at' => now()]);
    }

    /** Phiếu có thể khớp với 1 giao dịch: tạo trước giao dịch, còn pending hoặc mới hết hạn trong cửa sổ cho phép. */
    private function claimableDeposits(PaymentMethod $method, CarbonInterface $transferredAt): Builder
    {
        $windowHours = (int) Setting::get('deposit_claim_window_hours');

        return Deposit::query()
            ->where('payment_method_id', $method->id)
            ->whereIn('status', [DepositStatus::Pending, DepositStatus::Expired])
            // +5 phút phòng lệch giờ giữa ngân hàng/blockchain và server.
            ->where('created_at', '<=', $transferredAt->copy()->addMinutes(5))
            ->where('expires_at', '>=', $transferredAt->copy()->subHours($windowHours));
    }

    private function uniqueCode(): string
    {
        do {
            $code = config('smm.deposits.code_prefix').random_int(1000000, 9999999);
        } while (Deposit::query()->where('code', $code)->exists());

        return $code;
    }

    /** Cộng phần lẻ 0.001–0.999 không trùng với phiếu crypto nào đang chờ của cùng kênh. */
    private function uniqueCryptoAmount(PaymentMethod $method, string $base): string
    {
        $windowHours = (int) Setting::get('deposit_claim_window_hours');

        $taken = Deposit::query()
            ->where('payment_method_id', $method->id)
            ->whereIn('status', [DepositStatus::Pending, DepositStatus::Expired])
            ->where('expires_at', '>=', now()->subHours($windowHours))
            ->pluck('paid_amount')
            ->map(fn ($v) => Money::round($v))
            ->flip();

        $max = (int) config('smm.deposits.crypto_fraction_max');

        for ($i = 0; $i < 50; $i++) {
            $amount = Money::add($base, Money::div(random_int(1, $max), 1000));

            if (! $taken->has($amount)) {
                return $amount;
            }
        }

        throw new SmmException('Too many pending deposits, please try again later');
    }
}
