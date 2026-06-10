<?php

namespace Tests\Feature;

use App\Models\Imobil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImobilCampuriSpatiuTest extends TestCase
{
    use RefreshDatabase;

    public function test_campurile_vizibile_pentru_spatiu_se_salveaza_pe_imobil(): void
    {
        $this->post(route('imobile.store'), [
            'nume' => 'Imobil configurare spatiu',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj-Napoca',
            'campuri_spatiu_vizibile' => [
                'suprafata_contractuala_mp',
                'corp',
                'chirias',
            ],
        ])->assertRedirect('/imobile');

        $imobil = Imobil::query()->where('nume', 'Imobil configurare spatiu')->firstOrFail();

        $this->assertSame([
            'suprafata_contractuala_mp',
            'corp',
            'chirias',
        ], $imobil->campuri_spatiu_vizibile);

        $this->get(route('imobile.edit', $imobil))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imobile/Create')
                ->where('imobil.campuri_spatiu_vizibile.0', 'suprafata_contractuala_mp')
                ->where('imobil.campuri_spatiu_vizibile.1', 'corp')
                ->where('imobil.campuri_spatiu_vizibile.2', 'chirias')
                ->where('campuriSpatiuConfigurabile.0.key', 'suprafata_contractuala_mp')
            );
    }

    public function test_formularul_de_spatiu_primeste_campurile_vizibile_per_imobil(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil cu formular custom',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Sibiu',
            'campuri_spatiu_vizibile' => [
                'etaj',
                'pret_lunar',
            ],
        ]);

        $this->get(route('spatii.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->where("campuriSpatiuVizibile.{$imobil->id}.0", 'etaj')
                ->where("campuriSpatiuVizibile.{$imobil->id}.1", 'pret_lunar')
            );
    }
}
