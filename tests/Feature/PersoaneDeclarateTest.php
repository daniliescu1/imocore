<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersoaneDeclarateTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_spatii_inchiriate_with_persoane_fields(): void
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
            'suprafata_contractuala_mp' => 45,
            'persoane_declarate' => 6,
            'chirias' => 'Chiriaș test',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S102',
            'status' => 'liber',
            'ordine' => 2,
        ]);

        $this->get(route('persoane-declarate.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('PersoaneDeclarate/Index')
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiu->id)
                ->where('spatii.0.persoane_calculate_automat', 5)
                ->where('spatii.0.persoane_declarate', 6));
    }

    public function test_update_saves_persoane_declarate_on_spatiu(): void
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
            'suprafata_contractuala_mp' => 45,
            'ordine' => 1,
        ]);

        $this->from(route('persoane-declarate.index'))
            ->patch(route('persoane-declarate.update', $spatiu), [
                'persoane_declarate' => 4,
            ])
            ->assertRedirect(route('persoane-declarate.index'));

        $this->assertSame(4, $spatiu->fresh()->persoane_declarate);
    }

    public function test_contract_form_reads_persoane_declarate_updated_from_page(): void
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
            'suprafata_contractuala_mp' => 45,
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-101',
            'chirias' => 'Chiriaș test',
            'status' => 'activ',
        ]);

        $this->patch(route('persoane-declarate.update', $spatiu), [
            'persoane_declarate' => 7,
        ])->assertRedirect();

        $this->get(route('contracte.edit', $contract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contract.persoane_declarate', 7));
    }

    public function test_index_can_filter_by_persoane_declarate(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'strada' => 'Strada 1',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiuCuPersoane = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S101',
            'status' => 'inchiriat',
            'persoane_declarate' => 3,
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S102',
            'status' => 'inchiriat',
            'persoane_declarate' => null,
            'ordine' => 2,
        ]);

        $this->get(route('persoane-declarate.index', ['declarate' => 'declarate']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('spatii', 1)
                ->where('spatii.0.id', $spatiuCuPersoane->id));

        $this->get(route('persoane-declarate.index', ['declarate' => 'ne_declarate']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('spatii', 1)
                ->where('spatii.0.identificator', 'S102'));
    }
}
