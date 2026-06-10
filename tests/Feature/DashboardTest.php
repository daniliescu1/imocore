<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_vede_total_suma_si_suprafata_la_spatii_inchiriate(): void
    {
        $this->seedInchiriate();

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.inchiriate', 2)
                ->where('stats.inchiriate_suma', 2907.5)
                ->where('stats.inchiriate_mp', 205)
                ->where('isOwner', true)
            );
    }

    public function test_non_owner_nu_primeste_totalurile_spatiilor_inchiriate(): void
    {
        $this->seedInchiriate();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.inchiriate', 2)
                ->missing('stats.inchiriate_suma')
                ->missing('stats.inchiriate_mp')
                ->where('isOwner', false)
            );
    }

    public function test_total_chirii_include_doar_spatiile_inchiriate(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil filtru',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'INCH',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 100,
            'pret_lunar' => 1000,
            'indexare_2026' => 1200,
            'moneda' => 'EUR',
        ]);

        foreach ([
            ['identificator' => 'LIB', 'status' => 'liber', 'pret_lunar' => 900, 'indexare_2026' => 1100],
            ['identificator' => 'REZ', 'status' => 'rezervat', 'pret_lunar' => 800, 'indexare_2025' => 950],
            ['identificator' => 'COM', 'status' => 'comun', 'pret_lunar' => 700, 'indexare_2026' => 850],
            ['identificator' => 'ADM', 'status' => 'administrativ', 'pret_lunar' => 600, 'indexare_2025' => 750],
        ] as $spatiu) {
            Spatiu::query()->create([
                'imobil_id' => $imobil->id,
                'identificator' => $spatiu['identificator'],
                'status' => $spatiu['status'],
                'suprafata_contractuala_mp' => 50,
                'pret_lunar' => $spatiu['pret_lunar'],
                'indexare_2025' => $spatiu['indexare_2025'] ?? null,
                'indexare_2026' => $spatiu['indexare_2026'] ?? null,
                'moneda' => 'EUR',
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'owner']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.inchiriate', 1)
                ->where('stats.inchiriate_suma', 1200)
                ->where('stats.inchiriate_mp', 100)
            );
    }

    private function seedInchiriate(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil dashboard',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S1',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 120,
            'pret_lunar' => 1200,
            'indexare_2026' => 1500,
            'moneda' => 'EUR',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S2',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 85,
            'pret_lunar' => 850,
            'indexare_2025' => 1407.5,
            'moneda' => 'EUR',
        ]);
    }
}
