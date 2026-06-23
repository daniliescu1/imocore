<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnexaImobilBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_all_for_imobil_deletes_all_anexe(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil anexe',
            'strada' => 'Strada 4',
            'numar' => '4',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S4',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C4',
            'chirias' => 'Chiriaș anexe',
            'status' => 'activ',
        ]);

        foreach (['2026-05', '2026-06'] as $luna) {
            Anexa::query()->create([
                'contract_id' => $contract->id,
                'luna' => $luna,
                'total' => 100,
                'status' => 'draft',
            ]);
        }

        $this->delete(route('anexe.imobil.destroy-all', $imobil))
            ->assertRedirect(route('anexe.imobil', $imobil))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('anexe', 0);
    }

    public function test_imobil_page_filters_anexe_by_search(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada 4',
            'numar' => '4',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiuA = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E106',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $spatiuB = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQE103',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        $contractA = Contract::query()->create([
            'spatiu_id' => $spatiuA->id,
            'numar_contract' => 'Nr. 294 din 27.03.2025',
            'chirias' => 'CFB TRAININGS&DEVELOPMENT SRL',
            'status' => 'activ',
        ]);

        $contractB = Contract::query()->create([
            'spatiu_id' => $spatiuB->id,
            'numar_contract' => 'Nr. 235 din 20.12.2023',
            'chirias' => 'ZBOELECTRONICS SRL',
            'status' => 'activ',
        ]);

        Anexa::query()->create([
            'contract_id' => $contractA->id,
            'luna' => '2026-05',
            'total' => 319.29,
            'status' => 'draft',
        ]);

        Anexa::query()->create([
            'contract_id' => $contractB->id,
            'luna' => '2026-05',
            'total' => 1138.83,
            'status' => 'draft',
        ]);

        $this->get(route('anexe.imobil', ['imobil' => $imobil->id, 'search' => 'E106']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Anexe/Imobil')
                ->where('filters.search', 'E106')
                ->has('anexe', 1)
                ->where('anexe.0.spatiu', 'E106')
            );

        $this->get(route('anexe.imobil', ['imobil' => $imobil->id, 'search' => 'ZBOELECTRONICS']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('anexe', 1)
                ->where('anexe.0.chirias', 'ZBOELECTRONICS SRL')
            );
    }
}
