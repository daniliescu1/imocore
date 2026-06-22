<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
