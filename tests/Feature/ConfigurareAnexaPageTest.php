<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\ServiciuStandardAnexa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConfigurareAnexaPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagina_configurare_anexa_incarca_imobilul_selectat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil configurare anexa',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa test',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Energie electrica',
            'um' => 'KW',
            'pret_unitar' => '1.5280',
            'tva_21' => '21',
            'tip_calcul' => 'contor',
        ]);

        $this->get(route('configurare-anexa.index', ['imobil_id' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareAnexa/Index')
                ->where('selectedImobilId', $imobil->id)
                ->where('anexe.0.denumire', 'Anexa test')
                ->where('anexe.0.imobil', 'Imobil configurare anexa')
                ->where('anexe.0.linii_count', 1)
            );

        $this->get(route('configurare-anexa.edit', $configurare))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareAnexa/Form')
                ->where('anexa.denumire', 'Anexa test')
                ->where('anexa.linii.0.denumire', 'Energie electrica')
            );
    }

    public function test_configurarea_anexei_pastreaza_headerul_de_zona(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil header anexa',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa cu zone',
            'implicit' => true,
        ]);

        $this->put(route('configurare-anexa.update', $configurare), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa cu zone',
            'implicit' => true,
            'linii' => [
                [
                    'denumire' => 'Energie electrica',
                    'nr_crt' => 1,
                    'um' => 'KW',
                    'pret_unitar' => '1.391',
                    'tva_21' => '21',
                    'tip_calcul' => 'contor',
                ],
                [
                    'tip_linie' => 'header',
                ],
                [
                    'denumire' => 'Curatenie',
                    'nr_crt' => 1,
                    'um' => 'MP',
                    'pret_unitar' => '2.97',
                    'tva_21' => '11',
                    'tip_calcul' => 'pe_mp',
                ],
            ],
        ])->assertRedirect(route('configurare-anexa.edit', $configurare));

        $configurare->refresh()->load('linii');

        $this->assertCount(3, $configurare->linii);
        $this->assertSame('header', $configurare->linii[1]->tip_linie);
        $this->assertSame('Energie electrica', $configurare->linii[0]->denumire);
        $this->assertSame(1, $configurare->linii[0]->nr_crt);
        $this->assertSame('Curatenie', $configurare->linii[2]->denumire);
        $this->assertSame(1, $configurare->linii[2]->nr_crt);
    }

    public function test_configurarea_anexei_pastreaza_randul_cu_coeficient(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil coeficient anexa',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa coeficient',
            'implicit' => true,
        ]);

        $this->put(route('configurare-anexa.update', $configurare), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa coeficient',
            'implicit' => true,
            'linii' => [
                [
                    'denumire' => 'Apa pluviala / meteorica',
                    'tip_calcul' => 'mp_coeficient',
                    'coeficient' => '0.09',
                    'um' => 'MC',
                    'pret_unitar' => '9.74',
                    'tva_21' => '11',
                ],
            ],
        ])->assertRedirect(route('configurare-anexa.edit', $configurare));

        $linie = $configurare->refresh()->linii->first();

        $this->assertSame('mp_coeficient', $linie->tip_calcul);
        $this->assertEquals(0.09, (float) $linie->coeficient);
        $this->assertSame('Apa pluviala / meteorica', $linie->denumire);
    }

    public function test_configurarea_anexei_pastreaza_randul_coeficient_impreuna_cu_celelalte_linii(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil coeficient mix',
            'strada' => 'Strada Test',
            'numar' => '4',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa mix',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Consum Apa',
            'tip_calcul' => 'contor',
            'um' => 'MC',
            'pret_unitar' => '9.74',
            'ordine' => 1,
            'nr_crt' => 1,
        ]);

        $this->put(route('configurare-anexa.update', $configurare), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa mix',
            'implicit' => true,
            'linii' => [
                [
                    'denumire' => 'Consum Apa',
                    'tip_calcul' => 'contor',
                    'um' => 'MC',
                    'pret_unitar' => '9.74',
                    'nr_crt' => 1,
                ],
                [
                    'denumire' => 'Apa pluviala / meteorica',
                    'tip_calcul' => 'mp_coeficient',
                    'coeficient' => '0.09',
                    'um' => 'MC',
                    'pret_unitar' => '9.74',
                    'tva_21' => '11',
                    'nr_crt' => 2,
                ],
            ],
        ])->assertRedirect(route('configurare-anexa.edit', $configurare));

        $configurare->refresh()->load('linii');

        $this->assertCount(2, $configurare->linii);
        $this->assertSame('mp_coeficient', $configurare->linii[1]->tip_calcul);
        $this->assertEquals(0.09, (float) $configurare->linii[1]->coeficient);
    }

    public function test_salvarea_tva_11_procente_functioneaza_chiar_daca_valoarea_standard_contine_procent(): void
    {
        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_TVA,
            'valoare' => '11%',
            'label' => '11%%',
            'activ' => true,
        ]);

        $imobil = Imobil::query()->create([
            'nume' => 'Imobil TVA 11',
            'strada' => 'Strada Test',
            'numar' => '5',
            'localitate' => 'Cluj',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa TVA',
            'implicit' => true,
        ]);

        $this->put(route('configurare-anexa.update', $configurare), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa TVA',
            'implicit' => true,
            'linii' => [
                [
                    'denumire' => 'Curatenie',
                    'um' => 'MP',
                    'pret_unitar' => '2.97',
                    'tva_21' => '11',
                    'tip_calcul' => 'pe_mp',
                ],
                [
                    'denumire' => 'Gunoi menajer',
                    'um' => 'PERS',
                    'pret_unitar' => '15',
                    'tva_21' => '11',
                    'tip_calcul' => 'persoane',
                ],
            ],
        ])->assertRedirect(route('configurare-anexa.edit', $configurare));

        $configurare->refresh()->load('linii');

        $this->assertCount(2, $configurare->linii);
        $this->assertSame('11', ServiciuStandardAnexa::normalizeValoare(
            ServiciuStandardAnexa::TIP_TVA,
            (string) $configurare->linii[0]->tva_21
        ));
        $this->assertSame('11', ServiciuStandardAnexa::normalizeValoare(
            ServiciuStandardAnexa::TIP_TVA,
            (string) $configurare->linii[1]->tva_21
        ));

        $this->get(route('configurare-anexa.edit', $configurare))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serviciiStandard.tva.0.valoare', '11')
                ->where('serviciiStandard.tva.0.label', '11%')
            );
    }
}
