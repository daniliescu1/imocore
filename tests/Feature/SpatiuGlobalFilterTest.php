<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatiuGlobalFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_liber_fara_imobil_afiseaza_toate_spatiile_libere(): void
    {
        $imobilA = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $imobilB = Imobil::query()->create([
            'nume' => 'Plaza',
            'strada' => 'Strada B',
            'numar' => '2',
            'localitate' => 'Timișoara',
            'ordine' => 2,
        ]);

        $liberA = Spatiu::query()->create([
            'imobil_id' => $imobilA->id,
            'identificator' => 'A1',
            'status' => 'liber',
            'suprafata_contractuala_mp' => 50,
            'pret_lunar' => 500,
            'moneda' => 'EUR',
        ]);

        $liberB = Spatiu::query()->create([
            'imobil_id' => $imobilB->id,
            'identificator' => 'B1',
            'status' => 'liber',
            'suprafata_contractuala_mp' => 40,
            'pret_lunar' => 400,
            'moneda' => 'EUR',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobilA->id,
            'identificator' => 'A2',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 60,
            'pret_lunar' => 600,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/spatii?status=liber')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('imobil', null)
                ->where('filters.status', 'liber')
                ->where('filters.global', true)
                ->has('spatii', 2)
                ->where('spatii.0.id', $liberA->id)
                ->where('spatii.1.id', $liberB->id)
            );
    }

    public function test_status_liber_global_poate_filtra_dupa_etaj(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $parter = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'P1',
            'status' => 'liber',
            'etaj' => 'Parter',
            'suprafata_contractuala_mp' => 50,
            'pret_lunar' => 500,
            'moneda' => 'EUR',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E1',
            'status' => 'liber',
            'etaj' => '1',
            'suprafata_contractuala_mp' => 40,
            'pret_lunar' => 400,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/spatii?status=liber&etaj=Parter')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('filters.etaj', 'Parter')
                ->where('filters.global', true)
                ->has('spatii', 1)
                ->where('spatii.0.id', $parter->id)
            );
    }

    public function test_global_fara_status_afiseaza_toate_spatiile(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $liber = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'L1',
            'status' => 'liber',
            'suprafata_contractuala_mp' => 50,
            'pret_lunar' => 500,
            'moneda' => 'EUR',
        ]);

        $inchiriat = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'I1',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 60,
            'pret_lunar' => 600,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/spatii?global=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('imobil', null)
                ->where('filters.global', true)
                ->where('filters.status', '')
                ->has('spatii', 2)
                ->where('spatii.0.id', $liber->id)
                ->where('spatii.1.id', $inchiriat->id)
            );
    }
}
