<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatiuParcareTest extends TestCase
{
    use RefreshDatabase;

    public function test_parcare_salveaza_moneda_ron(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Parking',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'P-01',
            'etaj' => 'Parcare',
            'status' => 'inchiriat',
            'pret_lunar' => '350',
            'moneda' => 'RON',
            'chirias' => 'Test SRL',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'P-01')->firstOrFail();

        $this->assertSame('Parcare', $spatiu->etaj);
        $this->assertSame('RON', $spatiu->moneda);
        $this->assertSame('350.00', $spatiu->pret_lunar);
        $this->assertSame(0, $spatiu->persoane_declarate);
        $this->assertSame('neincalzit', $spatiu->regim_incalzire);
    }

    public function test_parcare_poate_folosi_eur(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Parking',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'P-02',
            'etaj' => 'Parcare',
            'status' => 'liber',
            'pret_lunar' => '75',
            'moneda' => 'EUR',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $this->assertSame('EUR', Spatiu::query()->where('identificator', 'P-02')->value('moneda'));
    }

    public function test_parcare_fara_moneda_explicita_default_ron(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Parking',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'P-03',
            'etaj' => 'Parcare',
            'status' => 'liber',
            'pret_lunar' => '350',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $this->assertSame('RON', Spatiu::query()->where('identificator', 'P-03')->value('moneda'));
    }

    public function test_biroul_forteaza_moneda_eur(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil birou',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Timișoara',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S101',
            'etaj' => 'Parter',
            'status' => 'inchiriat',
            'pret_lunar' => '1200',
            'moneda' => 'RON',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $this->assertSame('EUR', Spatiu::query()->where('identificator', 'S101')->value('moneda'));
    }

    public function test_edit_page_arata_moneda_pentru_parcare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Parking',
            'strada' => 'Strada Test',
            'numar' => '4',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'P-03',
            'etaj' => 'Parcare',
            'status' => 'inchiriat',
            'pret_lunar' => 400,
            'moneda' => 'RON',
        ]);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->where('spatiu.etaj', 'Parcare')
                ->where('spatiu.moneda', 'RON')
            );
    }
}
