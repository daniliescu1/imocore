<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\AnexaLinie;
use App\Models\Contract;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_factura_can_be_downloaded_as_pdf(): void
    {
        $factura = $this->createFactura();

        $this->get(route('facturare.download', $factura))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('Factura-ZBOELECTRONICS-SRL-18-06-2026.pdf');
    }

    public function test_anexa_can_be_downloaded_as_pdf(): void
    {
        $anexa = $this->createAnexa();

        $this->get(route('anexe.download', $anexa))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('Anexa-ZBOELECTRONICS-SRL-Mai-2026.pdf');
    }

    public function test_factura_show_includes_download_url(): void
    {
        $factura = $this->createFactura();

        $this->get(route('facturare.show', $factura))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facturare/Show')
                ->where('downloadUrl', route('facturare.download', $factura)));
    }

    public function test_anexa_show_includes_download_url(): void
    {
        $anexa = $this->createAnexa();

        $this->get(route('anexe.show', $anexa))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Anexe/Show')
                ->where('downloadUrl', route('anexe.download', $anexa)));
    }

    private function createFactura(): Factura
    {
        $anexa = $this->createAnexa();

        return Factura::query()->create([
            'anexa_id' => $anexa->id,
            'numar_factura' => 'FACT-000001',
            'data_emitere' => '2026-06-18',
            'data_scadenta' => '2026-06-23',
            'curs_eur' => 5,
            'chirie_eur' => 2500,
            'chirie_lei' => 12500,
            'penalitati' => 0,
            'total' => 16700,
            'status' => 'draft',
        ]);
    }

    private function createAnexa(): Anexa
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Gheorghe Lazar',
            'numar' => '9',
            'localitate' => 'Timisoara',
            'ordine' => 1,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQE 103',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'Nr. 366 din 27.05.2026',
            'chirias' => 'ZBOELECTRONICS SRL',
            'status' => 'activ',
        ]);

        $anexa = Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 273.43,
            'status' => 'draft',
        ]);

        AnexaLinie::query()->create([
            'anexa_id' => $anexa->id,
            'ordine' => 1,
            'tip_linie' => 'serviciu',
            'nr_crt' => 1,
            'denumire' => 'Energie Electrica',
            'um' => 'kwh',
            'cantitate' => 120,
            'pret_unitar' => 1.2,
            'valoare' => 144,
            'tva_21' => 30.24,
        ]);

        return $anexa;
    }
}
