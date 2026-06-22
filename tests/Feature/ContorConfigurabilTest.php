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
}
