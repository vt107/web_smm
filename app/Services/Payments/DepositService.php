<?php

namespace App\Services\Payments;

use App\Enums\ActiveStatus;
use App\Enums\DepositMatchedBy;
use App\Enums\DepositStatus;
use App\Enums\PaymentGateway;
use App\Enums\TelegramEvent;
use App\Enums\TransactionType;
use App\Enums\TransferStatus;
use App\Exceptions\SmmException;
use App\Models\Deposit;
use App\Models\IncomingTransfer;
use App\Models\PaymentMethod;
use App\Models\Setting;
use App\Models\User;
use App\Services\BalanceService;
use App\Services\TelegramNotifier;
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
    public function __construct(
        private readonly BalanceService $balance,
        private readonly TelegramNotifier $telegram,
    ) {}

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
            throw new SmmException('Amount must be between :min and :max USD', [
                'min' => Money::format($method->min_amount),
                'max' => Money::format($method->max_amount),
            ]);
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

            $deposit = preg_match("/{$prefix}\d+/i", (string) $transfer->content, $m)
                ? $candidates->where('code', strtoupper($m[0]))->first()
                : null;
        } else {
            $matches = $candidates->where('paid_amount', Money::round($transfer->amount))->limit(2)->get();
            $deposit = $matches->count() === 1 ? $matches->first() : null;
        }

        if (! $deposit) {
            $this->notifyUnmatched($transfer);

            return null;
        }

        return $this->complete($deposit, $transfer, DepositMatchedBy::Auto);
    }

    /**
     * Khách nhận giao dịch SePay chưa khớp bằng MÃ THAM CHIẾU NGÂN HÀNG (vd FT23…) – mã chỉ người chuyển thấy
     * trên biên lai (không dùng id nội bộ của SePay vì là số tăng dần, đoán được).
     * Điều kiện: có phiếu cùng kênh tạo trước giao dịch, còn trong thời hạn, lệch ≤ claim_tolerance_percent.
     *
     * @throws SmmException
     */
    public function claim(User $user, PaymentMethod $method, string $reference): Deposit
    {
        if ($method->gateway !== PaymentGateway::Sepay) {
            throw new SmmException('Incorrect request');
        }

        $transfer = $method->incomingTransfers()
            ->where('raw->referenceCode', trim($reference))
            ->first();

        if (! $transfer) {
            throw new SmmException('Transaction not found yet, please try again in a few minutes');
        }

        if ($transfer->status !== TransferStatus::Unmatched) {
            throw new SmmException('This transaction has already been used');
        }

        $deposit = $this->claimableDepositFor($user, $transfer);

        if (! $deposit) {
            throw new SmmException('No matching deposit request found for this transaction');
        }

        return $this->complete($deposit, $transfer, DepositMatchedBy::UserClaim);
    }

    /**
     * Khách xin nhận giao dịch crypto chưa khớp. Tx hash công khai trên blockchain nên KHÔNG tự cộng tiền:
     * đánh dấu người xin nhận và báo admin duyệt (Incoming transfers → Approve claim).
     *
     * @throws SmmException
     */
    public function requestCryptoClaim(User $user, PaymentMethod $method, string $txHash): IncomingTransfer
    {
        if ($method->gateway !== PaymentGateway::Crypto) {
            throw new SmmException('Incorrect request');
        }

        return DB::transaction(function () use ($user, $method, $txHash) {
            $transfer = $method->incomingTransfers()->where('external_id', trim($txHash))->lockForUpdate()->first();

            if (! $transfer) {
                throw new SmmException('Transaction not found yet, please try again in a few minutes');
            }

            if ($transfer->status !== TransferStatus::Unmatched) {
                throw new SmmException('This transaction has already been used');
            }

            if ($transfer->claimed_by !== null) {
                throw new SmmException('This transaction is already under review');
            }

            if (! $this->claimableDepositFor($user, $transfer)) {
                throw new SmmException('No matching deposit request found for this transaction');
            }

            $transfer->forceFill(['claimed_by' => $user->id, 'claimed_at' => now()])->save();

            $this->telegram->notify(TelegramEvent::UnmatchedTransfer, implode("\n", [
                '🔎 <b>Crypto claim needs review</b>',
                'Customer: '.e($user->username),
                'Amount: '.Money::format($transfer->amount).' '.e($method->currency).' via '.e($method->name),
                'Tx: '.e($transfer->external_id),
                'Check the sender, then approve or reject: '.url('/admin/incoming-transfers'),
            ]));

            return $transfer;
        });
    }

    /** Admin duyệt yêu cầu nhận giao dịch crypto của khách. */
    public function approveClaim(IncomingTransfer $transfer): Deposit
    {
        $claimant = $transfer->claimant ?? throw new SmmException('Incorrect request');

        $deposit = $this->claimableDepositFor($claimant, $transfer);

        return $deposit
            ? $this->complete($deposit, $transfer, DepositMatchedBy::Admin)
            : $this->assignToUser($transfer, $claimant);
    }

    public function rejectClaim(IncomingTransfer $transfer): void
    {
        $transfer->forceFill(['claimed_by' => null, 'claimed_at' => null])->save();
    }

    /** Phiếu của khách có thể khớp giao dịch (so với số tiền gốc, không tính phần lẻ nhận diện của crypto). */
    private function claimableDepositFor(User $user, IncomingTransfer $transfer): ?Deposit
    {
        $tolerance = (string) config('smm.deposits.claim_tolerance_percent');

        return $this->claimableDeposits($transfer->paymentMethod, $transfer->transferred_at)
            ->where('user_id', $user->id)
            ->latest('id')
            ->get()
            ->first(function (Deposit $d) use ($transfer, $tolerance) {
                $base = Money::mul($d->requested_amount, $d->exchange_rate);
                $diff = Money::abs(Money::sub($transfer->amount, $base));

                return Money::cmp($diff, Money::mul($base, Money::div($tolerance, 100, 12))) <= 0;
            });
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

            $this->telegram->notify(TelegramEvent::DepositCompleted, implode("\n", array_filter([
                '💰 <b>Deposit received</b>',
                'Customer: '.e($deposit->user->username),
                'Amount: $'.Money::format($amount).(Money::isPositive($bonus) ? ' (+$'.Money::format($bonus).' bonus)' : ''),
                'Paid: '.Money::format($received).' '.e($deposit->paid_currency).' via '.e($method->name),
                'Code: '.e($deposit->code).($by === DepositMatchedBy::Auto ? '' : ' · matched by '.$by->getLabel()),
            ])));

            return $deposit;
        });
    }

    private function notifyUnmatched(IncomingTransfer $transfer): void
    {
        $method = $transfer->paymentMethod;

        $this->telegram->notify(TelegramEvent::UnmatchedTransfer, implode("\n", array_filter([
            '⚠️ <b>Payment not matched to any deposit</b>',
            'Amount: '.Money::format($transfer->amount).' '.e($method->currency).' via '.e($method->name),
            $transfer->content ? 'Description: '.e(mb_strimwidth($transfer->content, 0, 120, '…')) : null,
            'Transaction: '.e($transfer->external_id),
            'Assign it to a customer: '.url('/admin/incoming-transfers'),
        ])), dedupeKey: "transfer:{$transfer->id}", dedupeMinutes: 60 * 24 * 30);
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
