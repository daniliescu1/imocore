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

    public function test_generate_creates_rent_only_invoice_for_contract_without_anexa(): void
    {
        $this->travelTo('2026-06-15');

        $imobil = Imobil::query()->create([
            'nume' => 'Imobil parcare',
            'strada' => 'Strada P',
            'numar' => '1',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'P-12',
            'etaj' => 'Parcare',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'pret_lunar' => 290,
            'ordine' => 1,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-PARC',
            'chirias' => 'PARC SRL',
            'data_start' => '2026-05-01',
            'chirie' => 250,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->post(route('facturare.generate'), [
            'imobil_id' => $imobil->id,
        ])->assertRedirect(route('facturare.imobil', $imobil));

        $this->assertDatabaseCount('facturi', 1);
        $this->assertDatabaseHas('facturi', [
            'anexa_id' => null,
            'luna' => '2026-05',
            'chirie_eur' => 290,
            'status' => 'draft',
        ]);

        $factura = Factura::query()->firstOrFail();

        $this->get(route('facturare.show', $factura))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Facturare/Show')
                ->where('factura.luna', '2026-05')
                ->where('factura.contract.numar', 'C-PARC')
                ->where('factura.spatiu.identificator', 'P-12')
                ->has('factura.linii', 2)
                ->where('factura.linii.0.denumire', 'Chirie spațiu iunie 2026 · 290,00 EUR/lună')
                ->where('factura.linii.1.denumire', 'Penalități')
            );

        $this->post(route('facturare.generate'), [
            'imobil_id' => $imobil->id,
        ])->assertRedirect(route('facturare.imobil', $imobil));

        $this->assertDatabaseCount('facturi', 1);
    }

    public function test_destroy_all_for_imobil_deletes_all_facturi(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil bulk',
            'strada' => 'Strada 2',
            'numar' => '2',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S2',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C2',
            'chirias' => 'Chiriaș bulk',
            'status' => 'activ',
        ]);

        foreach (['2026-05', '2026-06'] as $luna) {
            $anexa = Anexa::query()->create([
                'contract_id' => $contract->id,
                'luna' => $luna,
                'total' => 100,
                'status' => 'draft',
            ]);

            Factura::query()->create([
                'anexa_id' => $anexa->id,
                'numar_factura' => "FACT-{$luna}",
                'total' => 1500,
                'status' => 'draft',
            ]);
        }

        $this->delete(route('facturare.imobil.destroy-all', $imobil))
            ->assertRedirect(route('facturare.imobil', $imobil))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('facturi', 0);
    }

    public function test_destroy_all_for_imobil_respects_filters(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil filtru',
            'strada' => 'Strada 3',
            'numar' => '3',
            'localitate' => 'Timișoara',
            'ordine' => 1,
        ]);

        $spatiuA = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A-1',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $spatiuB = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'B-1',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 2,
        ]);

        $contractA = Contract::query()->create([
            'spatiu_id' => $spatiuA->id,
            'numar_contract' => 'CA',
            'chirias' => 'Alpha SRL',
            'status' => 'activ',
        ]);

        $contractB = Contract::query()->create([
            'spatiu_id' => $spatiuB->id,
            'numar_contract' => 'CB',
            'chirias' => 'Beta SRL',
            'status' => 'activ',
        ]);

        foreach ([$contractA, $contractB] as $contract) {
            $anexa = Anexa::query()->create([
                'contract_id' => $contract->id,
                'luna' => '2026-05',
                'total' => 100,
                'status' => 'draft',
            ]);

            Factura::query()->create([
                'anexa_id' => $anexa->id,
                'numar_factura' => "FACT-{$contract->numar_contract}",
                'total' => 1500,
                'status' => 'draft',
            ]);
        }

        $this->delete(route('facturare.imobil.destroy-all', $imobil), [
            'search_chirias' => 'Alpha',
        ])->assertRedirect(route('facturare.imobil', [
            'imobil' => $imobil->id,
            'search_chirias' => 'Alpha',
        ]))->assertSessionHas('success');

        $this->assertDatabaseCount('facturi', 1);
        $this->assertDatabaseHas('facturi', [
            'numar_factura' => 'FACT-CB',
        ]);
    }
}
