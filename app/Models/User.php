<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Giá trị mặc định (khớp default trong migration) để model mới tạo có sẵn trong bộ nhớ. */
    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'balance' => '0',
        'total_spent' => '0',
        'currency' => 'USD',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'locale' => 'vi',
    ];

    /**
     * balance, total_spent, api_key_* cố ý không nằm trong fillable:
     * số dư chỉ đổi qua BalanceService, API key chỉ qua generateApiKey().
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'currency',
        'timezone',
        'locale',
        'referred_by',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'api_key_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'balance' => 'decimal:6',
            'total_spent' => 'decimal:6',
            'api_key_created_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->level_id ??= Level::default()->id;
            $user->ref_code ??= static::uniqueRefCode();
        });
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function displayCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency', 'code');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** Chỉ admin đang hoạt động được vào trang quản trị Filament. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->isActive();
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** Tạo API key mới (key cũ mất hiệu lực). Trả về key gốc – chỉ hiển thị 1 lần. */
    public function generateApiKey(): string
    {
        $key = Str::random(40);

        $this->forceFill([
            'api_key_hash' => static::hashApiKey($key),
            'api_key_last4' => substr($key, -4),
            'api_key_created_at' => now(),
        ])->save();

        return $key;
    }

    public static function findByApiKey(?string $key): ?self
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        return static::query()->where('api_key_hash', static::hashApiKey($key))->first();
    }

    public static function hashApiKey(string $key): string
    {
        return hash('sha256', $key);
    }

    private static function uniqueRefCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::query()->where('ref_code', $code)->exists());

        return $code;
    }
}
