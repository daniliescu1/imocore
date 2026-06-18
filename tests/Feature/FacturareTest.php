<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\Contract;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacturareTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_facturate_and_nefacturate_counts(): void
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
            'identificator' => 'S1',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C1',
            'chirias' => 'Chiriaș test',
            'status' => 'activ',
        ]);

        $anexaFacturata = Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 100,
            'status' => 'draft',
        ]);

        Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-06',
            'total' => 120,
            'status' => 'draft',
        ]);

        Factura::query()->create([
            'anexa_id' => $anexaFacturata->id,
            'numar_factura' => 'FACT-000001',
            'total' => 1500,
            'status' => 'draft',
        ]);

        $this->get(route('facturare.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Facturare/Index')
                ->where('anexeFacturate', 1)
                ->where('anexeNefacturate', 1));
    }
}
