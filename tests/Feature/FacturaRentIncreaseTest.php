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

    public function test_factura_afiseaza_utilitatile_grupate_pe_cote_tva(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil factura TVA',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-TVA',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-TVA',
            'chirias' => 'Chiriaș TVA',
            'data_start' => '2026-01-01',
            'chirie' => 1000,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $anexa = Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 379.21,
        ]);

        $anexa->linii()->create([
            'denumire' => 'Energie electrica',
            'tip_linie' => 'serviciu',
            'valoare' => 100,
            'tva_21' => 21,
            'ordine' => 1,
        ]);

        $anexa->linii()->create([
            'tip_linie' => 'header',
            'denumire' => '',
            'ordine' => 2,
        ]);

        $anexa->linii()->create([
            'denumire' => 'Apa pluviala',
            'tip_linie' => 'serviciu',
            'valoare' => 233.76,
            'tva_21' => 25.71,
            'ordine' => 3,
        ]);

        $factura = Factura::query()->create([
            'anexa_id' => $anexa->id,
            'numar_factura' => 'FACT-TVA',
            'curs_eur' => 5,
            'chirie_eur' => 1000,
            'chirie_lei' => 5000,
            'total' => 5379.21,
            'penalitati' => 0,
            'status' => 'draft',
        ]);

        $this->get(route('facturare.show', $factura))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('factura.linii.1.nr_crt', 2)
                ->where('factura.linii.1.denumire', 'Utilități 21% mai')
                ->where('factura.linii.1.valoare', 100)
                ->where('factura.linii.1.tva', 21)
                ->where('factura.linii.2.nr_crt', 3)
                ->where('factura.linii.2.denumire', 'Utilități 11% mai')
                ->where('factura.linii.2.valoare', 233.76)
                ->where('factura.linii.2.tva', 25.71)
                ->where('factura.linii.3.nr_crt', 4)
                ->where('factura.linii.3.denumire', 'Penalități')
            );
    }
}
