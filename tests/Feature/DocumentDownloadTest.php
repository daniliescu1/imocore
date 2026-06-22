<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\AnexaLinie;
use App\Models\Contract;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\DocumentFormatter;
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

    public function test_factura_show_includes_full_anexa_detail(): void
    {
        $factura = $this->createFactura();

        $this->get(route('facturare.show', $factura))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('factura.anexa_detaliu.numar', '01')
                ->where('factura.anexa_detaliu.perioada_citire', '20.05.2026 - 25.05.2026')
                ->where('factura.anexa_detaliu.imobil.nume', '700 Office')
                ->where('factura.anexa_detaliu.spatiu.identificator', 'HQE 103')
                ->has('factura.anexa_detaliu.linii', 1));
    }

    public function test_factura_show_includes_download_url(): void
    {
        $factura = $this->createFactura();

        $this->get(route('facturare.show', $factura))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Facturare/Show')
                ->where('downloadUrl', route('facturare.download', $factura))
                ->where('factura.imobil.id', $factura->anexa->contract->spatiu->imobil_id));
    }

    public function test_anexa_show_includes_download_url(): void
    {
        $anexa = $this->createAnexa();

        $this->get(route('anexe.show', $anexa))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Anexe/Show')
                ->where('downloadUrl', route('anexe.download', $anexa))
                ->where('anexa.contract.email_facturare', 'facturare@zbo.ro'));
    }

    public function test_factura_pdf_has_invoice_on_first_page_and_annex_on_second(): void
    {
        $factura = $this->createFactura();

        $response = $this->get(route('facturare.download', $factura));
        $pdf = $response->getContent();

        $response->assertOk();
        $this->assertSame(2, $this->pdfPageCount($pdf));
    }

    public function test_factura_pdf_stays_two_pages_with_typical_invoice_lines(): void
    {
        $anexa = $this->createAnexa();

        $anexa->linii()->create([
            'denumire' => 'Apa pluviala',
            'tip_linie' => 'serviciu',
            'valoare' => 604.27,
            'tva_21' => 66.46,
            'ordine' => 2,
        ]);

        $anexa->linii()->create([
            'denumire' => 'Salubritate',
            'tip_linie' => 'serviciu',
            'valoare' => 0,
            'tva_21' => 0,
            'ordine' => 3,
        ]);

        $factura = Factura::query()->create([
            'anexa_id' => $anexa->id,
            'numar_factura' => 'FACT-FULL',
            'data_emitere' => '2026-06-18',
            'data_scadenta' => '2026-06-23',
            'curs_eur' => 5.0046,
            'chirie_eur' => 2500,
            'chirie_lei' => 12511.57,
            'penalitati' => 0,
            'total' => 20127.47,
            'status' => 'draft',
        ]);

        $response = $this->get(route('facturare.download', $factura));
        $pdf = $response->getContent();

        $response->assertOk();
        $this->assertSame(2, $this->pdfPageCount($pdf));
    }

    public function test_anexa_pdf_is_single_page_for_simple_annex(): void
    {
        $anexa = $this->createAnexa();

        $response = $this->get(route('anexe.download', $anexa));
        $pdf = $response->getContent();

        $response->assertOk();
        $this->assertSame(1, $this->pdfPageCount($pdf));
    }

    public function test_pdf_text_strips_romanian_diacritics(): void
    {
        $this->assertSame(
            'Chirie spatiu iunie 2026',
            DocumentFormatter::pdfText('Chirie spațiu iunie 2026'),
        );
        $this->assertSame(
            'Utilitati 21% TVA mai 2026',
            DocumentFormatter::pdfText('Utilități 21% TVA mai 2026'),
        );
        $this->assertSame('LUNA', DocumentFormatter::pdfText('LUNĂ'));
        $this->assertSame('Pret unitar', DocumentFormatter::pdfText('Preț unitar'));
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
            'chirias_tip' => 'pj',
            'chirias_date' => [
                'email' => 'contact@zbo.ro',
                'email_2' => 'facturare@zbo.ro',
            ],
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

    private function pdfPageCount(string $pdfBinary): int
    {
        preg_match_all('/\/Type\s*\/Page[^s]/', $pdfBinary, $matches);

        return count($matches[0]);
    }
}
