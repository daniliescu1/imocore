<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatiuIndexareTest extends TestCase
{
    use RefreshDatabase;

    public function test_indexarile_se_salveaza_pe_spatiu(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil indexare',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S1',
            'suprafata_contractuala_mp' => '10',
            'status' => 'liber',
            'moneda' => 'EUR',
            'indexare_2026' => '140.75',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S1')->firstOrFail();

        $this->assertSame('140.75', $spatiu->indexare_2026);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->where('spatiu.indexare_2026', '140.75')
            );

        $this->get(route('spatii.index', ['imobil_id' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('spatii.0.chirie_lunara_curenta', '140.75')
                ->where('spatii.0.sursa_chirie_curenta', 'Indexare 2026')
                ->where('spatii.0.pret_mp_curent', '14.08')
            );
    }

    public function test_suprafata_si_chirie_accepta_virgula_ca_separator_zecimal(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil virgula',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S2',
            'suprafata_contractuala_mp' => '598,31',
            'pret_lunar' => '1407,50',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'indexare_2026' => '1500,25',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S2')->firstOrFail();

        $this->assertSame('598.31', $spatiu->suprafata_contractuala_mp);
        $this->assertSame('1407.50', $spatiu->pret_lunar);
        $this->assertSame('1500.25', $spatiu->indexare_2026);
    }

    public function test_de_lamurit_se_salveaza_si_apare_in_lista(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil lamurit',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S3',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'de_lamurit' => true,
            'de_lamurit_detaliu' => 'Suprafata de confirmat cu locatorul',
            'observatii' => 'Acces din curte',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S3')->firstOrFail();

        $this->assertTrue($spatiu->de_lamurit);
        $this->assertSame('Suprafata de confirmat cu locatorul', $spatiu->de_lamurit_detaliu);
        $this->assertSame('Acces din curte', $spatiu->observatii);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->where('spatiu.de_lamurit', true)
                ->where('spatiu.de_lamurit_detaliu', 'Suprafata de confirmat cu locatorul')
            );

        $this->get(route('spatii.index', ['imobil_id' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('spatii.0.de_lamurit', true)
                ->where('spatii.0.de_lamurit_detaliu', 'Suprafata de confirmat cu locatorul')
            );

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S3',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'de_lamurit' => false,
            'de_lamurit_detaliu' => 'ignorat',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu->refresh();
        $this->assertFalse($spatiu->de_lamurit);
        $this->assertNull($spatiu->de_lamurit_detaliu);
    }

    public function test_de_lamurit_se_salveaza_automat_la_toggle(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil toggle',
            'strada' => 'Strada Test',
            'numar' => '4',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S4',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'de_lamurit' => false,
        ]);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.marcaj', $spatiu), ['field' => 'de_lamurit', 'value' => true])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $this->assertTrue($spatiu->fresh()->de_lamurit);

        $this->get(route('spatii.index', ['imobil_id' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('spatii.0.de_lamurit', true)
            );
    }

    public function test_de_lamurit_functioneaza_si_pentru_spatiu_administrativ(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil admin lamurit',
            'strada' => 'Strada Test',
            'numar' => '5',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'ADM1',
            'status' => 'administrativ',
            'moneda' => 'EUR',
            'de_lamurit' => false,
        ]);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.marcaj', $spatiu), ['field' => 'de_lamurit', 'value' => true])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $this->assertTrue($spatiu->fresh()->de_lamurit);
    }

    public function test_marcajele_sunt_mutual_exclusive(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil marcaje',
            'strada' => 'Strada Test',
            'numar' => '6',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S6',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'marcat_galben' => true,
            'marcat_verde' => true,
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S6')->firstOrFail();

        $this->assertTrue($spatiu->marcat_galben);
        $this->assertFalse($spatiu->marcat_verde);
        $this->assertFalse($spatiu->de_lamurit);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.marcaj', $spatiu), ['field' => 'marcat_verde', 'value' => true])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $spatiu->refresh();
        $this->assertFalse($spatiu->marcat_galben);
        $this->assertTrue($spatiu->marcat_verde);
        $this->assertFalse($spatiu->de_lamurit);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.marcaj', $spatiu), ['field' => 'de_lamurit', 'value' => true])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $spatiu->refresh();
        $this->assertFalse($spatiu->marcat_galben);
        $this->assertFalse($spatiu->marcat_verde);
        $this->assertTrue($spatiu->de_lamurit);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.marcaj', $spatiu), ['field' => 'de_lamurit', 'value' => false])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $spatiu->refresh();
        $this->assertFalse($spatiu->marcat_galben);
        $this->assertFalse($spatiu->marcat_verde);
        $this->assertFalse($spatiu->de_lamurit);
    }

    public function test_de_lamurit_toggle_sterge_detaliul(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil toggle detaliu',
            'strada' => 'Strada Test',
            'numar' => '7',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S7',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'de_lamurit' => true,
            'de_lamurit_detaliu' => 'Chirie de verificat',
        ]);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.marcaj', $spatiu), ['field' => 'de_lamurit', 'value' => false])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $spatiu->refresh();
        $this->assertFalse($spatiu->de_lamurit);
        $this->assertNull($spatiu->de_lamurit_detaliu);
    }

    public function test_marcaj_galben_sterge_detaliul_de_lamurit(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil marcaj switch',
            'strada' => 'Strada Test',
            'numar' => '8',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S8',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'de_lamurit' => true,
            'de_lamurit_detaliu' => 'De clarificat',
        ]);

        $this->from(route('spatii.edit', $spatiu))
            ->patch(route('spatii.marcaj', $spatiu), ['field' => 'marcat_galben', 'value' => true])
            ->assertRedirect(route('spatii.edit', $spatiu));

        $spatiu->refresh();
        $this->assertTrue($spatiu->marcat_galben);
        $this->assertFalse($spatiu->de_lamurit);
        $this->assertNull($spatiu->de_lamurit_detaliu);
    }

    public function test_acoperis_si_fatada_au_zero_persoane(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil etaj',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        foreach (['Acoperiș', 'Fațadă', 'Parcare'] as $etaj) {
            $this->post(route('spatii.store'), [
                'imobil_id' => $imobil->id,
                'identificator' => $etaj,
                'suprafata_contractuala_mp' => '160',
                'etaj' => $etaj,
                'status' => 'liber',
                'moneda' => 'EUR',
            ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

            $spatiu = Spatiu::query()->where('identificator', $etaj)->firstOrFail();

            $this->assertSame(0, $spatiu->persoane_declarate);
            $this->assertSame(0, $spatiu->persoane_standard);
            $this->assertSame(0, $spatiu->persoanePentruAnexa());
            $this->assertSame('neincalzit', $spatiu->regim_incalzire);
            $this->assertNull($spatiu->configurare_anexa_id);
        }
    }

    public function test_lista_indica_daca_spatiul_are_anexa_alocata(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil anexa',
            'strada' => 'Strada Test',
            'numar' => '7',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'implicit' => true,
            'activ' => true,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Fara anexa',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Cu anexa',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'configurare_anexa_id' => $configurare->id,
        ]);

        $this->get(route('spatii.index', ['imobil_id' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('spatii.0.identificator', 'Fara anexa')
                ->where('spatii.0.are_anexa_alocata', false)
                ->where('spatii.1.identificator', 'Cu anexa')
                ->where('spatii.1.are_anexa_alocata', true)
            );
    }

    public function test_lista_indica_daca_spatiul_are_contract_activ(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil contract',
            'strada' => 'Strada Test',
            'numar' => '8',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiuComplet = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Complet',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'configurare_anexa_id' => $configurare->id,
        ]);

        $spatiuFaraContract = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Fara contract',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'configurare_anexa_id' => $configurare->id,
        ]);

        $spatiuFaraAnexa = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Fara anexa',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        $spatiuFaraAmbele = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Fara ambele',
            'status' => 'liber',
            'moneda' => 'EUR',
        ]);

        \App\Models\Contract::query()->create([
            'spatiu_id' => $spatiuComplet->id,
            'numar_contract' => 'C-1',
            'chirias' => 'Chiriaș activ',
            'data_start' => '2025-01-01',
            'chirie' => 1000,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        \App\Models\Contract::query()->create([
            'spatiu_id' => $spatiuFaraAnexa->id,
            'numar_contract' => 'C-2',
            'chirias' => 'Chiriaș cu contract',
            'data_start' => '2025-01-01',
            'chirie' => 900,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        \App\Models\Contract::query()->create([
            'spatiu_id' => $spatiuFaraContract->id,
            'numar_contract' => 'C-3',
            'chirias' => 'Chiriaș incomplet',
            'data_start' => '2025-01-01',
            'chirie' => 800,
            'moneda' => 'EUR',
            'status' => 'incomplet',
        ]);

        $this->get(route('spatii.index', ['imobil_id' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('spatii', fn ($spatii) => collect($spatii)->firstWhere('identificator', 'Complet')['are_contract_activ'] === true
                    && collect($spatii)->firstWhere('identificator', 'Complet')['are_anexa_alocata'] === true
                    && collect($spatii)->firstWhere('identificator', 'Fara contract')['are_contract_activ'] === false
                    && collect($spatii)->firstWhere('identificator', 'Fara contract')['are_anexa_alocata'] === true
                    && collect($spatii)->firstWhere('identificator', 'Fara anexa')['are_contract_activ'] === true
                    && collect($spatii)->firstWhere('identificator', 'Fara anexa')['are_anexa_alocata'] === false
                    && collect($spatii)->firstWhere('identificator', 'Fara ambele')['are_contract_activ'] === false
                    && collect($spatii)->firstWhere('identificator', 'Fara ambele')['are_anexa_alocata'] === false)
            );
    }
}
