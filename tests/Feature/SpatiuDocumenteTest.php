<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Contract;
use App\Models\Imobil;
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

    public function test_document_rows_are_hidden_for_liber_si_rezervat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $liber = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'L-1',
            'etaj' => 'Parter',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $rezervat = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'R-1',
            'etaj' => 'Fațadă',
            'status' => 'rezervat',
            'ordine' => 2,
        ]);

        $this->get(route('spatii.edit', $liber))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('showDocumente', false));

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

        $returnUrl = route('spatii.edit', $spatiu);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-NEW',
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'Chiriaș Test',
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => 'test@example.com',
                'telefon' => '',
            ],
            'data_start' => '2025-01-01',
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
}
