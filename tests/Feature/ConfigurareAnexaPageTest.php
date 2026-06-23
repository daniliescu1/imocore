<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\ConfigurareAnexaImobil;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\ServiciuStandardAnexa;
use App\Models\SetareAplicatie;
use App\Models\Spatiu;
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

    public function test_formularul_anexei_trimite_spatiul_pentru_previzualizare_mp(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil previzualizare mp',
            'strada' => 'Strada Test',
            'numar' => '3A',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa previzualizare mp',
            'implicit' => true,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'MP-1',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => '156',
            'persoane_declarate' => 4,
            'configurare_anexa_id' => $configurare->id,
        ]);

        $this->get(route('configurare-anexa.edit', $configurare))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('previewSpatiu.identificator', 'MP-1')
                ->where('previewSpatiu.suprafata_contractuala_mp', '156')
                ->where('previewSpatiu.persoane_pentru_anexa', 4)
            );
    }

    public function test_formularul_normalizeaza_tipul_vechi_mp_coeficient(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil tip vechi',
            'strada' => 'Strada Test',
            'numar' => '3B',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa tip vechi',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Apa pluviala meteorica',
            'tip_calcul' => 'mp*coeficient',
            'index_vechi' => '156',
            'index_nou' => '156',
            'facturat' => '24336',
            'coeficient' => '156',
            'um' => 'MC',
            'pret_unitar' => '9.74',
            'tva_21' => '11',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'MP-LEGACY',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => '156',
            'configurare_anexa_id' => $configurare->id,
        ]);

        $this->get(route('configurare-anexa.edit', $configurare))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('anexa.linii.0.tip_calcul', 'mp_coeficient')
                ->where('anexa.linii.0.coeficient', '0.09')
                ->where('anexa.linii.0.index_vechi', '')
                ->where('anexa.linii.0.facturat', '')
            );
    }

    public function test_salvarea_anexei_nu_pastreaza_citiri_si_cantitati_specifice_spatiului(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil template',
            'strada' => 'Strada Test',
            'numar' => '5',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa template',
            'implicit' => true,
        ]);

        $this->put(route('configurare-anexa.update', $configurare), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa template',
            'implicit' => true,
            'activ' => true,
            'linii' => [
                [
                    'tip_linie' => 'serviciu',
                    'denumire' => 'Energie Electrica',
                    'tip_calcul' => 'contor',
                    'index_vechi' => '2495.14',
                    'index_nou' => '3682.4',
                    'facturat' => '1187.26',
                    'um' => 'Kw',
                    'pret_unitar' => '1.528',
                    'valoare' => '1814.13',
                    'tva_21' => '21',
                    'activ' => true,
                ],
                [
                    'tip_linie' => 'serviciu',
                    'denumire' => 'Incalzire / mp',
                    'tip_calcul' => 'pe_mp',
                    'facturat' => '156',
                    'um' => 'MP',
                    'pret_unitar' => '2.5',
                    'valoare' => '390',
                    'tva_21' => '21',
                    'activ' => true,
                ],
                [
                    'tip_linie' => 'serviciu',
                    'denumire' => 'Servicii Gunoi Menajer',
                    'tip_calcul' => 'persoane',
                    'facturat' => '20',
                    'um' => 'Pers',
                    'pret_unitar' => '5',
                    'valoare' => '100',
                    'tva_21' => '21',
                    'activ' => true,
                ],
            ],
        ])->assertRedirect();

        $configurare->refresh()->load('linii');

        $this->assertNull($configurare->linii[0]->index_vechi);
        $this->assertNull($configurare->linii[0]->index_nou);
        $this->assertNull($configurare->linii[0]->facturat);
        $this->assertNull($configurare->linii[1]->facturat);
        $this->assertNull($configurare->linii[2]->facturat);
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

    public function test_editarea_tipului_mp_coeficient_actualizeaza_coeficientul_liniilor(): void
    {
        $standard = ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_TIP_CALCUL,
            'valoare' => 'mp_coeficient',
            'label' => 'Mp × coeficient',
            'coeficient' => '0.0900',
            'activ' => true,
        ]);

        $imobil = Imobil::query()->create([
            'nume' => 'Imobil coeficient standard',
            'strada' => 'Strada Test',
            'numar' => '4B',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa coeficient standard',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Apa pluviala / meteorica',
            'tip_calcul' => 'mp_coeficient',
            'coeficient' => '0.0900',
            'um' => 'MC',
            'pret_unitar' => '9.74',
            'tva_21' => '11',
        ]);

        $this->put(route('configurare-anexa.servicii-standard.update', [
            'tip' => ServiciuStandardAnexa::TIP_TIP_CALCUL,
            'serviciuStandard' => $standard,
        ]), [
            'valoare' => 'mp_coeficient',
            'coeficient' => '0.12',
        ])->assertRedirect(route('configurare-anexa.servicii-standard.index', [
            'tip' => ServiciuStandardAnexa::TIP_TIP_CALCUL,
        ]));

        $this->assertEquals(0.12, (float) $standard->fresh()->coeficient);
        $this->assertEquals(0.12, (float) $configurare->linii()->first()->coeficient);

        $this->get(route('configurare-anexa.servicii-standard.index', [
            'tip' => ServiciuStandardAnexa::TIP_TIP_CALCUL,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('valori.0.valoare', 'mp_coeficient')
                ->where('valori.0.coeficient', '0.1200')
            );
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

    public function test_preturile_standard_sunt_definite_pe_denumire_serviciu(): void
    {
        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Energie electrica',
            'label' => 'Energie electrica',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Consum Apa',
            'label' => 'Consum Apa',
            'activ' => true,
        ]);

        $this->get(route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareAnexa/ServiciiStandard')
                ->where('tipActiv', ServiciuStandardAnexa::TIP_PRET)
                ->has('valori', 2)
                ->where('valori.0.valoare', 'Consum Apa')
                ->where('valori.1.valoare', 'Energie electrica')
            );

        $pretEnergie = ServiciuStandardAnexa::query()
            ->where('tip', ServiciuStandardAnexa::TIP_PRET)
            ->where('valoare', 'Energie electrica')
            ->firstOrFail();

        $this->put(route('configurare-anexa.servicii-standard.update', [
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'serviciuStandard' => $pretEnergie,
        ]), [
            'valoare' => 'Energie electrica',
            'coeficient' => '1.5280',
        ])->assertRedirect(route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET]));

        $this->assertSame('1.5280', ServiciuStandardAnexa::pretPentruDenumire('Energie electrica'));
    }

    public function test_preturile_standard_se_salveaza_bulk(): void
    {
        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Energie electrica',
            'label' => 'Energie electrica',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::syncPreturiFromDenumire();

        $pret = ServiciuStandardAnexa::query()
            ->where('tip', ServiciuStandardAnexa::TIP_PRET)
            ->where('valoare', 'Energie electrica')
            ->firstOrFail();

        $this->put(route('configurare-anexa.servicii-standard.pret.bulk'), [
            'preturi' => [
                ['id' => $pret->id, 'coeficient' => '1.528'],
            ],
        ])->assertRedirect(route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET]));

        $this->assertSame('1.5280', (string) $pret->fresh()->coeficient);
        $this->assertSame('1.5280', ServiciuStandardAnexa::pretPentruDenumire('Energie electrica'));
    }

    public function test_preturile_standard_salveaza_si_tva_bulk(): void
    {
        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Incalzire / mp',
            'label' => 'Incalzire / mp',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_TVA,
            'valoare' => '21',
            'label' => '21%',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::syncPreturiFromDenumire();

        $pret = ServiciuStandardAnexa::query()
            ->where('tip', ServiciuStandardAnexa::TIP_PRET)
            ->where('valoare', 'Incalzire / mp')
            ->firstOrFail();

        $this->put(route('configurare-anexa.servicii-standard.pret.bulk'), [
            'preturi' => [
                ['id' => $pret->id, 'coeficient' => '8.82', 'tva' => '21'],
            ],
        ])->assertRedirect(route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET]));

        $this->assertSame('8.8200', (string) $pret->fresh()->coeficient);
        $this->assertSame('21', ServiciuStandardAnexa::tvaPentruDenumire('Incalzire / mp'));
    }

    public function test_preturile_standard_salveaza_si_um_bulk(): void
    {
        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Energie Electrica',
            'label' => 'Energie Electrica',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_UM,
            'valoare' => 'Kw',
            'label' => 'Kw',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::syncPreturiFromDenumire();

        $pret = ServiciuStandardAnexa::query()
            ->where('tip', ServiciuStandardAnexa::TIP_PRET)
            ->where('valoare', 'Energie Electrica')
            ->firstOrFail();

        $imobil = Imobil::query()->create([
            'nume' => 'Imobil UM pret',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa UM',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Energie Electrica',
            'tip_calcul' => 'contor',
            'um' => 'MC',
            'pret_unitar' => '1',
            'activ' => true,
        ]);

        $this->put(route('configurare-anexa.servicii-standard.pret.bulk'), [
            'preturi' => [
                ['id' => $pret->id, 'coeficient' => '1.528', 'tva' => '21', 'um' => 'Kw'],
            ],
        ])->assertRedirect(route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET]));

        $this->assertSame('1.5280', (string) $pret->fresh()->coeficient);
        $this->assertSame('Kw', ServiciuStandardAnexa::umPentruDenumire('Energie Electrica'));
        $this->assertSame('Kw', $configurare->linii()->first()->fresh()->um);
    }

    public function test_salvarea_anexei_pastreaza_facturat_pentru_tip_administrare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil administrare',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa administrare',
            'implicit' => true,
        ]);

        $this->put(route('configurare-anexa.update', $configurare), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa administrare',
            'implicit' => true,
            'activ' => true,
            'linii' => [[
                'tip_linie' => 'serviciu',
                'denumire' => 'Administrare spatiu: citire contoare',
                'tip_calcul' => 'Administrare',
                'facturat' => '1',
                'um' => 'buc',
                'pret_unitar' => '55',
                'valoare' => '55',
                'tva_21' => '21',
                'activ' => true,
            ]],
        ])->assertRedirect();

        $linie = $configurare->fresh()->linii->firstOrFail();

        $this->assertSame('administrare', $linie->tip_calcul);
        $this->assertSame('1.000', $linie->facturat);
        $this->assertNull($linie->index_vechi);
        $this->assertNull($linie->index_nou);
    }

    public function test_generarea_anexei_afiseaza_cantitatea_pentru_tip_administrare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil administrare generare',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa administrare',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Administrare spatiu: citire contoare',
            'tip_calcul' => 'administrare',
            'facturat' => '1',
            'um' => 'buc',
            'pret_unitar' => '55',
            'valoare' => '55',
            'tva_21' => '21',
            'ordine' => 1,
            'nr_crt' => 1,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HO STEAGURI',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'Nr. 90 din 11.05.2021',
            'chirias' => 'CLEAR VIEW MEDIA OOH SRL',
            'data_start' => '2021-05-11',
            'status' => 'activ',
        ]);

        $this->post('/anexe/generare', [
            'luna' => '2026-06',
            'imobil_id' => $imobil->id,
        ])->assertRedirect();

        $linie = Anexa::query()->with('linii')->firstOrFail()->linii->first();

        $this->assertSame('administrare', $linie->tip_calcul);
        $this->assertEquals(1, (float) $linie->cantitate);
        $this->assertEquals(55, (float) $linie->valoare);
        $this->assertNull($linie->index_vechi);
        $this->assertNull($linie->index_nou);
    }

    public function test_generarea_anexei_deriveaza_cantitatea_din_valoare_pentru_administrare_fara_facturat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil administrare fallback',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa administrare',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Administrare spatiu: citire contoare',
            'tip_calcul' => 'administrare',
            'facturat' => null,
            'um' => 'buc',
            'pret_unitar' => '55',
            'valoare' => '55',
            'tva_21' => '21',
            'ordine' => 1,
            'nr_crt' => 1,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HO STEAGURI',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-001',
            'chirias' => 'Test SRL',
            'data_start' => '2026-01-01',
            'status' => 'activ',
        ]);

        $this->post('/anexe/generare', [
            'luna' => '2026-06',
            'imobil_id' => $imobil->id,
        ])->assertRedirect();

        $linie = Anexa::query()->with('linii')->firstOrFail()->linii->first();

        $this->assertEquals(1, (float) $linie->cantitate);
    }

    public function test_sterge_anexa_neutilizata(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil stergere anexa',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa de sters',
            'implicit' => true,
        ]);

        $this->delete(route('configurare-anexa.destroy', $configurare))
            ->assertRedirect(route('configurare-anexa.index', ['imobil_id' => $imobil->id]));

        $this->assertNull(ConfigurareAnexaImobil::query()->find($configurare->id));
    }

    public function test_sterge_anexa_respinge_daca_e_folosita_de_spatii(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil anexa ocupata',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa ocupata',
            'implicit' => true,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D101',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        $this->from(route('configurare-anexa.index'))
            ->delete(route('configurare-anexa.destroy', $configurare))
            ->assertSessionHasErrors('anexa');

        $this->assertNotNull(ConfigurareAnexaImobil::query()->find($configurare->id));
    }

    public function test_sterge_anexa_implicita_promoveaza_alta_anexa(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil anexa implicita',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj',
        ]);

        $implicita = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa A',
            'implicit' => true,
        ]);

        $alta = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa B',
            'implicit' => false,
        ]);

        $this->delete(route('configurare-anexa.destroy', $implicita))
            ->assertRedirect(route('configurare-anexa.index', ['imobil_id' => $imobil->id]));

        $this->assertNull(ConfigurareAnexaImobil::query()->find($implicita->id));
        $this->assertTrue((bool) $alta->fresh()->implicit);
    }

    public function test_preturile_standard_in_eur_se_propaga_la_linii_cu_conversie(): void
    {
        SetareAplicatie::seteaza('curs_eur_facturare', '5');

        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Aer conditionat',
            'label' => 'Aer conditionat',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::syncPreturiFromDenumire();

        $pret = ServiciuStandardAnexa::query()
            ->where('tip', ServiciuStandardAnexa::TIP_PRET)
            ->where('valoare', 'Aer conditionat')
            ->firstOrFail();

        $imobil = Imobil::query()->create([
            'nume' => 'Imobil aer conditionat',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa aer',
            'implicit' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Aer conditionat',
            'tip_calcul' => 'fix',
            'um' => 'buc',
            'pret_unitar' => '1',
            'activ' => true,
        ]);

        $this->put(route('configurare-anexa.servicii-standard.pret.bulk'), [
            'preturi' => [
                [
                    'id' => $pret->id,
                    'coeficient' => '50',
                    'moneda' => 'EUR',
                    'tva' => '21',
                    'um' => 'buc',
                ],
            ],
        ])->assertRedirect(route('configurare-anexa.servicii-standard.index', ['tip' => ServiciuStandardAnexa::TIP_PRET]));

        $pret->refresh();
        $linie = $configurare->linii()->first()->fresh();

        $this->assertSame('EUR', $pret->moneda);
        $this->assertSame('50.0000', (string) $pret->coeficient);
        $this->assertSame('EUR', $linie->moneda);
        $this->assertSame('250.0000', (string) $linie->pret_unitar);
        $this->assertSame('50.000', (string) $linie->facturat);
        $this->assertSame('250.00', (string) $linie->valoare);
    }

    public function test_indexul_configurare_anexa_filtreaza_anexele_dupa_spatiu(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $anexaPrincipala = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa principala',
            'implicit' => true,
        ]);

        $anexaSecundara = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa secundara',
            'implicit' => false,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC118',
            'chirias' => 'Supermedical SRL',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $anexaPrincipala->id,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E306',
            'chirias' => 'Alt chiriaș',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $anexaSecundara->id,
        ]);

        $this->get(route('configurare-anexa.index', ['search' => 'HQC118']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareAnexa/Index')
                ->where('filters.search', 'HQC118')
                ->has('anexe', 1)
                ->where('anexe.0.denumire', 'Anexa principala')
                ->has('spatiiCautate', 1)
                ->where('spatiiCautate.0.identificator', 'HQC118')
            );
    }

    public function test_indexul_configurare_anexa_afiseaza_mesaj_cand_spatiul_nu_are_anexa(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC118',
            'chirias' => 'Supermedical SRL',
            'status' => 'liber',
        ]);

        $this->get(route('configurare-anexa.index', ['search' => 'Supermedical']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareAnexa/Index')
                ->has('anexe', 0)
                ->has('spatiiCautate', 1)
            );
    }
}
