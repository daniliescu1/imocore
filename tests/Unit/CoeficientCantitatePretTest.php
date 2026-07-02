<?php

namespace Tests\Unit;

use App\Support\CoeficientCantitatePret;
use PHPUnit\Framework\TestCase;

class CoeficientCantitatePretTest extends TestCase
{
    public function test_salveaza_procentul_ui_ca_multiplicator(): void
    {
        $this->assertSame('1.2', CoeficientCantitatePret::normalizeForSave('120'));
        $this->assertSame('0.2', CoeficientCantitatePret::normalizeForSave('20'));
        $this->assertSame('1', CoeficientCantitatePret::normalizeForSave('100'));
    }

    public function test_citeste_multiplicatorul_din_baza_fara_reconvertire(): void
    {
        $this->assertSame(1.2, CoeficientCantitatePret::toMultiplier('1.2'));
        $this->assertSame(0.2, CoeficientCantitatePret::toMultiplier('0.2'));
        $this->assertSame(1.0, CoeficientCantitatePret::toMultiplier('1'));
    }

    public function test_afiseaza_procentul_in_formular(): void
    {
        $this->assertSame('120', CoeficientCantitatePret::toPercentForForm('1.2'));
        $this->assertSame('100', CoeficientCantitatePret::toPercentForForm('1'));
    }
}
