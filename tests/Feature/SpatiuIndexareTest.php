<?php

namespace Tests\Feature;

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
            'indexare_2025' => '125.50',
            'indexare_2026' => '140.75',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S1')->firstOrFail();

        $this->assertSame('125.50', $spatiu->indexare_2025);
        $this->assertSame('140.75', $spatiu->indexare_2026);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->where('spatiu.indexare_2025', '125.50')
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
            'indexare_2025' => '1500,25',
        ])->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $spatiu = Spatiu::query()->where('identificator', 'S2')->firstOrFail();

        $this->assertSame('598.31', $spatiu->suprafata_contractuala_mp);
        $this->assertSame('1407.50', $spatiu->pret_lunar);
        $this->assertSame('1500.25', $spatiu->indexare_2025);
    }
}
