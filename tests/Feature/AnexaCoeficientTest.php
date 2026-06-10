<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnexaCoeficientTest extends TestCase
{
    use RefreshDatabase;

    public function test_generarea_anexei_calculeaza_mp_coeficient(): void
    {
        $anexa = $this->genereazaAnexa([
            'suprafata_contractuala_mp' => '161',
        ], [
            [
                'denumire' => 'Apa pluviala / meteorica',
                'tip_calcul' => 'mp_coeficient',
                'coeficient' => '0.09',
                'um' => 'MC',
                'pret_unitar' => '9.74',
                'tva_21' => '11',
                'ordine' => 1,
                'nr_crt' => 1,
            ],
        ]);

        $linie = $anexa->linii->first();

        $this->assertSame('mp_coeficient', $linie->tip_calcul);
        $this->assertEquals(161.0, (float) $linie->index_vechi);
        $this->assertEquals(0.09, (float) $linie->index_nou);
        $this->assertEquals(14.49, (float) $linie->cantitate);
        $this->assertEquals(141.13, round((float) $linie->valoare, 2));
    }

    public function test_generarea_anexei_calculeaza_pe_mp(): void
    {
        $anexa = $this->genereazaAnexa([
            'suprafata_contractuala_mp' => '120.50',
        ], [
            [
                'denumire' => 'Curatenie Spatii Comune / MP',
                'tip_calcul' => 'mp',
                'um' => 'MP',
                'pret_unitar' => '0.97',
                'ordine' => 1,
                'nr_crt' => 1,
            ],
        ]);

        $linie = $anexa->linii->first();

        $this->assertSame('mp', $linie->tip_calcul);
        $this->assertEquals(120.5, (float) $linie->index_vechi);
        $this->assertEquals(120.5, (float) $linie->cantitate);
        $this->assertEquals(116.89, round((float) $linie->valoare, 2));
    }

    public function test_generarea_anexei_calculeaza_pe_persoane_cu_standard(): void
    {
        $anexa = $this->genereazaAnexa([
            'suprafata_contractuala_mp' => '100',
            'persoane_declarate' => null,
        ], [
            [
                'denumire' => 'Servicii Gunoi Menajer',
                'tip_calcul' => 'persoane',
                'um' => 'PERS',
                'pret_unitar' => '49.82',
                'ordine' => 1,
                'nr_crt' => 1,
            ],
        ]);

        $linie = $anexa->linii->first();

        $this->assertSame('persoane', $linie->tip_calcul);
        $this->assertEquals(10, (float) $linie->cantitate);
        $this->assertEquals(498.2, round((float) $linie->valoare, 2));
    }

    public function test_generarea_anexei_calculeaza_pe_persoane_declarate(): void
    {
        $anexa = $this->genereazaAnexa([
            'suprafata_contractuala_mp' => '100',
            'persoane_declarate' => 6,
        ], [
            [
                'denumire' => 'Servicii Gunoi Menajer',
                'tip_calcul' => 'persoane',
                'um' => 'PERS',
                'pret_unitar' => '49.82',
                'ordine' => 1,
                'nr_crt' => 1,
            ],
        ]);

        $linie = $anexa->linii->first();

        $this->assertEquals(6, (float) $linie->cantitate);
        $this->assertEquals(298.92, round((float) $linie->valoare, 2));
    }

    private function genereazaAnexa(array $spatiuAttributes, array $liniiConfigurare): Anexa
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil generare anexa',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa test',
            'implicit' => true,
        ]);

        foreach ($liniiConfigurare as $linieConfigurare) {
            $configurare->linii()->create($linieConfigurare);
        }

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            ...$spatiuAttributes,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-001',
            'chirias' => 'Test SRL',
            'data_start' => '2026-01-01',
            'status' => 'activ',
        ]);

        $this->post('/anexe/generare', ['luna' => '2026-06'])
            ->assertRedirect('/anexe');

        return Anexa::query()->with('linii')->firstOrFail();
    }
}
