<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Level extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'min_spent', 'discount_percent', 'auto_upgrade', 'is_default', 'sort', 'color'];

    protected function casts(): array
    {
        return [
            'min_spent' => 'decimal:6',
            'discount_percent' => 'decimal:2',
            'auto_upgrade' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Chỉ 1 cấp được đánh dấu mặc định.
        static::saved(function (Level $level) {
            if ($level->is_default) {
                static::query()->whereKeyNot($level->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Cấp gán cho user mới; không có cấp đánh dấu default thì lấy cấp thấp nhất. */
    public static function default(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('min_spent')->firstOrFail();
    }
}
