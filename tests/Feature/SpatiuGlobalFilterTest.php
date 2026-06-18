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
                ->has('spatii', 2)
                ->where('spatii.0.id', $liberA->id)
                ->where('spatii.1.id', $liberB->id)
            );
    }
}
