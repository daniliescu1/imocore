<?php

namespace Tests\Unit;

use App\Models\Spatiu;
use PHPUnit\Framework\TestCase;

class SpatiuPersoaneStandardTest extends TestCase
{
    public function test_rotunjeste_intotdeauna_in_sus(): void
    {
        $this->assertSame(0, Spatiu::calculeazaPersoaneStandard(0));
        $this->assertSame(1, Spatiu::calculeazaPersoaneStandard(9.18));
        $this->assertSame(1, Spatiu::calculeazaPersoaneStandard('5.5'));
        $this->assertSame(1, Spatiu::calculeazaPersoaneStandard(10));
        $this->assertSame(3, Spatiu::calculeazaPersoaneStandard(25));
        $this->assertSame(3, Spatiu::calculeazaPersoaneStandard(29.9));
    }
}
