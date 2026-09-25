<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_multiplication_rounds_half_up(): void
    {
        $this->assertSame('0.000002', Money::mul('0.0000015', 1));
        $this->assertSame('1.900000', Money::mul('2', '0.95'));
    }

    public function test_division_rounds_half_up(): void
    {
        $this->assertSame('0.666667', Money::div(2, 3));
        $this->assertSame('-0.666667', Money::div(-2, 3));
    }

    public function test_ceil_rounds_up_to_scale(): void
    {
        $this->assertSame('250001', Money::ceil('250000.01'));
        $this->assertSame('250000', Money::ceil('250000.000000'));
        $this->assertSame('1.24', Money::ceil('1.231', 2));
    }

    public function test_format_keeps_two_decimals_minimum(): void
    {
        $this->assertSame('0.90', Money::format('0.900000'));
        $this->assertSame('0.00123', Money::format('0.001230'));
        $this->assertSame('100.00', Money::format(100));
    }

    public function test_floats_are_normalized(): void
    {
        $this->assertSame('0.000010', Money::add(1.0E-5, 0));
    }
}
