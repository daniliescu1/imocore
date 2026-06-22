<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatiuStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_spatiul_liber_are_regim_incalzire_integral_implicit(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil regim implicit',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S-LIBER',
            'status' => 'liber',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $this->assertSame('integral', Spatiu::query()->where('identificator', 'S-LIBER')->value('regim_incalzire'));
    }

    public function test_spatiul_administrativ_curata_campurile_comerciale(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil administrativ',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $locator = Locator::query()->create(['nume' => 'Locator Test']);
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'implicit' => true,
            'activ' => true,
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S-ADM',
            'status' => 'administrativ',
            'regim_incalzire' => 'integral',
            'locator_id' => $locator->id,
            'configurare_anexa_id' => $configurare->id,
            'chirias' => 'Nu ar trebui salvat',
            'indexare_2026' => '120',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S-ADM')->firstOrFail();

        $this->assertSame('administrativ', $spatiu->status);
        $this->assertSame('neincalzit', $spatiu->regim_incalzire);
        $this->assertNull($spatiu->locator_id);
        $this->assertNull($spatiu->configurare_anexa_id);
        $this->assertNull($spatiu->chirias);
        $this->assertNull($spatiu->indexare_2026);
    }

    public function test_spatiul_comun_are_zero_persoane_si_fara_locator_anexa(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil comun',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Timișoara',
        ]);

        $locator = Locator::query()->create(['nume' => 'Locator Test']);
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'implicit' => true,
            'activ' => true,
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S-COMUN',
            'suprafata_contractuala_mp' => '14.6',
            'status' => 'comun',
            'regim_incalzire' => 'integral',
            'locator_id' => $locator->id,
            'configurare_anexa_id' => $configurare->id,
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S-COMUN')->firstOrFail();

        $this->assertSame('comun', $spatiu->status);
        $this->assertSame('neincalzit', $spatiu->regim_incalzire);
        $this->assertNull($spatiu->locator_id);
        $this->assertNull($spatiu->configurare_anexa_id);
        $this->assertSame(0, $spatiu->persoane_declarate);
        $this->assertSame(0, $spatiu->persoane_standard);
        $this->assertSame(0, $spatiu->persoanePentruAnexa());
    }

    public function test_spatiul_care_devine_liber_promoveaza_indexarea_mai_mare_in_pret_lunar(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil liber indexare',
            'strada' => 'Strada Test',
            'numar' => '4',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S-INDEX',
            'status' => 'inchiriat',
            'pret_lunar' => 1000,
            'indexare_2026' => 1500,
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S-INDEX',
            'status' => 'liber',
            'pret_lunar' => 1000,
            'indexare_2026' => 1500,
            'moneda' => 'EUR',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu->refresh();

        $this->assertSame('liber', $spatiu->status);
        $this->assertSame('1500.00', $spatiu->pret_lunar);
        $this->assertNull($spatiu->indexare_2026);
    }

    public function test_spatiul_care_devine_liber_pastreaza_pret_lunar_cand_indexarea_nu_este_mai_mare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil liber pret',
            'strada' => 'Strada Test',
            'numar' => '5',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S-PRET',
            'status' => 'inchiriat',
            'pret_lunar' => 1200,
            'indexare_2026' => 900,
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S-PRET',
            'status' => 'liber',
            'pret_lunar' => 1200,
            'indexare_2026' => 900,
            'moneda' => 'EUR',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu->refresh();

        $this->assertSame('liber', $spatiu->status);
        $this->assertSame('1200.00', $spatiu->pret_lunar);
        $this->assertNull($spatiu->indexare_2026);
    }
}
