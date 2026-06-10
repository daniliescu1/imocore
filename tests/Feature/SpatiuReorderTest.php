<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatiuReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_spatiile_sunt_afisate_in_ordinea_introducerii(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil ordine',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj',
        ]);

        $primul = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $alDoilea = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'B',
            'status' => 'liber',
            'ordine' => 2,
        ]);

        $this->get(route('spatii.index', ['imobil_id' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('spatii.0.id', $primul->id)
                ->where('spatii.1.id', $alDoilea->id)
            );
    }

    public function test_spatiile_pot_fi_reordonate_in_cadrul_imobilului(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil reorder',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Sibiu',
        ]);

        $primul = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'NR 1',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $alDoilea = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'NR 2',
            'status' => 'liber',
            'ordine' => 2,
        ]);

        $this->from(route('spatii.index', ['imobil_id' => $imobil->id]))
            ->put(route('spatii.reorder'), [
                'imobil_id' => $imobil->id,
                'ordine' => [$alDoilea->id, $primul->id],
            ])
            ->assertRedirect(route('spatii.index', ['imobil_id' => $imobil->id]));

        $this->assertSame(2, $primul->fresh()->ordine);
        $this->assertSame(1, $alDoilea->fresh()->ordine);
    }
}
