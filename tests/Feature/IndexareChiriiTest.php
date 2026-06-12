<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IndexareChiriiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_spatii_in_imobil_and_spatiu_order(): void
    {
        $imobilPrimul = Imobil::query()->create([
            'nume' => 'Imobil A',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 2,
        ]);

        $imobilAlDoilea = Imobil::query()->create([
            'nume' => 'Imobil B',
            'strada' => 'Strada 2',
            'numar' => '2',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiuB1 = Spatiu::query()->create([
            'imobil_id' => $imobilAlDoilea->id,
            'identificator' => 'B1',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 1,
            'chirias' => 'Chiriaș B',
        ]);

        $spatiuA1 = Spatiu::query()->create([
            'imobil_id' => $imobilPrimul->id,
            'identificator' => 'A1',
            'status' => 'liber',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $this->get(route('indexare-chirii.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IndexareChirii/Index')
                ->has('spatii', 2)
                ->where('spatii.0.id', $spatiuB1->id)
                ->where('spatii.0.imobil', 'Imobil B')
                ->where('spatii.0.identificator', 'B1')
                ->where('spatii.1.id', $spatiuA1->id)
                ->where('spatii.1.imobil', 'Imobil A'));
    }

    public function test_update_saves_indexare_2026(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S101',
            'status' => 'inchiriat',
            'pret_lunar' => 1000,
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $this->from(route('indexare-chirii.index'))
            ->patch(route('indexare-chirii.update', $spatiu), [
                'indexare_2026' => '1250.50',
            ])
            ->assertRedirect(route('indexare-chirii.index'));

        $this->assertSame('1250.50', $spatiu->fresh()->indexare_2026);
    }

    public function test_index_filters_by_search(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'HQ Office',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'TMI 1',
            'status' => 'inchiriat',
            'chirias' => 'Alpha SRL',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'TMI 2',
            'status' => 'liber',
            'chirias' => 'Beta SRL',
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        $this->get(route('indexare-chirii.index', ['search' => 'Alpha']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IndexareChirii/Index')
                ->has('spatii', 1)
                ->where('spatii.0.identificator', 'TMI 1'));
    }
}
