<?php

namespace Tests\Unit;

use App\Support\PretGunoiMenajer;
use PHPUnit\Framework\TestCase;

class PretGunoiMenajerTest extends TestCase
{
    public function test_recunoaste_denumirea_serviciului_gunoi_menajer(): void
    {
        $this->assertTrue(PretGunoiMenajer::isGunoiMenajer('Servicii Gunoi Menajer'));
        $this->assertFalse(PretGunoiMenajer::isGunoiMenajer('Curatenie Spatii Comune / pers'));
    }

    public function test_calculeaza_valoarea_cu_pret_suplimentar(): void
    {
        $this->assertSame(51.89, PretGunoiMenajer::valoarePentruPersoane(1, 51.89, 25.0));
        $this->assertSame(76.89, PretGunoiMenajer::valoarePentruPersoane(2, 51.89, 25.0));
        $this->assertSame(101.89, PretGunoiMenajer::valoarePentruPersoane(3, 51.89, 25.0));
    }

    public function test_calculeaza_valoarea_fara_pret_suplimentar(): void
    {
        $this->assertEqualsWithDelta(155.67, PretGunoiMenajer::valoarePentruPersoane(3, 51.89, null), 0.001);
        $this->assertSame(0.0, PretGunoiMenajer::valoarePentruPersoane(0, 51.89, 25.0));
    }
}
