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

    /** @param  bool  $roundUp  làm tròn lên (giá) hay làm tròn thường (số dư, tiền đã trừ) */
    public function format(string|int|float $usd, bool $roundUp = true): string
    {
        $converted = $roundUp
            ? $this->fromUsd($usd)
            : Money::round(Money::mul($usd, $this->rate, 12), $this->decimals);

        [$thousands, $decimal] = $this->code === 'VND' ? ['.', ','] : [',', '.'];
        $value = number_format((float) $converted, $this->decimals, $decimal, $thousands);

        // Bỏ số 0 thừa ở cuối nhưng giữ tối thiểu 2 chữ số lẻ: $1.0000 → $1.00, $0.0012 giữ nguyên.
        if ($this->decimals > 2) {
            $value = preg_replace('/(\\'.$decimal.'\\d{2}\\d*?)0+$/', '$1', $value);
        }

        return $this->symbol_position === 'after' ? "{$value} {$this->symbol}" : "{$this->symbol}{$value}";
    }
}
