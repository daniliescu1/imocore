<?php

namespace Tests\Unit;

use App\Support\TipCalculAnexa;
use PHPUnit\Framework\TestCase;

class TipCalculAnexaTest extends TestCase
{
    public function test_identifica_pausal_apa_si_canalizare_pe_persoana(): void
    {
        $this->assertTrue(TipCalculAnexa::isPausalApaCanalizarePePersoana('pausal', 'Consum apa - mc / pers'));
        $this->assertTrue(TipCalculAnexa::isPausalApaCanalizarePePersoana('Pausal', 'Canalizare mc / pers'));
    }

    public function test_nu_identifica_alte_pausale_ca_apa_sau_canalizare_pe_persoana(): void
    {
        $this->assertFalse(TipCalculAnexa::isPausalApaCanalizarePePersoana('pausal', 'Gunoi menajer'));
        $this->assertFalse(TipCalculAnexa::isPausalApaCanalizarePePersoana('pausal', 'Apă pausal'));
        $this->assertFalse(TipCalculAnexa::isPausalApaCanalizarePePersoana('contor configurabil', 'Consum apa - mc / pers'));
    }

    public function test_identifica_contor_fix(): void
    {
        $this->assertTrue(TipCalculAnexa::isContorFix('Contor Fix'));
        $this->assertTrue(TipCalculAnexa::isContorFix('contor fix'));
        $this->assertTrue(TipCalculAnexa::isCitire('Contor Fix'));
        $this->assertFalse(TipCalculAnexa::isContorFix('Contor'));
        $this->assertFalse(TipCalculAnexa::isContorFix('fix'));
    }
}
