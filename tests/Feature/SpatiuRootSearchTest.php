<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatiuRootSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_cautarea_dupa_spatiu_pe_pagina_principala_afiseaza_spatiile_gasite(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E306',
            'chirias' => 'Yusen Logistic',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 27,
            'pret_lunar' => 216,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/spatii?search=306')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('imobil', null)
                ->where('filters.search', '306')
                ->where('filters.search_spatii', true)
                ->has('imobile', 0)
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiu->id)
                ->where('spatii.0.identificator', 'E306')
                ->where('spatii.0.chirias', 'Yusen Logistic')
            );
    }

    public function test_cautarea_dupa_imobil_pe_pagina_principala_ramane_pe_lista_de_imobile(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E101',
            'status' => 'liber',
            'suprafata_contractuala_mp' => 20,
            'pret_lunar' => 100,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/spatii?search=700+Office')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('filters.search', '700 Office')
                ->where('filters.search_spatii', false)
                ->has('spatii', 0)
                ->has('imobile', 1)
                ->where('imobile.0.id', $imobil->id)
            );
    }

    public function test_cautarea_globala_poate_filtra_si_dupa_documente(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E306',
            'chirias' => 'Yusen Logistic',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 27,
            'pret_lunar' => 216,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/spatii?search=Yusen&documente=fara_contract')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('filters.search_spatii', true)
                ->where('filters.documente', 'fara_contract')
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiu->id)
            );
    }
}
