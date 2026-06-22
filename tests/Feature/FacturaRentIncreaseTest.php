<?php

namespace Tests\Feature;

use App\Models\Anexa;
use App\Models\ConfigurareAnexaImobil;
use App\Models\Contract;
use App\Models\Factura;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacturaRentIncreaseTest extends TestCase
{
    use RefreshDatabase;

    private function ataseazaConfigurareAnexa(Spatiu $spatiu): ConfigurareAnexaImobil
    {
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $spatiu->imobil_id,
            'denumire' => 'Anexă test',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiu->update(['configurare_anexa_id' => $configurare->id]);

        return $configurare;
    }

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
        $this->ataseazaConfigurareAnexa($spatiuInainte);

        $spatiuDupa = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-DUPA',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);
        $this->ataseazaConfigurareAnexa($spatiuDupa);

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
        ])->assertRedirect(route('facturare.index'));

        $facturi = Factura::query()
            ->with('anexa.contract')
            ->get()
            ->keyBy(fn (Factura $factura): string => $factura->anexa->contract->numar_contract);

        $this->assertSame('1600.00', $facturi['C-INAINTE']->chirie_eur);
        $this->assertSame('8000.00', $facturi['C-INAINTE']->chirie_lei);
        $this->assertSame('1800.00', $facturi['C-DUPA']->chirie_eur);
        $this->assertSame('9000.00', $facturi['C-DUPA']->chirie_lei);
    }

    public function test_facturarea_foloseste_chiria_indexata_cand_este_mai_mare_decat_chiria_contractuala(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil indexare',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-INDEX',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'pret_lunar' => 1600,
            'indexare_2026' => 2000,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-INDEX',
            'chirias' => 'Chiriaș indexat',
            'data_start' => '2026-01-01',
            'chirie' => 1600,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 100,
        ]);

        $this->post(route('facturare.generate'), [
            'curs_eur' => 5,
        ])->assertRedirect(route('facturare.index'));

        $factura = Factura::query()->firstOrFail();

        $this->assertSame('2000.00', $factura->chirie_eur);
        $this->assertSame('10000.00', $factura->chirie_lei);
    }

    public function test_facturarea_pastreaza_chiria_contractuala_cand_indexarea_este_mai_mica(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil indexare mica',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-INDEX-MICA',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'pret_lunar' => 1600,
            'indexare_2026' => 1400,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-INDEX-MICA',
            'chirias' => 'Chiriaș contractual',
            'data_start' => '2026-01-01',
            'chirie' => 1600,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 100,
        ]);

        $this->post(route('facturare.generate'), [
            'curs_eur' => 5,
        ])->assertRedirect(route('facturare.index'));

        $factura = Factura::query()->firstOrFail();

        $this->assertSame('1600.00', $factura->chirie_eur);
        $this->assertSame('8000.00', $factura->chirie_lei);
    }

    public function test_facturarea_foloseste_pret_lunar_din_spatiu_in_loc_de_chiria_lunara_din_contract(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil chirie contractuala',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-PRET-LUNAR',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'pret_lunar' => 180,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-PRET-LUNAR',
            'chirias' => 'Chiriaș contractual',
            'data_start' => '2026-01-01',
            'chirie' => 150,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 100,
        ]);

        $this->post(route('facturare.generate'), [
            'curs_eur' => 5,
        ])->assertRedirect(route('facturare.index'));

        $factura = Factura::query()->firstOrFail();

        $this->assertSame('180.00', $factura->chirie_eur);
        $this->assertSame('900.00', $factura->chirie_lei);
    }

    public function test_factura_afiseaza_utilitatile_grupate_pe_cote_tva(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil factura TVA',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $locator = Locator::query()->create([
            'nume' => 'GREEN COURT SRL',
            'cui_are_ro' => true,
            'cui' => '12345678',
            'registrul_comertului' => 'J40/123/2020',
            'adresa' => 'Str. Exemplu 1, Timișoara',
            'banca' => 'Banca Transilvania',
            'cont_bancar' => 'RO49BTRL00000000000000',
            'email' => 'facturare@greencourt.ro',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-TVA',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'locator_id' => $locator->id,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-TVA',
            'chirias' => 'ZBO ELECTRONICS SRL',
            'chirias_tip' => 'pj',
            'chirias_date' => [
                'cui' => '98765432',
                'sediu_social' => 'Str. Chiriaș 2, Timișoara',
                'telefon' => '0721000000',
                'email' => 'office@chirias.ro',
                'nr_reg_comert' => 'J35/1/2019',
            ],
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
            'data_emitere' => '2026-05-31',
            'data_scadenta' => '2026-06-05',
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
                ->where('factura.numar_factura', 'FACT-TVA')
                ->where('factura.data_emitere', '31.05.2026')
                ->where('factura.data_scadenta', '05.06.2026')
                ->where('factura.locator.nume', 'GREEN COURT SRL')
                ->where('factura.locator.cui', 'RO12345678')
                ->where('factura.locator.email', 'facturare@greencourt.ro')
                ->where('factura.locatar.nume', 'ZBO ELECTRONICS SRL')
                ->where('factura.locatar.identificator_label', 'CUI')
                ->where('factura.locatar.identificator', '98765432')
                ->where('factura.locatar.telefon', '0721000000')
                ->where('factura.locatar.email', 'office@chirias.ro')
                ->where('factura.linii.1.nr_crt', 2)
                ->where('factura.linii.1.denumire', 'Utilități 21% TVA mai 2026')
                ->where('factura.linii.1.valoare', 100)
                ->where('factura.linii.1.tva', 21)
                ->where('factura.linii.2.nr_crt', 3)
                ->where('factura.linii.2.denumire', 'Utilități 11% TVA mai 2026')
                ->where('factura.linii.2.valoare', 233.76)
                ->where('factura.linii.2.tva', 25.71)
                ->where('factura.linii.3.nr_crt', 4)
                ->where('factura.linii.3.denumire', 'Penalități')
            );
    }

    public function test_factura_nu_afiseaza_utilitati_cu_valoare_zero(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil utilitati zero',
            'strada' => 'Strada Zero',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-ZERO',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-ZERO',
            'chirias' => 'Chiriaș zero',
            'chirie' => 1000,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $anexa = Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 100,
        ]);

        $anexa->linii()->create([
            'denumire' => 'Energie electrica',
            'tip_linie' => 'serviciu',
            'valoare' => 100,
            'tva_21' => 21,
            'ordine' => 1,
        ]);

        $anexa->linii()->create([
            'denumire' => 'Serviciu fara consum',
            'tip_linie' => 'serviciu',
            'valoare' => 0,
            'tva_21' => 0,
            'ordine' => 2,
        ]);

        $factura = Factura::query()->create([
            'anexa_id' => $anexa->id,
            'numar_factura' => 'FACT-ZERO',
            'curs_eur' => 5,
            'chirie_eur' => 1000,
            'chirie_lei' => 5000,
            'total' => 5121,
            'penalitati' => 0,
            'status' => 'draft',
        ]);

        $this->get(route('facturare.show', $factura))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('factura.linii.0.denumire', 'Chirie spațiu iunie 2026 · 1.000,00 EUR/lună')
                ->where('factura.linii.1.denumire', 'Utilități 21% TVA mai 2026')
                ->where('factura.linii.2.denumire', 'Penalități')
                ->has('factura.linii', 3)
            );
    }

    public function test_factura_afiseaza_tva_21_pe_chirie_pentru_locator_cu_tva(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil CIT Grup',
            'strada' => 'Calea Aradului',
            'numar' => '48/A',
            'localitate' => 'Timișoara',
        ]);

        $locator = Locator::query()->create([
            'nume' => 'CIT Grup',
            'cui_are_ro' => true,
            'cui' => '14965516',
            'chirie_cu_tva' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'CIT-01',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'locator_id' => $locator->id,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-CIT',
            'chirias' => 'FOOD FROM HOME SRL',
            'chirie' => 2500,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $anexa = Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 4172.84,
        ]);

        $factura = Factura::query()->create([
            'anexa_id' => $anexa->id,
            'numar_factura' => 'FACT-CIT',
            'data_emitere' => '2026-06-18',
            'data_scadenta' => '2026-06-23',
            'curs_eur' => 5.0046,
            'chirie_eur' => 2500,
            'chirie_lei' => 12511.57,
            'penalitati' => 0,
            'total' => 12511.57 + round(12511.57 * 0.21, 2),
            'status' => 'draft',
        ]);

        $this->get(route('facturare.show', $factura))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('factura.linii.0.denumire', 'Chirie spațiu iunie 2026 · 2.500,00 EUR/lună')
                ->where('factura.linii.0.valoare', '12511.57')
                ->where('factura.linii.0.tva', 2627.43)
                ->where('factura.sumar.tva_21', 2627.43)
                ->where('factura.sumar.total', 15139));
    }

    public function test_generarea_facturii_include_tva_21_pe_chirie_pentru_locator_cu_tva(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil generare TVA chirie',
            'strada' => 'Strada TVA',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $locator = Locator::query()->create([
            'nume' => 'CIT Grup',
            'chirie_cu_tva' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'CIT-GEN',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
            'locator_id' => $locator->id,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-GEN-TVA',
            'chirias' => 'Chiriaș TVA',
            'chirie' => 1000,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 100,
        ]);

        $this->post(route('facturare.generate'), [
            'curs_eur' => 5,
        ])->assertRedirect(route('facturare.index'));

        $factura = Factura::query()->firstOrFail();

        $this->assertSame('5000.00', $factura->chirie_lei);
        $this->assertSame('6150.00', $factura->total);
    }

    public function test_pagina_facturare_imobil_afiseaza_doar_facturile_imobilului(): void
    {
        $imobilSelectat = Imobil::query()->create([
            'nume' => 'Imobil facturi dedicat',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $imobilAscuns = Imobil::query()->create([
            'nume' => 'Imobil facturi ascuns',
            'strada' => 'Strada B',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        foreach ([$imobilSelectat, $imobilAscuns] as $index => $imobil) {
            $spatiu = Spatiu::query()->create([
                'imobil_id' => $imobil->id,
                'identificator' => 'F-'.$index,
                'status' => 'inchiriat',
                'moneda' => 'EUR',
            ]);

            $contract = Contract::query()->create([
                'spatiu_id' => $spatiu->id,
                'numar_contract' => 'C-'.$index,
                'chirias' => 'Chiriaș '.$index,
                'data_start' => '2026-01-01',
                'chirie' => 1000,
                'moneda' => 'EUR',
                'status' => 'activ',
            ]);

            $anexa = Anexa::query()->create([
                'contract_id' => $contract->id,
                'luna' => '2026-05',
                'total' => 100,
            ]);

            Factura::query()->create([
                'anexa_id' => $anexa->id,
                'numar_factura' => 'FACT-'.$index,
                'curs_eur' => 5,
                'chirie_eur' => 1000,
                'chirie_lei' => 5000,
                'total' => 5100,
                'penalitati' => 0,
                'status' => 'draft',
            ]);
        }

        $this->get(route('facturare.imobil', $imobilSelectat))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Facturare/Imobil')
                ->where('imobil.id', $imobilSelectat->id)
                ->where('facturi.0.numar_factura', 'FACT-0')
                ->has('facturi', 1)
            );
    }

    public function test_pagina_facturare_imobil_poate_filtra_dupa_spatiu_si_chirias(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil filtre facturi',
            'strada' => 'Strada D',
            'numar' => '4',
            'localitate' => 'Timișoara',
        ]);

        foreach ([
            ['identificator' => 'HQE 103', 'chirias' => 'ZBOELECTRONICS SRL', 'suffix' => 'A'],
            ['identificator' => 'HQE 102', 'chirias' => 'MY&TA EXPERT SRL', 'suffix' => 'B'],
        ] as $index => $data) {
            $spatiu = Spatiu::query()->create([
                'imobil_id' => $imobil->id,
                'identificator' => $data['identificator'],
                'status' => 'inchiriat',
                'moneda' => 'EUR',
            ]);

            $contract = Contract::query()->create([
                'spatiu_id' => $spatiu->id,
                'numar_contract' => 'C-'.$data['suffix'],
                'chirias' => $data['chirias'],
                'data_start' => '2026-01-01',
                'chirie' => 1000,
                'moneda' => 'EUR',
                'status' => 'activ',
            ]);

            $anexa = Anexa::query()->create([
                'contract_id' => $contract->id,
                'luna' => '2026-05',
                'total' => 100,
            ]);

            Factura::query()->create([
                'anexa_id' => $anexa->id,
                'numar_factura' => 'FACT-'.$data['suffix'],
                'curs_eur' => 5,
                'chirie_eur' => 1000,
                'chirie_lei' => 5000,
                'total' => 5100,
                'penalitati' => 0,
                'status' => 'draft',
            ]);
        }

        $this->get(route('facturare.imobil', [
            'imobil' => $imobil,
            'search_spatiu' => 'HQE 103',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search_spatiu', 'HQE 103')
                ->where('facturi.0.spatiu', 'HQE 103')
                ->where('facturi.0.chirias', 'ZBOELECTRONICS SRL')
                ->has('facturi', 1)
            );

        $this->get(route('facturare.imobil', [
            'imobil' => $imobil,
            'search_chirias' => 'MY&TA',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.search_chirias', 'MY&TA')
                ->where('facturi.0.spatiu', 'HQE 102')
                ->where('facturi.0.chirias', 'MY&TA EXPERT SRL')
                ->has('facturi', 1)
            );
    }

    public function test_generarea_din_pagina_imobilului_redirecteaza_inapoi_la_imobil(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil generare facturi',
            'strada' => 'Strada C',
            'numar' => '3',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-GEN',
            'status' => 'inchiriat',
            'moneda' => 'EUR',
        ]);
        $this->ataseazaConfigurareAnexa($spatiu);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-GEN',
            'chirias' => 'Chiriaș generare',
            'data_start' => '2026-01-01',
            'chirie' => 1000,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Anexa::query()->create([
            'contract_id' => $contract->id,
            'luna' => '2026-05',
            'total' => 100,
        ]);

        $this->post(route('facturare.generate'), [
            'imobil_id' => $imobil->id,
        ])->assertRedirect(route('facturare.imobil', $imobil));

        $this->assertCount(1, Factura::query()->get());
    }
}
