<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatiuDocumenteTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_exposes_contract_and_document_rows(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
            'tip_linie' => 'serviciu',
            'denumire' => 'Energie electrica',
            'tip_calcul' => 'manual',
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D204',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-204',
            'chirias' => 'Test SRL',
            'data_start' => '2025-01-01',
            'chirie' => 1000,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->where('showDocumente', true)
                ->where('contractActiv.id', $contract->id)
                ->where('contractActiv.numar_contract', 'C-204')
                ->has('configurariAnexe.'.$imobil->id, 1)
            );
    }

    public function test_document_rows_are_hidden_for_spatiu_comun(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Comun',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Comun 1',
            'status' => 'comun',
            'ordine' => 1,
        ]);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('showDocumente', false)
                ->where('contractActiv', null)
            );
    }

    public function test_document_rows_appear_for_parcare_inchiriat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Parking',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'P-12',
            'etaj' => 'Parcare',
            'status' => 'inchiriat',
            'ordine' => 1,
        ]);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('showDocumente', true)
            );
    }

    public function test_document_rows_appear_for_liber_fara_contract_activ(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'L-1',
            'etaj' => 'Parter',
            'status' => 'liber',
            'chirias' => 'Fost chiriaș',
            'ordine' => 1,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-OLD',
            'chirias' => 'Fost chiriaș',
            'data_start' => '2025-01-01',
            'chirie' => 500,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('showDocumente', true)
                ->where('contractActiv', null)
            );
    }

    public function test_salvarea_spatiului_liber_sterge_chiriasul(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C-302',
            'etaj' => 'Parter',
            'status' => 'inchiriat',
            'chirias' => 'Golden Cube',
            'ordine' => 1,
        ]);

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'C-302',
            'etaj' => 'Parter',
            'status' => 'liber',
            'de_lamurit' => false,
            'marcat_galben' => false,
            'marcat_verde' => false,
        ])->assertRedirect();

        $spatiu->refresh();

        $this->assertSame('liber', $spatiu->status);
        $this->assertNull($spatiu->chirias);
    }

    public function test_document_rows_are_hidden_for_rezervat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $rezervat = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'R-1',
            'etaj' => 'Fațadă',
            'status' => 'rezervat',
            'ordine' => 2,
        ]);

        $this->get(route('spatii.edit', $rezervat))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showDocumente', false));
    }

    public function test_parcare_inchiriat_pastreaza_configurarea_anexei_la_salvare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Parking',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă parcare',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'P-12',
            'etaj' => 'Parcare',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'moneda' => 'RON',
            'ordine' => 1,
        ]);

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'P-12',
            'etaj' => 'Parcare',
            'status' => 'inchiriat',
            'pret_lunar' => 250,
            'moneda' => 'RON',
            'configurare_anexa_id' => $configurare->id,
            'de_lamurit' => false,
            'marcat_galben' => false,
            'marcat_verde' => false,
        ])->assertRedirect();

        $this->assertSame($configurare->id, $spatiu->fresh()->configurare_anexa_id);
    }

    public function test_selectarea_anexei_din_editare_spatiu_se_salveaza_automat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $anexaInitiala = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă inițială',
            'implicit' => true,
            'activ' => true,
        ]);

        $anexaNoua = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă nouă',
            'implicit' => false,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQE 103',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $anexaInitiala->id,
            'ordine' => 1,
        ]);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.anexa', $spatiu), [
                'configurare_anexa_id' => $anexaNoua->id,
            ])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $this->assertSame($anexaNoua->id, $spatiu->fresh()->configurare_anexa_id);
    }

    public function test_clone_anexa_individuala_creeaza_copie_si_realoca_doar_spatiul_curent(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
            'tip_linie' => 'serviciu',
            'denumire' => 'Apa',
            'tip_calcul' => 'manual',
            'activ' => true,
        ]);

        $spatiu1 = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D204',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        $spatiu2 = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D205',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 2,
        ]);

        $this->post(route('spatii.anexa-individuala', $spatiu1))
            ->assertRedirect();

        $spatiu1->refresh();
        $spatiu2->refresh();

        $this->assertNotSame($configurare->id, $spatiu1->configurare_anexa_id);
        $this->assertSame($configurare->id, $spatiu2->configurare_anexa_id);

        $copie = ConfigurareAnexaImobil::query()->findOrFail($spatiu1->configurare_anexa_id);
        $this->assertSame('Anexă utilități · D204', $copie->denumire);
        $this->assertSame(1, $copie->linii()->count());
    }

    public function test_personalizare_anexa_deschide_editorul_cu_sugestie_de_nume(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D204',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        $response = $this->post(route('spatii.anexa-individuala', $spatiu));
        $copie = ConfigurareAnexaImobil::query()->findOrFail($spatiu->fresh()->configurare_anexa_id);

        $response->assertRedirect(route('configurare-anexa.edit', [
            'configurare' => $copie,
            'return_url' => route('spatii.edit', $spatiu),
            'personalizare' => 1,
            'spatiu_id' => $spatiu->id,
            'anexa_anterioara_id' => $configurare->id,
            'denumire_sugestie' => 'Anexă utilități · D204',
        ], absolute: false));

        $this->get(route('configurare-anexa.edit', [
            'configurare' => $copie,
            'personalizare' => 1,
            'spatiu_id' => $spatiu->id,
            'anexa_anterioara_id' => $configurare->id,
            'denumire_sugestie' => 'Anexă utilități · D204',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ConfigurareAnexa/Form')
                ->where('personalizare.activ', true)
                ->where('personalizare.denumire_sugestie', 'Anexă utilități · D204')
                ->where('personalizare.spatiu_id', $spatiu->id)
                ->where('personalizare.anexa_anterioara_id', $configurare->id)
                ->where('anexa.denumire', 'Anexă utilități · D204')
            );
    }

    public function test_anularea_personalizarii_anexei_restabileste_anexa_anterioara(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        $configurare->linii()->create([
            'ordine' => 1,
            'tip_linie' => 'serviciu',
            'denumire' => 'Apă rece',
            'tip_calcul' => 'manual',
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D204',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        $this->post(route('spatii.anexa-individuala', $spatiu));

        $spatiu->refresh();
        $copieId = $spatiu->configurare_anexa_id;
        $this->assertNotSame($configurare->id, $copieId);

        $response = $this->post(route('configurare-anexa.cancel-personalizare', $copieId), [
            'spatiu_id' => $spatiu->id,
            'anexa_anterioara_id' => $configurare->id,
            'return_url' => route('spatii.edit', $spatiu),
        ]);

        $response->assertRedirect(route('spatii.edit', $spatiu, absolute: false));

        $spatiu->refresh();
        $this->assertSame($configurare->id, $spatiu->configurare_anexa_id);
        $this->assertNull(ConfigurareAnexaImobil::query()->find($copieId));
    }

    public function test_salvarea_anexei_respinge_denumire_duplicata_pe_acelasi_imobil(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $existenta = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        $copie = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => '',
            'implicit' => false,
            'activ' => true,
        ]);

        $this->from(route('configurare-anexa.edit', $copie))
            ->put(route('configurare-anexa.update', $copie), [
                'imobil_id' => $imobil->id,
                'denumire' => 'Anexă utilități',
                'implicit' => false,
                'linii' => [],
            ])
            ->assertSessionHasErrors(['denumire']);

        $this->assertSame('', $copie->fresh()->denumire);
        $this->assertSame('Anexă utilități', $existenta->fresh()->denumire);
    }

    public function test_contract_store_redirects_back_to_spatiu_when_return_url_is_set(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D204',
            'status' => 'liber',
            'ordine' => 1,
        ]);
        $locator = Locator::query()->create([
            'nume' => 'Locator Test',
            'imobil_id' => $imobil->id,
        ]);

        $returnUrl = route('spatii.edit', $spatiu);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            'locator_id' => $locator->id,
            'numar_contract' => 'C-NEW',
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'Chiriaș Test',
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => 'test@example.com',
                'telefon' => '0700000000',
            ],
            'data_start' => '2025-01-01',
            'data_end' => '2026-01-01',
            'chirie' => 900,
            'moneda' => 'EUR',
            'status' => 'activ',
            'return_url' => $returnUrl,
        ])->assertRedirect($returnUrl);

        $this->assertDatabaseHas('contracte', [
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-NEW',
        ]);
    }

    public function test_preview_anexa_renders_allocated_template_without_persisting(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
            'tip_linie' => 'serviciu',
            'denumire' => 'Energie electrica',
            'tip_calcul' => 'manual',
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D204',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-204',
            'chirias' => 'Test SRL',
            'data_start' => '2025-01-01',
            'chirie' => 1000,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $returnUrl = route('spatii.edit', $spatiu);
        $normalizedReturnUrl = '/spatii/'.$spatiu->id.'/editare';

        $this->get(route('spatii.anexa-previzualizare', [
            'spatiu' => $spatiu,
            'configurare_anexa_id' => $configurare->id,
            'return_url' => $returnUrl,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anexe/Show')
                ->where('previewMode', true)
                ->where('downloadUrl', null)
                ->where('returnUrl', $normalizedReturnUrl)
                ->where('returnLabel', 'Înapoi la spațiu')
                ->where('anexa.status', 'preview')
                ->where('anexa.configurare.denumire', 'Anexă utilități')
                ->where('anexa.spatiu.identificator', 'D204')
                ->has('anexa.linii', 1)
            );

        $this->assertDatabaseCount('anexe', 0);
    }
}
