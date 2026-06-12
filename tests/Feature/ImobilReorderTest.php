<?php

namespace Tests\Feature;

use App\Models\Imobil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImobilReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_imobile_can_be_reordered(): void
    {
        $primul = Imobil::query()->create([
            'nume' => 'Imobil A',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $alDoilea = Imobil::query()->create([
            'nume' => 'Imobil B',
            'strada' => 'Strada 2',
            'numar' => '2',
            'localitate' => 'Timișoara',
            'ordine' => 2,
        ]);

        $this->from(route('imobile.index'))
            ->put(route('imobile.reorder'), [
                'ordine' => [$alDoilea->id, $primul->id],
            ])
            ->assertRedirect(route('imobile.index'));

        $this->assertSame(1, $alDoilea->fresh()->ordine);
        $this->assertSame(2, $primul->fresh()->ordine);
    }

    public function test_imobile_index_uses_ordine(): void
    {
        $primul = Imobil::query()->create([
            'nume' => 'Imobil A',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 2,
        ]);

        $alDoilea = Imobil::query()->create([
            'nume' => 'Imobil B',
            'strada' => 'Strada 2',
            'numar' => '2',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $this->get(route('imobile.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Imobile/Index')
                ->where('imobile.0.id', $alDoilea->id)
                ->where('imobile.1.id', $primul->id));
    }
}
