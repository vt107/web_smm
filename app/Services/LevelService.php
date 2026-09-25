<?php

namespace App\Services;

use App\Models\Level;
use App\Models\User;

class LevelService
{
    /**
     * Nâng cấp nếu total_spent đạt cấp tự động cao hơn cấp hiện tại. Không bao giờ tự hạ cấp.
     */
    public function upgradeIfEligible(User $user): bool
    {
        $current = $user->level;

        $target = Level::query()
            ->where('auto_upgrade', true)
            ->where('min_spent', '<=', $user->total_spent)
            ->orderByDesc('min_spent')
            ->first();

        if (! $target || ($current && $target->min_spent <= $current->min_spent)) {
            return false;
        }

        $user->level()->associate($target)->save();

        return true;
    }
}
