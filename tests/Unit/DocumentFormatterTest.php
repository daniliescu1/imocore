<?php

namespace Tests\Unit;

use App\Support\DocumentFormatter;
use PHPUnit\Framework\TestCase;

class DocumentFormatterTest extends TestCase
{
    public function test_decimal_limiteaza_la_doua_zecimale(): void
    {
        $this->assertSame('124.37', DocumentFormatter::decimal(124.373399999999996));
        $this->assertSame('380.81', DocumentFormatter::decimal(380.8134));
        $this->assertSame('112.4', DocumentFormatter::decimal(112.399599999999993));
        $this->assertSame('51.89', DocumentFormatter::decimal(51.89));
        $this->assertSame('0.09', DocumentFormatter::decimal(0.09));
    }
}
