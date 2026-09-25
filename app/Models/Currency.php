<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public const BASE = 'USD';

    public const CREATED_AT = null;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'symbol', 'symbol_position', 'rate', 'decimals', 'auto_update', 'status'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:6',
            'decimals' => 'integer',
            'auto_update' => 'boolean',
        ];
    }

    /** Quy đổi số USD sang tiền tệ này (chỉ để hiển thị). Làm tròn lên để giá hiển thị không thấp hơn giá thật. */
    public function fromUsd(string|int|float $usd): string
    {
        return Money::ceil(Money::mul($usd, $this->rate, 12), $this->decimals);
    }

    public function format(string|int|float $usd): string
    {
        $value = number_format((float) $this->fromUsd($usd), $this->decimals, '.', ',');

        return $this->symbol_position === 'after' ? "{$value} {$this->symbol}" : "{$this->symbol}{$value}";
    }
}
