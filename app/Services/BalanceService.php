<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Cửa duy nhất để thay đổi số dư user. Mỗi thay đổi khoá dòng user và ghi 1 dòng sổ cái
 * với balance_before / balance_after.
 */
class BalanceService
{
    public function __construct(private readonly LevelService $levels) {}

    /**
     * @param  string  $amount  USD, dương = cộng, âm = trừ
     *
     * @throws InsufficientBalanceException
     */
    public function change(
        User|int $user,
        string $amount,
        TransactionType $type,
        ?Model $reference = null,
        ?string $description = null,
        ?User $createdBy = null,
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $type, $reference, $description, $createdBy) {
            /** @var User $locked */
            $locked = User::query()->lockForUpdate()->findOrFail($user instanceof User ? $user->getKey() : $user);

            $before = $locked->balance;
            $after = Money::add($before, $amount);

            if (Money::cmp($after, 0) < 0) {
                throw new InsufficientBalanceException;
            }

            $transaction = $locked->transactions()->create([
                'type' => $type,
                'amount' => Money::add($amount, 0),
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'created_by' => $createdBy?->getKey(),
            ]);

            $locked->balance = $after;

            if ($type->affectsSpent()) {
                // Đơn (amount âm) làm tăng chi tiêu, hoàn tiền (amount dương) làm giảm.
                $spent = Money::sub($locked->total_spent, $amount);
                $locked->total_spent = Money::cmp($spent, 0) < 0 ? '0' : $spent;
            }

            $locked->save();

            if ($type === TransactionType::Order) {
                $this->levels->upgradeIfEligible($locked);
            }

            if ($user instanceof User) {
                $user->setRawAttributes($locked->getAttributes(), true);
                $user->unsetRelation('level');
            }

            return $transaction;
        });
    }
}
