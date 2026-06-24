<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\CitireContor;
use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\ContorConfigurabil;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\ContorConfigurabilSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContorConfigurabilTest extends TestCase
{
    use RefreshDatabase;

    public function test_citiri_contoare_afiseaza_contorul_configurabil_la_nivel_de_imobil(): void
    {
        [$imobil, , $linieConfigurabil] = $this->creeazaScenariuContorConfigurabil();

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'luna' => '2026-05', 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Imobil')
                ->has('contoareConfigurabile', 1)
                ->where('contoareConfigurabile.0.configurare_anexa_linie_id', $linieConfigurabil->id)
                ->has('spatii.0.liniiContor', 1)
                ->has('spatii.1.liniiContor', 1)
            );
    }

    public function test_citiri_contoare_salveaza_citirea_configurabila_fara_spatiu(): void
    {
        [$imobil, , $linieConfigurabil] = $this->creeazaScenariuContorConfigurabil();

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-05',
            'data_citire' => '2026-05-20T10:00',
            'citiri' => [[
                'spatiu_id' => null,
                'configurare_anexa_linie_id' => $linieConfigurabil->id,
                'index_vechi' => 0,
                'index_nou' => 817.27,
            ]],
        ])->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieConfigurabil->id,
            'luna' => '2026-05',
            'consum' => 817.27,
        ]);
    }

    public function test_generarea_anexei_repartizeaza_contorul_configurabil_dupa_scaderi(): void
    {
        [
            $imobil,
            $configurare,
            $linieConfigurabil,
            $linieIndividual,
            $spatiuA,
            $spatiuB,
        ] = $this->creeazaScenariuContorConfigurabil();

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $linieConfigurabil->id)
            ->firstOrFail();

        $regula->update([
            'foloseste_scaderi' => true,
            'scaderi' => [
                ['spatiu_id' => $spatiuA->id, 'configurare_anexa_linie_id' => $linieIndividual->id],
                ['spatiu_id' => $spatiuB->id, 'configurare_anexa_linie_id' => $linieIndividual->id],
            ],
            'alocari' => [$spatiuA->id, $spatiuB->id],
        ]);

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieConfigurabil->id,
            'luna' => '2026-05',
            'data_citire' => '2026-05-20 10:00:00',
            'index_vechi' => 0,
            'index_nou' => 817.27,
            'consum' => 817.27,
        ]);

        CitireContor::query()->create([
            'spatiu_id' => $spatiuA->id,
            'configurare_anexa_linie_id' => $linieIndividual->id,
            'luna' => '2026-05',
            'data_citire' => '2026-05-20 10:00:00',
            'index_vechi' => 0,
            'index_nou' => 272,
            'consum' => 272,
        ]);

        CitireContor::query()->create([
            'spatiu_id' => $spatiuB->id,
            'configurare_anexa_linie_id' => $linieIndividual->id,
            'luna' => '2026-05',
            'data_citire' => '2026-05-20 10:00:00',
            'index_vechi' => 0,
            'index_nou' => 461,
            'consum' => 461,
        ]);

        $this->post('/anexe/generare', ['luna' => '2026-06', 'imobil_id' => $imobil->id])
            ->assertRedirect();

        $anexe = Anexa::query()->with('linii')->get();
        $this->assertCount(2, $anexe);

        foreach ($anexe as $anexa) {
            $linie = $anexa->linii->firstWhere('denumire', $linieConfigurabil->denumire);
            $this->assertNotNull($linie);
            $this->assertEquals(42.135, (float) $linie->cantitate);
        }
    }

    public function test_configurare_contoare_listeaza_imobilele_cu_contoare_configurabile(): void
    {
        [$imobil, , $linieConfigurabil] = $this->creeazaScenariuContorConfigurabil();

        $this->get(route('configurare-contoare.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareContoare/Index')
                ->has('imobile', 1)
                ->where('imobile.0.id', $imobil->id)
                ->where('imobile.0.contoare_configurabile_count', 1)
            );
    }

    public function test_configurare_contoare_imobil_afiseaza_lista_contoarelor(): void
    {
        [$imobil, , $linieConfigurabil] = $this->creeazaScenariuContorConfigurabil();

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieConfigurabil->id,
            'luna' => '2026-05',
            'data_citire' => '2026-05-20 10:00:00',
            'index_vechi' => 100,
            'index_nou' => 817.27,
            'consum' => 717.27,
        ]);

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $linieConfigurabil->id)
            ->firstOrFail();

        $this->get(route('configurare-contoare.imobil', $imobil))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareContoare/Imobil')
                ->where('imobil.id', $imobil->id)
                ->has('contoare', 1)
                ->where('contoare.0.id', $regula->id)
                ->where('contoare.0.ultima_citire.index_vechi', '100.000')
                ->where('contoare.0.ultima_citire.index_nou', '817.270')
                ->where('contoare.0.ultima_citire.consum', '717.270')
            );
    }

    public function test_configurare_contoare_detaliu_afiseaza_citirea_si_formularul(): void
    {
        [$imobil, , $linieConfigurabil, , $spatiuA, $spatiuB] = $this->creeazaScenariuContorConfigurabil();

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieConfigurabil->id,
            'luna' => '2026-05',
            'data_citire' => '2026-05-20 10:00:00',
            'index_vechi' => 0,
            'index_nou' => 817.27,
            'consum' => 817.27,
        ]);

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $linieConfigurabil->id)
            ->firstOrFail();

        $this->get(route('configurare-contoare.contor', [
            'imobil' => $imobil->id,
            'contorConfigurabil' => $regula->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareContoare/Contor')
                ->where('contor.id', $regula->id)
                ->where('contor.ultima_citire.consum', '817.270')
                ->has('contor.spatiiOptions', 2)
                ->where('contor.spatiiOptions.0.id', $spatiuA->id)
                ->where('contor.spatiiOptions.1.id', $spatiuB->id)
            );
    }

    public function test_configurare_contoare_limiteaza_spatiile_la_anexa_alocata(): void
    {
        [$imobil, $configurare, $linieConfigurabil] = $this->creeazaScenariuContorConfigurabil();

        $altaAnexa = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa mica',
            'implicit' => false,
            'activ' => true,
        ]);

        $spatiuAltaAnexa = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S3',
            'status' => 'liber',
            'configurare_anexa_id' => $altaAnexa->id,
        ]);

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $linieConfigurabil->id)
            ->firstOrFail();

        $this->put(route('configurare-contoare.update', $regula), [
            'foloseste_scaderi' => true,
            'alocari' => [$spatiuAltaAnexa->id],
        ])->assertStatus(422);
    }

    public function test_configurare_contoare_listeaza_si_contoarele_pausale(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa servicii',
            'implicit' => true,
            'activ' => true,
        ]);

        $liniePausal = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Gunoi menajer',
            'tip_calcul' => 'pausal',
            'um' => 'Pers',
            'activ' => true,
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S1',
            'status' => 'liber',
            'configurare_anexa_id' => $configurare->id,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare);

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $liniePausal->id)
            ->firstOrFail();

        $this->get(route('configurare-contoare.imobil', $imobil))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareContoare/Imobil')
                ->has('contoare', 1)
                ->where('contoare.0.id', $regula->id)
                ->where('contoare.0.tip_label', 'Pausal')
            );
    }

    public function test_scaderile_accepta_doar_servicii_de_tip_contor(): void
    {
        [
            $imobil,
            $configurare,
            $linieConfigurabil,
            $linieIndividual,
            $spatiuA,
        ] = $this->creeazaScenariuContorConfigurabil();

        $liniePausal = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Apă pausal',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'activ' => true,
            'ordine' => 3,
        ]);

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $linieConfigurabil->id)
            ->firstOrFail();

        $this->get(route('configurare-contoare.contor', [
            'imobil' => $imobil->id,
            'contorConfigurabil' => $regula->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contor.liniiScadereOptions.0.linii.0.id', $linieIndividual->id)
                ->where('contor.liniiScadereOptions.0.linii.0.label', 'Energie electrica · Contor')
                ->missing('contor.liniiScadereOptions.0.linii.1')
            );

        $this->put(route('configurare-contoare.update', $regula), [
            'foloseste_scaderi' => true,
            'alocari' => [$spatiuA->id],
            'scaderi' => [[
                'spatiu_id' => $spatiuA->id,
                'configurare_anexa_linie_id' => $liniePausal->id,
            ]],
        ])->assertStatus(422);
    }

    public function test_lista_contoare_afiseaza_toate_spatiile_anexei_fara_scaderi(): void
    {
        [
            $imobil,
            $configurare,
            ,
            ,
            $spatiuA,
            $spatiuB,
        ] = $this->creeazaScenariuContorConfigurabil();

        $spatiuC = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        $liniePausal = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Consum apa - mc / pers',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'activ' => true,
            'ordine' => 4,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare->fresh(['linii']));

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $liniePausal->id)
            ->firstOrFail();

        $regula->update([
            'foloseste_scaderi' => false,
            'scaderi' => [],
            'alocari' => [$spatiuA->id, $spatiuB->id],
        ]);

        $this->get(route('configurare-contoare.imobil', $imobil))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareContoare/Imobil')
                ->where('contoare', fn ($contoare) => collect($contoare)->firstWhere('id', $regula->id)['alocari_count'] === 3)
            );

        $this->get(route('configurare-contoare.contor', [
            'imobil' => $imobil->id,
            'contorConfigurabil' => $regula->id,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contor.spatiiOptions', 3)
            );
    }

    public function test_generarea_anexei_repartizeaza_cantitatea_pausal_pe_toate_spatiile_anexei_chiar_cu_alocari_vechi(): void
    {
        [
            $imobil,
            $configurare,
            ,
            ,
            $spatiuA,
            $spatiuB,
        ] = $this->creeazaScenariuContorConfigurabil();

        $spatiuC = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiuC->id,
            'numar_contract' => 'C-HQC1',
            'chirias' => 'Test SRL C',
            'data_start' => '2026-01-01',
            'status' => 'activ',
        ]);

        $linieApa = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Consum apa - mc / pers',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'pret_unitar' => 10,
            'activ' => true,
            'ordine' => 4,
        ]);

        $linieCanalizare = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Canalizare mc / pers',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'pret_unitar' => 8,
            'activ' => true,
            'ordine' => 5,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare->fresh(['linii']));

        ContorConfigurabil::query()
            ->whereIn('configurare_anexa_linie_id', [$linieApa->id, $linieCanalizare->id])
            ->update([
                'foloseste_scaderi' => false,
                'scaderi' => [],
                'alocari' => [$spatiuA->id, $spatiuB->id],
            ]);

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieApa->id,
            'luna' => '2026-05',
            'consum' => 6,
        ]);

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieCanalizare->id,
            'luna' => '2026-05',
            'consum' => 6,
        ]);

        $this->post('/anexe/generare', ['luna' => '2026-06', 'imobil_id' => $imobil->id])
            ->assertRedirect();

        $anexe = Anexa::query()->with('linii')->get();

        $this->assertCount(3, $anexe);

        foreach ($anexe as $anexa) {
            $linieApaGenerata = $anexa->linii->firstWhere('denumire', $linieApa->denumire);
            $linieCanalizareGenerata = $anexa->linii->firstWhere('denumire', $linieCanalizare->denumire);

            $this->assertNotNull($linieApaGenerata);
            $this->assertNotNull($linieCanalizareGenerata);
            $this->assertEquals(2, (float) $linieApaGenerata->cantitate);
            $this->assertEquals(2, (float) $linieCanalizareGenerata->cantitate);
        }
    }

    public function test_generarea_anexei_repartizeaza_pausal_apa_si_canalizare_pe_persoane(): void
    {
        [
            $imobil,
            $configurare,
            ,
            ,
            $spatiuA,
            $spatiuB,
        ] = $this->creeazaScenariuContorConfigurabil();

        $spatiuC = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'persoane_declarate' => 3,
        ]);

        $spatiuA->update(['persoane_declarate' => 1]);
        $spatiuB->update(['persoane_declarate' => 2]);

        Contract::query()->create([
            'spatiu_id' => $spatiuC->id,
            'numar_contract' => 'C-HQC1',
            'chirias' => 'Test SRL C',
            'data_start' => '2026-01-01',
            'status' => 'activ',
        ]);

        $linieApa = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Consum apa - mc / pers',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'pret_unitar' => 10,
            'activ' => true,
            'ordine' => 4,
        ]);

        $linieCanalizare = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Canalizare mc / pers',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'pret_unitar' => 8,
            'activ' => true,
            'ordine' => 5,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare->fresh(['linii']));

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieApa->id,
            'luna' => '2026-05',
            'consum' => 49.5,
        ]);

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieCanalizare->id,
            'luna' => '2026-05',
            'consum' => 49.5,
        ]);

        $this->post('/anexe/generare', ['luna' => '2026-06', 'imobil_id' => $imobil->id])
            ->assertRedirect();

        $anexe = Anexa::query()->with(['linii', 'contract'])->get();

        $this->assertCount(3, $anexe);

        $cantitatePentruSpatiu = function (Spatiu $spatiu, string $denumire) use ($anexe): float {
            $anexa = $anexe->first(fn (Anexa $anexa): bool => (int) $anexa->contract?->spatiu_id === (int) $spatiu->id);
            $this->assertNotNull($anexa, "Lipseste anexa pentru {$spatiu->identificator}");

            return (float) $anexa->linii->firstWhere('denumire', $denumire)?->cantitate;
        };

        $this->assertEquals(8.25, $cantitatePentruSpatiu($spatiuA, $linieApa->denumire));
        $this->assertEquals(16.5, $cantitatePentruSpatiu($spatiuB, $linieApa->denumire));
        $this->assertEquals(24.75, $cantitatePentruSpatiu($spatiuC, $linieApa->denumire));
        $this->assertEquals(8.25, $cantitatePentruSpatiu($spatiuA, $linieCanalizare->denumire));
    }

    public function test_generarea_anexei_pastreaza_repartizarea_contorului_configurabil_pe_spatii(): void
    {
        [
            $imobil,
            ,
            $linieConfigurabil,
            ,
            $spatiuA,
            $spatiuB,
        ] = $this->creeazaScenariuContorConfigurabil();

        $spatiuA->update(['persoane_declarate' => 1]);
        $spatiuB->update(['persoane_declarate' => 5]);

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieConfigurabil->id,
            'luna' => '2026-05',
            'index_vechi' => 0,
            'index_nou' => 10,
            'consum' => 10,
        ]);

        $this->post('/anexe/generare', ['luna' => '2026-06', 'imobil_id' => $imobil->id])
            ->assertRedirect();

        $anexe = Anexa::query()->with('linii')->get();

        foreach ($anexe as $anexa) {
            $linie = $anexa->linii->firstWhere('denumire', $linieConfigurabil->denumire);
            $this->assertNotNull($linie);
            $this->assertEquals(5, (float) $linie->cantitate);
        }
    }

    /**
     * @return array{
     *     0: Imobil,
     *     1: ConfigurareAnexaImobil,
     *     2: ConfigurareAnexaLinie,
     *     3: ConfigurareAnexaLinie,
     *     4: Spatiu,
     *     5: Spatiu
     * }
     */
    private function creeazaScenariuContorConfigurabil(): array
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa > 50 mp',
            'implicit' => true,
            'activ' => true,
        ]);

        $linieConfigurabil = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Energie electrica spatii comune',
            'tip_calcul' => 'Contor configurabil',
            'um' => 'Kw',
            'activ' => true,
            'ordine' => 1,
        ]);

        $linieIndividual = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Energie electrica',
            'tip_calcul' => 'contor',
            'um' => 'Kw',
            'activ' => true,
            'ordine' => 2,
        ]);

        $spatiuA = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQA1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        $spatiuB = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQB1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        foreach ([$spatiuA, $spatiuB] as $spatiu) {
            Contract::query()->create([
                'spatiu_id' => $spatiu->id,
                'numar_contract' => "C-{$spatiu->identificator}",
                'chirias' => 'Test SRL',
                'data_start' => '2026-01-01',
                'status' => 'activ',
            ]);
        }

        ContorConfigurabilSync::syncForConfigurare($configurare);

        return [$imobil, $configurare, $linieConfigurabil, $linieIndividual, $spatiuA, $spatiuB];
    }

    public function test_spatiul_liber_nu_intra_in_alocarile_efective_pentru_pausal(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa servicii',
            'implicit' => true,
            'activ' => true,
        ]);

        $liniePausal = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Consum apa - mc / pers',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'activ' => true,
            'ordine' => 1,
        ]);

        $spatiuInchiriat = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C303',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        $spatiuLiber = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C304',
            'status' => 'liber',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 2,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare);

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $liniePausal->id)
            ->firstOrFail();

        $this->assertSame([$spatiuInchiriat->id], $regula->alocariEfectiveIds());
        $this->assertSame([$spatiuInchiriat->id], $regula->alocariIds());
        $this->assertNotContains($spatiuLiber->id, $regula->alocariIds());
    }

    public function test_pausal_repartizeaza_doar_pe_spatiile_inchiriate_si_afiseaza_numarul_corect(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timisoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa Pers < 50 mp',
            'implicit' => true,
            'activ' => true,
        ]);

        $linieApa = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Consum apa - mc / pers',
            'tip_calcul' => 'pausal',
            'um' => 'MC',
            'pret_unitar' => 9.74,
            'activ' => true,
            'ordine' => 1,
        ]);

        $spatiiInchiriate = collect(range(1, 3))->map(function (int $index) use ($imobil, $configurare): Spatiu {
            $spatiu = Spatiu::query()->create([
                'imobil_id' => $imobil->id,
                'identificator' => "C30{$index}",
                'status' => 'inchiriat',
                'configurare_anexa_id' => $configurare->id,
                'ordine' => $index,
            ]);

            Contract::query()->create([
                'spatiu_id' => $spatiu->id,
                'numar_contract' => "C-{$spatiu->identificator}",
                'chirias' => 'Test SRL',
                'data_start' => '2026-01-01',
                'status' => 'activ',
            ]);

            return $spatiu;
        });

        foreach (range(1, 2) as $index) {
            Spatiu::query()->create([
                'imobil_id' => $imobil->id,
                'identificator' => "L30{$index}",
                'status' => 'liber',
                'configurare_anexa_id' => $configurare->id,
                'ordine' => 10 + $index,
            ]);
        }

        ContorConfigurabilSync::syncForConfigurare($configurare);

        $regula = ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $linieApa->id)
            ->firstOrFail();

        $this->assertSame($spatiiInchiriate->pluck('id')->sort()->values()->all(), collect($regula->alocariEfectiveIds())->sort()->values()->all());
        $this->assertSame(3, Spatiu::countInchiriateForAnexa($configurare->id));
        $this->assertSame(5, Spatiu::countAlocateForAnexa($configurare->id));

        CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieApa->id,
            'luna' => '2026-05',
            'consum' => 52.5,
        ]);

        $this->post('/anexe/generare', ['luna' => '2026-06', 'imobil_id' => $imobil->id])
            ->assertRedirect();

        $cantitatePerSpatiu = round(52.5 / 3, 3);

        foreach (Anexa::query()->with('linii')->get() as $anexa) {
            $linieGenerata = $anexa->linii->firstWhere('denumire', $linieApa->denumire);
            $this->assertNotNull($linieGenerata);
            $this->assertEquals($cantitatePerSpatiu, (float) $linieGenerata->cantitate);
        }

        $this->get('/configurare-anexa?imobil_id='.$imobil->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('anexe.0.spatii_inchiriate_count', 3)
                ->where('anexe.0.spatii_count', 5)
            );
    }
}
