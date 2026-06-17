<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\Contract;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacturaRentIncreaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_facturarea_foloseste_chiria_crescuta_pentru_luna_aplicabila(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil facturare',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiuInainte = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-INAINTE',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        $spatiuDupa = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-DUPA',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        $contractInainte = Contract::query()->create([
            'spatiu_id' => $spatiuInainte->id,
            'numar_contract' => 'C-INAINTE',
            'chirias' => 'Chiriaș înainte',
            'data_start' => '2026-03-17',
            'data_end' => '2028-04-30',
            'chirie' => 1600,
            'crestere_chirie_la' => 1800,
            'data_crestere_chirie' => '2028-01-22',
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $contractDupa = Contract::query()->create([
            'spatiu_id' => $spatiuDupa->id,
            'numar_contract' => 'C-DUPA',
            'chirias' => 'Chiriaș după',
            'data_start' => '2026-03-17',
            'data_end' => '2028-04-30',
            'chirie' => 1600,
            'crestere_chirie_la' => 1800,
            'data_crestere_chirie' => '2028-01-22',
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Anexa::query()->create([
            'contract_id' => $contractInainte->id,
            'luna' => '2027-11',
            'total' => 100,
        ]);

        Anexa::query()->create([
            'contract_id' => $contractDupa->id,
            'luna' => '2027-12',
            'total' => 100,
        ]);

        $this->post(route('facturare.generate'), [
            'curs_eur' => 5,
        ])->assertRedirect('/facturare');

        $facturi = Factura::query()
            ->with('anexa.contract')
            ->get()
            ->keyBy(fn (Factura $factura): string => $factura->anexa->contract->numar_contract);

        $this->assertSame('1600.00', $facturi['C-INAINTE']->chirie_eur);
        $this->assertSame('8000.00', $facturi['C-INAINTE']->chirie_lei);
        $this->assertSame('1800.00', $facturi['C-DUPA']->chirie_eur);
        $this->assertSame('9000.00', $facturi['C-DUPA']->chirie_lei);
    }
}
