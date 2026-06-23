<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ModuleSpatiuSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_citiri_contoare_index_afiseaza_spatiile_cautate(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC118',
            'chirias' => 'Supermedical SRL',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 27,
            'pret_lunar' => 370,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/citiri-contoare?search=HQC118')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Index')
                ->where('filters.search', 'HQC118')
                ->where('filters.search_spatii', true)
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiu->id)
                ->has('imobile', 0)
            );
    }

    public function test_anexe_index_afiseaza_spatiile_cautate(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC118',
            'chirias' => 'Supermedical SRL',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 27,
            'pret_lunar' => 370,
            'moneda' => 'EUR',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/anexe?search=Supermedical')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anexe/Index')
                ->where('filters.search', 'Supermedical')
                ->where('filters.search_spatii', true)
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiu->id)
            );
    }

    public function test_citiri_contoare_index_filtrare_dupa_imobil(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/citiri-contoare?search=700+Office')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Index')
                ->where('filters.search_spatii', false)
                ->has('imobile', 1)
                ->where('imobile.0.id', $imobil->id)
            );
    }

    public function test_facturare_index_redirecteaza_la_imobil_cand_spatiul_este_intr_un_singur_imobil(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQC118',
            'chirias' => 'Supermedical SRL',
            'status' => 'inchiriat',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/facturare?search=HQC118')
            ->assertRedirect(route('facturare.imobil', [
                'imobil' => $imobil->id,
                'search_spatiu' => 'HQC118',
            ]));
    }

    public function test_facturare_index_afiseaza_spatiile_cand_sunt_in_mai_multe_imobile(): void
    {
        $imobilA = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $imobilB = Imobil::query()->create([
            'nume' => 'Conac 54',
            'strada' => 'Strada B',
            'numar' => '2',
            'localitate' => 'Dumbravita',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobilA->id,
            'identificator' => 'A101',
            'chirias' => 'Alpha SRL',
            'status' => 'inchiriat',
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobilB->id,
            'identificator' => 'B101',
            'chirias' => 'Beta SRL',
            'status' => 'inchiriat',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/facturare?search=101')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Facturare/Index')
                ->where('filters.search', '101')
                ->where('filters.search_spatii', true)
                ->has('spatii', 2)
            );
    }

    public function test_facturare_index_filtrare_dupa_imobil(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/facturare?search=700+Office')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Facturare/Index')
                ->where('filters.search_spatii', false)
            );
    }
}
