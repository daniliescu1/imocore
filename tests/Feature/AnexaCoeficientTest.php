<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\CitireContor;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\ContorConfigurabilSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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

    public function test_generarea_anexei_calculeaza_tipul_vechi_mp_coeficient(): void
    {
        $anexa = $this->genereazaAnexa([
            'suprafata_contractuala_mp' => '156',
        ], [
            [
                'denumire' => 'Apa pluviala meteorica',
                'tip_calcul' => 'mp*coeficient',
                'um' => 'MC',
                'pret_unitar' => '9.74',
                'tva_21' => '11',
                'ordine' => 1,
                'nr_crt' => 1,
            ],
        ]);

        $linie = $anexa->linii->first();

        $this->assertSame('mp*coeficient', $linie->tip_calcul);
        $this->assertEquals(156, (float) $linie->index_vechi);
        $this->assertEquals(0.09, (float) $linie->index_nou);
        $this->assertEquals(14.04, (float) $linie->cantitate);
        $this->assertEquals(136.75, round((float) $linie->valoare, 2));
    }

    public function test_generarea_anexei_ignora_index_nou_vechi_cand_nu_este_coeficient(): void
    {
        $anexa = $this->genereazaAnexa([
            'suprafata_contractuala_mp' => '156',
        ], [
            [
                'denumire' => 'Apa pluviala meteorica',
                'tip_calcul' => 'mp*coeficient',
                'index_vechi' => '156',
                'index_nou' => '156',
                'facturat' => '24336',
                'coeficient' => '156',
                'um' => 'MC',
                'pret_unitar' => '9.74',
                'tva_21' => '11',
                'ordine' => 1,
                'nr_crt' => 1,
            ],
        ]);

        $linie = $anexa->linii->first();

        $this->assertEquals(156, (float) $linie->index_vechi);
        $this->assertEquals(0.09, (float) $linie->index_nou);
        $this->assertEquals(14.04, (float) $linie->cantitate);
        $this->assertEquals(136.75, round((float) $linie->valoare, 2));
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
        $this->assertNull($linie->index_vechi);
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
        $this->assertNull($linie->index_vechi);
        $this->assertNull($linie->index_nou);
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

    public function test_generarea_anexei_foloseste_citirile_contoare_nu_valorile_din_configurare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil contor',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa contor',
            'implicit' => true,
        ]);

        $linieConfig = $configurare->linii()->create([
            'denumire' => 'Consum apa - mc / pers',
            'tip_calcul' => 'contor',
            'index_vechi' => '1303',
            'index_nou' => '1327',
            'facturat' => '24',
            'um' => 'MC',
            'pret_unitar' => '9.74',
            'tva_21' => '11',
            'ordine' => 1,
            'nr_crt' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        CitireContor::query()->create([
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linieConfig->id,
            'luna' => '2026-05',
            'index_vechi' => 1303,
            'index_nou' => 1327,
            'consum' => 24,
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

        $linie = Anexa::query()->with('linii')->firstOrFail()->linii->first();

        $this->assertSame('contor', $linie->tip_calcul);
        $this->assertEquals(1303, (float) $linie->index_vechi);
        $this->assertEquals(1327, (float) $linie->index_nou);
        $this->assertEquals(24, (float) $linie->cantitate);
        $this->assertEquals(233.76, round((float) $linie->valoare, 2));
    }

    public function test_generarea_anexei_foloseste_citirile_pausal(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil pausal',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa pausal',
            'implicit' => true,
        ]);

        $linieConfig = $configurare->linii()->create([
            'denumire' => 'Servicii Gunoi Menajer',
            'tip_calcul' => 'pausal',
            'um' => 'Pers',
            'pret_unitar' => '9.74',
            'tva_21' => '21',
            'ordine' => 1,
            'nr_crt' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare);

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieConfig->id,
            'luna' => '2026-05',
            'consum' => 4,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-002',
            'chirias' => 'Test Pausal SRL',
            'data_start' => '2026-01-01',
            'status' => 'activ',
        ]);

        $this->post('/anexe/generare', ['luna' => '2026-06'])
            ->assertRedirect('/anexe');

        $linie = Anexa::query()->with('linii')->firstOrFail()->linii->first();

        $this->assertSame('pausal', $linie->tip_calcul);
        $this->assertNull($linie->index_vechi);
        $this->assertNull($linie->index_nou);
        $this->assertEquals(4, (float) $linie->cantitate);
        $this->assertEquals(38.96, round((float) $linie->valoare, 2));
    }

    public function test_generarea_anexei_poate_fi_limitata_la_un_imobil(): void
    {
        $imobilSelectat = $this->creeazaImobilEligibil('Imobil selectat', 'A1');
        $imobilNeselectat = $this->creeazaImobilEligibil('Imobil neselectat', 'B1');

        $this->post('/anexe/generare', [
            'luna' => '2026-06',
            'imobil_id' => $imobilSelectat->id,
        ])->assertRedirect(route('anexe.imobil', $imobilSelectat));

        $anexe = Anexa::query()->with('contract.spatiu')->get();

        $this->assertCount(1, $anexe);
        $this->assertSame($imobilSelectat->id, $anexe->first()->contract->spatiu->imobil_id);
        $this->assertNotSame($imobilNeselectat->id, $anexe->first()->contract->spatiu->imobil_id);
    }

    public function test_pagina_anexe_imobil_afiseaza_doar_anexele_imobilului(): void
    {
        $imobilSelectat = $this->creeazaImobilEligibil('Imobil anexe dedicat', 'A1');
        $this->creeazaImobilEligibil('Imobil anexe ascuns', 'B1');

        $this->post('/anexe/generare', ['luna' => '2026-06'])
            ->assertRedirect('/anexe');

        $this->assertCount(2, Anexa::query()->get());

        $this->get(route('anexe.imobil', $imobilSelectat))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anexe/Imobil')
                ->where('imobil.id', $imobilSelectat->id)
                ->where('anexe.0.imobil', 'Imobil anexe dedicat')
                ->has('anexe', 1)
            );
    }

    public function test_generarea_din_pagina_imobilului_redirecteaza_inapoi_la_imobil(): void
    {
        $imobil = $this->creeazaImobilEligibil('Imobil generare dedicata', 'A1');

        $this->post('/anexe/generare', [
            'luna' => '2026-06',
            'imobil_id' => $imobil->id,
        ])->assertRedirect(route('anexe.imobil', $imobil));

        $this->assertCount(1, Anexa::query()->get());
    }

    public function test_stergerea_anexei_din_pagina_imobilului_ramane_pe_imobil(): void
    {
        $imobil = $this->creeazaImobilEligibil('Imobil stergere anexa', 'A1');

        $this->post('/anexe/generare', [
            'luna' => '2026-06',
            'imobil_id' => $imobil->id,
        ])->assertRedirect(route('anexe.imobil', $imobil));

        $anexa = Anexa::query()->firstOrFail();

        $this->delete(route('anexe.destroy', $anexa))
            ->assertRedirect(route('anexe.imobil', $imobil));

        $this->assertDatabaseMissing('anexe', ['id' => $anexa->id]);

        $this->get(route('anexe.imobil', $imobil))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anexe/Imobil')
                ->has('anexe', 0)
            );
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

    private function creeazaImobilEligibil(string $nume, string $identificator): Imobil
    {
        $imobil = Imobil::query()->create([
            'nume' => $nume,
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => "Anexa {$nume}",
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Energie Electrica',
            'tip_calcul' => 'manual',
            'um' => 'KW',
            'valoare' => 100,
            'ordine' => 1,
            'nr_crt' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => $identificator,
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => "C-{$identificator}",
            'chirias' => 'Test SRL',
            'data_start' => '2026-01-01',
            'status' => 'activ',
        ]);

        return $imobil;
    }
}
