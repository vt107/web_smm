<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\PaymentGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'gateway', 'currency', 'exchange_rate', 'min_amount', 'max_amount', 'bonus_percent',
        'deposit_ttl_minutes', 'instructions', 'config', 'status', 'sort',
    ];

    protected $hidden = ['config'];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => ActiveStatus::class,
            'exchange_rate' => 'decimal:6',
            'min_amount' => 'decimal:6',
            'max_amount' => 'decimal:6',
            'bonus_percent' => 'decimal:2',
            'deposit_ttl_minutes' => 'integer',
            'config' => 'encrypted:array',
            'last_scanned_at' => 'datetime',
        ];
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(IncomingTransfer::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ActiveStatus::Active);
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config ?? [], $key, $default);
    }

    /** Nơi khách chuyển tiền tới: số tài khoản (SePay) hoặc địa chỉ ví (crypto). */
    public function payTo(): string
    {
        return (string) ($this->gateway === PaymentGateway::Sepay
            ? $this->configValue('account_number')
            : $this->configValue('address'));
    }
}
