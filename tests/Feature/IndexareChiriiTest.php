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
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiuB1->id)
                ->where('spatii.0.imobil', 'Imobil B')
                ->where('spatii.0.identificator', 'B1'));
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

    public function test_index_includes_rezumat_for_inchiriate_count_and_indexate_count(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S1',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 100.5,
            'indexare_2026' => 1200,
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S2',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 50,
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S3',
            'status' => 'liber',
            'suprafata_contractuala_mp' => 999,
            'moneda' => 'EUR',
            'ordine' => 3,
        ]);

        $this->get(route('indexare-chirii.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IndexareChirii/Index')
                ->where('rezumat.spatii_inchiriate', 2)
                ->where('rezumat.spatii_indexate_an_curent', 1)
                ->where('rezumat.an_curent', (int) now()->format('Y')));
    }

    public function test_index_filters_by_indexare_status(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiuIndexat = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'IDX',
            'status' => 'inchiriat',
            'indexare_2026' => 1500,
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $spatiuNeindexat = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'NOIDX',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        $this->get(route('indexare-chirii.index', ['indexare' => 'indexate']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IndexareChirii/Index')
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiuIndexat->id)
                ->where('filters.indexare', 'indexate'));

        $this->get(route('indexare-chirii.index', ['indexare' => 'neindexate']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('IndexareChirii/Index')
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiuNeindexat->id)
                ->where('filters.indexare', 'neindexate'));
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
