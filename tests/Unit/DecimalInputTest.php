<?php

namespace Tests\Unit;

use App\Support\DecimalInput;
use PHPUnit\Framework\TestCase;

class DecimalInputTest extends TestCase
{
    public function test_normalize_accepts_comma_as_decimal_separator(): void
    {
        $this->assertSame('598.31', DecimalInput::normalize('598,31'));
        $this->assertSame('1407.50', DecimalInput::normalize('1407,50'));
    }

    public function test_normalize_accepts_dot_as_decimal_separator(): void
    {
        $this->assertSame('598.31', DecimalInput::normalize('598.31'));
    }

    public function test_normalize_when_both_separators_dot_is_decimal(): void
    {
        $this->assertSame('3123.00', DecimalInput::normalize('3,123.00'));
        $this->assertSame('1234.56', DecimalInput::normalize('1,234.56'));
    }

    public function test_normalize_handles_empty_values(): void
    {
        $this->assertNull(DecimalInput::normalize(null));
        $this->assertNull(DecimalInput::normalize(''));
        $this->assertNull(DecimalInput::normalize('   '));
    }
}
