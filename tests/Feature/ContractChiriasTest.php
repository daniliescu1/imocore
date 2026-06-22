<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use App\Support\ContractCompleteness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractChiriasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{locator_id: int, data_end: string}
     */
    private function contractRequiredFields(?Locator $locator = null): array
    {
        $locator ??= Locator::query()->create(['nume' => 'Locator Test SRL']);

        return [
            'locator_id' => $locator->id,
            'data_end' => '2025-12-31',
        ];
    }

    public function test_contract_pf_salveaza_datele_structurate_si_actualizeaza_spatiul(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D204',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-PF-1',
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'Ion Popescu',
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara, str. Exemplu 1',
                'email' => 'ion@example.com',
                'telefon' => '0722000000',
            ],
            'data_start' => '2025-01-01',
            'chirie' => 900,
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('pf', $contract->chirias_tip);
        $this->assertSame('Ion Popescu', $contract->chirias);
        $this->assertSame('RX', $contract->chirias_date['serie_ci']);
        $this->assertSame('1234567890123', $contract->chirias_date['cnp']);
        $this->assertSame('Ion Popescu', $spatiu->fresh()->chirias);
    }

    public function test_contract_pj_salveaza_firma_si_administrator(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D205',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-PJ-1',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Exemplu SRL',
                'sediu_social' => 'Timișoara, str. Firma 2',
                'telefon' => '0256000000',
                'email' => 'office@exemplu.ro',
                'email_2' => 'contact@exemplu.ro',
                'banca' => 'BCR',
                'cont_bancar' => 'RO49RNCB0000000000000001',
                'nr_reg_comert' => 'J35/123/2020',
                'cui' => 'RO12345678',
                'administrator' => [
                    'nume_complet' => 'Maria Ionescu',
                    'calitate' => 'imputernicit_notarial',
                    'serie_ci' => 'seria TM nr. 654321, eliberat de SPCLEP, la data de 01.01.2020',
                    'numar_ci' => '654321',
                    'cnp' => '2980101123456',
                    'domiciliu' => 'Timișoara, str. Admin 3',
                    'email' => 'maria@exemplu.ro',
                    'telefon' => '0733000000',
                ],
            ],
            'data_start' => '2025-01-01',
            'crestere_chirie_la' => 1450,
            'data_crestere_chirie' => '01/07/2025',
            'chirie' => 1200,
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('pj', $contract->chirias_tip);
        $this->assertSame('SC Exemplu SRL', $contract->chirias);
        $this->assertSame('RO12345678', $contract->chirias_date['cui']);
        $this->assertSame('office@exemplu.ro', $contract->chirias_date['email']);
        $this->assertSame('contact@exemplu.ro', $contract->chirias_date['email_2']);
        $this->assertSame('BCR', $contract->chirias_date['banca']);
        $this->assertSame('RO49RNCB0000000000000001', $contract->chirias_date['cont_bancar']);
        $this->assertSame('1450.00', $contract->crestere_chirie_la);
        $this->assertSame('2025-07-01', $contract->data_crestere_chirie->format('Y-m-d'));
        $this->assertSame('Maria Ionescu', $contract->chirias_date['administrator']['nume_complet']);
        $this->assertSame('imputernicit_notarial', $contract->chirias_date['administrator']['calitate']);
        $this->assertSame('seria TM nr. 654321, eliberat de SPCLEP, la data de 01.01.2020', $contract->chirias_date['administrator']['serie_ci']);
        $this->assertSame('SC Exemplu SRL', $spatiu->fresh()->chirias);
    }

    public function test_contract_pj_salveaza_al_doilea_reprezentant_legal(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D205A',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-PJ-2REP',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Doi Reprezentanti SRL',
                'sediu_social' => 'Timișoara, str. Firma 2',
                'telefon' => '0256000000',
                'email' => 'office@doi-rep.ro',
                'nr_reg_comert' => 'J35/123/2020',
                'cui' => 'RO12345678',
                'administrator' => [
                    'nume_complet' => 'Maria Ionescu',
                    'calitate' => 'administrator',
                ],
                'administrator_2' => [
                    'nume_complet' => 'Mihai Laurentiu',
                    'calitate' => 'reprezentant_legal',
                    'serie_ci' => 'CI seria TZ nr. 514304, eliberat de SPCLEP Timisoara, la data de 07.05.20',
                    'cnp' => '1840703350157',
                    'domiciliu' => 'Timisoara, str. Meziad, nr.5',
                ],
            ],
            'data_start' => '2025-01-01',
            'chirie' => 1200,
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('Maria Ionescu', $contract->chirias_date['administrator']['nume_complet']);
        $this->assertSame('Mihai Laurentiu', $contract->chirias_date['administrator_2']['nume_complet']);
        $this->assertSame('reprezentant_legal', $contract->chirias_date['administrator_2']['calitate']);
        $this->assertSame('1840703350157', $contract->chirias_date['administrator_2']['cnp']);

        $this->get(route('contracte.edit', $contract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracte/Form')
                ->where('contract.chirias_pj.administrator_2.nume_complet', 'Mihai Laurentiu')
                ->where('contract.chirias_pj.administrator_2.calitate', 'reprezentant_legal')
            );
    }

    public function test_contract_pj_fara_al_doilea_reprezentant_nu_persista_administrator_2(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D205A2',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-PJ-1REP',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Un Reprezentant SRL',
                'sediu_social' => 'Timișoara',
                'telefon' => '0256000000',
                'email' => 'office@un-rep.ro',
                'nr_reg_comert' => 'J35/123/2020',
                'cui' => 'RO12345678',
                'administrator' => [
                    'nume_complet' => 'Maria Ionescu',
                ],
                'administrator_2' => [
                    'nume_complet' => '',
                    'calitate' => 'administrator',
                ],
            ],
            'data_start' => '2025-01-01',
            'chirie' => 1200,
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract = Contract::query()->firstOrFail();

        $this->assertNull($contract->chirias_date['administrator_2'] ?? null);
    }

    public function test_contract_pj_are_obligatoriu_doar_numele_reprezentantului_legal(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D205B',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-PJ-ADMIN-MIN',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Minimal Admin SRL',
                'sediu_social' => 'Timișoara',
                'telefon' => '0256000000',
                'email' => 'office@minimal.ro',
                'nr_reg_comert' => 'J35/123/2020',
                'cui' => 'RO12345678',
                'administrator' => [
                    'nume_complet' => 'Maria Ionescu',
                    'serie_ci' => '',
                    'numar_ci' => '',
                    'cnp' => '',
                    'domiciliu' => '',
                    'email' => '',
                    'telefon' => '',
                ],
            ],
            'data_start' => '2025-01-01',
            'chirie' => 1200,
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('Maria Ionescu', $contract->chirias_date['administrator']['nume_complet']);
        $this->assertSame('administrator', $contract->chirias_date['administrator']['calitate']);
        $this->assertNull($contract->chirias_date['administrator']['serie_ci']);
        $this->assertNull($contract->chirias_date['administrator']['cnp']);
        $this->assertNull($contract->chirias_date['administrator']['domiciliu']);
    }

    public function test_contract_pj_accepta_date_scrise_in_format_romanesc(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D205C',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'Nr. 347 din 17.03.2026',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'CYD JOHAN SCARPE SRL',
                'sediu_social' => 'Bivolaria',
                'telefon' => '0755934932',
                'email' => 'cyd.johanscarpe@icloud.com',
                'nr_reg_comert' => 'J2017000740331',
                'cui' => 'RO37506377',
                'administrator' => [
                    'nume_complet' => 'Cirdei Ioan',
                ],
            ],
            'data_start' => '17/03/2026',
            'data_end' => '30/04/2028',
            'chirie' => 1600,
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('2026-03-17', $contract->data_start->format('Y-m-d'));
        $this->assertSame('2028-04-30', $contract->data_end->format('Y-m-d'));
    }

    public function test_edit_contract_pastreaza_tipul_salvat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D206',
            'status' => 'inchiriat',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-EDIT',
            'chirias' => 'Ion Popescu',
            'chirias_tip' => 'pf',
            'chirias_date' => [
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => null,
                'telefon' => null,
            ],
            'data_start' => '2025-01-01',
            'chirie' => 800,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->get(route('contracte.edit', $contract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracte/Form')
                ->where('contract.chirias_tip', 'pf')
                ->where('contract.chirias_pf.nume_complet', 'Ion Popescu')
                ->where('contract.chirias_pf.serie_ci', 'RX')
            );
    }

    public function test_contract_store_salveaza_configurare_anexa_id_pe_spatiu(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D207',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-ANEXA-1',
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'Ion Popescu',
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => 'ion@example.com',
                'telefon' => '0722111111',
            ],
            'data_start' => '2025-01-01',
            'chirie' => 900,
            'moneda' => 'EUR',
            'configurare_anexa_id' => $configurare->id,
        ])->assertRedirect('/contracte');

        $this->assertSame($configurare->id, $spatiu->fresh()->configurare_anexa_id);
    }

    public function test_contract_update_salveaza_configurare_anexa_id_pe_spatiu(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D208',
            'status' => 'inchiriat',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-ANEXA-EDIT',
            'chirias' => 'Ion Popescu',
            'chirias_tip' => 'pf',
            'chirias_date' => [
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => null,
                'telefon' => null,
            ],
            'data_start' => '2025-01-01',
            'chirie' => 800,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->put(route('contracte.update', $contract), [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-ANEXA-EDIT',
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'Ion Popescu',
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => 'ion@example.com',
                'telefon' => '0722111111',
            ],
            'data_start' => '2025-01-01',
            'chirie' => 850,
            'moneda' => 'EUR',
            'configurare_anexa_id' => $configurare->id,
        ])->assertRedirect('/contracte');

        $this->assertSame($configurare->id, $spatiu->fresh()->configurare_anexa_id);
    }

    public function test_contract_update_pastreaza_anexa_spatiului_la_schimbarea_chiriasului(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D209',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-ANEXA-PAST',
            'chirias' => 'Chiriaș vechi SRL',
            'chirias_tip' => 'pj',
            'chirias_date' => [
                'denumire' => 'Chiriaș vechi SRL',
            ],
            'data_start' => '2025-01-01',
            'chirie' => 800,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->put(route('contracte.update', $contract), [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-ANEXA-PAST',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'Chiriaș nou SRL',
                'sediu_social' => 'Timișoara',
                'administrator' => [
                    'nume_complet' => 'Admin Nou',
                ],
            ],
            'data_start' => '2025-01-01',
            'chirie' => 900,
            'moneda' => 'EUR',
            'configurare_anexa_id' => '',
        ])->assertRedirect('/contracte');

        $spatiu->refresh();

        $this->assertSame($configurare->id, $spatiu->configurare_anexa_id);
        $this->assertSame('Chiriaș nou SRL', $spatiu->chirias);
    }

    public function test_contract_update_salveaza_cresterea_de_chirie(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D208B',
            'status' => 'inchiriat',
            'ordine' => 1,
        ]);

        $locator = Locator::query()->create(['nume' => 'Locator Update SRL']);
        $spatiu->update([
            'locator_id' => $locator->id,
            'locator' => $locator->nume,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'C-UPDATE-RENT',
            'chirias' => 'SC Rent Update SRL',
            'chirias_tip' => 'pj',
            'chirias_date' => [
                'sediu_social' => 'Timișoara',
                'telefon' => '0256000000',
                'email' => 'office@rent-update.ro',
                'nr_reg_comert' => 'J35/123/2020',
                'cui' => 'RO12345678',
                'administrator' => [
                    'nume_complet' => 'Maria Ionescu',
                    'calitate' => 'administrator',
                ],
            ],
            'data_start' => '2026-03-17',
            'data_end' => '2028-04-30',
            'chirie' => 1600,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->put(route('contracte.update', $contract), [
            'spatiu_id' => $spatiu->id,
            'locator_id' => $locator->id,
            'numar_contract' => 'C-UPDATE-RENT',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Rent Update SRL',
                'sediu_social' => 'Timișoara',
                'telefon' => '0256000000',
                'email' => 'office@rent-update.ro',
                'nr_reg_comert' => 'J35/123/2020',
                'cui' => 'RO12345678',
                'administrator' => [
                    'nume_complet' => 'Maria Ionescu',
                    'calitate' => 'administrator',
                ],
            ],
            'data_start' => '17/03/2026',
            'data_end' => '30/04/2028',
            'chirie' => 1600,
            'crestere_chirie_la' => 1800,
            'data_crestere_chirie' => '22/01/2028',
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract->refresh();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('1800.00', $contract->crestere_chirie_la);
        $this->assertSame('2028-01-22', $contract->data_crestere_chirie->format('Y-m-d'));

        $this->get(route('contracte.edit', $contract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contract.crestere_chirie_la', '1800.00')
                ->where('contract.data_crestere_chirie', '2028-01-22')
            );
    }

    public function test_contract_incomplet_se_salveaza_fara_a_marca_spatiul_inchiriat(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D209',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Parțial SRL',
            ],
        ])->assertRedirect();

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('incomplet', $contract->status);
        $this->assertSame('SC Parțial SRL', $contract->chirias);
        $this->assertSame('liber', $spatiu->fresh()->status);
        $this->assertNull($spatiu->fresh()->chirias);
    }

    public function test_contract_incomplet_fara_numar_contract_si_data_start_se_creeaza_fara_eroare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D214',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            'numar_contract' => '',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'sc cucu srl',
            ],
            'data_start' => '',
            'chirie' => 1560,
            'moneda' => 'EUR',
        ])->assertRedirect();

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('incomplet', $contract->status);
        $this->assertSame('sc cucu srl', $contract->chirias);
        $this->assertSame(\App\Support\ContractIncompleteStorage::NUMAR_PLACEHOLDER, $contract->numar_contract);
        $this->assertSame('1970-01-01', $contract->data_start->format('Y-m-d'));
    }

    public function test_contract_incomplet_cu_campuri_chirias_goale_se_actualizeaza_fara_eroare(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D213',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'test',
            'chirias' => 'SC Exemplu SRL',
            'chirias_tip' => 'pj',
            'chirias_date' => [
                'denumire' => 'SC Exemplu SRL',
            ],
            'data_start' => '2026-02-04',
            'chirie' => 1860,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->put(route('contracte.update', $contract), [
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'test',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => '',
                'sediu_social' => '',
                'telefon' => '',
                'email' => '',
                'nr_reg_comert' => '',
                'cui' => '',
                'administrator' => [
                    'nume_complet' => '',
                    'serie_ci' => '',
                    'numar_ci' => '',
                    'cnp' => '',
                    'domiciliu' => '',
                    'email' => '',
                    'telefon' => '',
                ],
            ],
            'data_start' => '2026-02-04',
            'data_end' => '2026-06-19',
            'chirie' => 1860,
            'moneda' => 'EUR',
        ])->assertRedirect();

        $contract->refresh();

        $this->assertSame('incomplet', $contract->status);
        $this->assertSame(\App\Support\ContractIncompleteStorage::CHIRIAS_PLACEHOLDER, $contract->chirias);
    }

    public function test_contract_incomplet_devine_activ_cand_sunt_complete_toate_campurile(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D210',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => null,
            'chirias' => 'SC Parțial SRL',
            'chirias_tip' => 'pj',
            'chirias_date' => [
                'denumire' => 'SC Parțial SRL',
            ],
            'data_start' => null,
            'chirie' => 0,
            'moneda' => 'EUR',
            'status' => 'incomplet',
        ]);

        $this->put(route('contracte.update', $contract), [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields(),
            'numar_contract' => 'C-COMPLETE-1',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Parțial SRL',
                'sediu_social' => 'Timișoara',
                'telefon' => '0256111111',
                'email' => 'office@partial.ro',
                'nr_reg_comert' => 'J35/1/2020',
                'cui' => 'RO111',
                'administrator' => [
                    'nume_complet' => 'Admin Test',
                    'serie_ci' => 'TM',
                    'numar_ci' => '111111',
                    'cnp' => '1234567890123',
                    'domiciliu' => 'Timișoara',
                    'email' => 'admin@partial.ro',
                ],
            ],
            'data_start' => '2025-01-01',
            'chirie' => 1000,
            'moneda' => 'EUR',
        ])->assertRedirect('/contracte');

        $contract->refresh();
        $spatiu->refresh();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('inchiriat', $spatiu->status);
        $this->assertSame('SC Parțial SRL', $spatiu->chirias);
    }

    public function test_edit_contract_incomplet_expune_campurile_lipsa(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D211',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $contract = Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => null,
            'chirias' => 'SC Parțial SRL',
            'chirias_tip' => 'pj',
            'chirias_date' => [
                'denumire' => 'SC Parțial SRL',
            ],
            'data_start' => null,
            'chirie' => 0,
            'moneda' => 'EUR',
            'status' => 'incomplet',
        ]);

        $this->get(route('contracte.edit', $contract))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracte/Form')
                ->where('contract.status', 'incomplet')
                ->where('contract.missing_field_labels', fn ($labels) => collect($labels)->isNotEmpty())
            );

        $missing = \App\Support\ContractCompleteness::missingFieldKeys([
            'spatiu_id' => $spatiu->id,
            'locator_id' => null,
            'numar_contract' => null,
            'data_start' => null,
            'data_end' => null,
            'chirie' => 0,
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'SC Parțial SRL',
                'sediu_social' => '',
                'email' => '',
                'nr_reg_comert' => '',
                'cui' => '',
                'administrator' => [
                    'nume_complet' => '',
                    'serie_ci' => '',
                    'numar_ci' => '',
                    'cnp' => '',
                    'domiciliu' => '',
                    'email' => '',
                ],
            ],
        ]);

        $this->assertContains('numar_contract', $missing);
        $this->assertContains('locator_id', $missing);
        $this->assertNotContains('data_start', $missing);
        $this->assertNotContains('data_end', $missing);
    }

    public function test_contract_fara_telefon_chirias_ramane_incomplet(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D212',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $locator = Locator::query()->create(['nume' => 'Locator Test SRL']);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            'locator_id' => $locator->id,
            'data_end' => '2025-12-31',
            'numar_contract' => 'C-FARA-TEL',
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'Ion Popescu',
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => 'ion@example.com',
            ],
            'data_start' => '2025-01-01',
            'chirie' => 900,
            'moneda' => 'EUR',
        ])->assertRedirect();

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('incomplet', $contract->status);
        $this->assertContains('chirias_pf.telefon', ContractCompleteness::missingFieldKeys([
            'spatiu_id' => $spatiu->id,
            'locator_id' => $locator->id,
            'numar_contract' => 'C-FARA-TEL',
            'data_start' => '2025-01-01',
            'data_end' => '2025-12-31',
            'chirie' => 900,
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'Ion Popescu',
                'serie_ci' => 'RX',
                'numar_ci' => '123456',
                'cnp' => '1234567890123',
                'domiciliu' => 'Timișoara',
                'email' => 'ion@example.com',
                'telefon' => '',
            ],
        ]));
    }

    public function test_contract_pj_cu_cnp_prefix_devine_activ(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'E106',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $locator = Locator::query()->create(['nume' => 'Golden Cube']);

        $this->post('/contracte', [
            'spatiu_id' => $spatiu->id,
            ...$this->contractRequiredFields($locator),
            'numar_contract' => 'Nr. 294 din 27.03.2025',
            'chirias_tip' => 'pj',
            'chirias_pj' => [
                'denumire' => 'CFB TRAININGS&DEVELOPMENT SRL',
                'sediu_social' => 'Timisoara',
                'telefon' => '0726440428',
                'email' => 'florinburlacu5@gmail.com',
                'email_2' => 'florinburlacu5@gmail.com',
                'nr_reg_comert' => 'J35/3580/19.09.2019',
                'cui' => 'RO41663515',
                'administrator' => [
                    'nume_complet' => 'Burlacu Florin',
                    'serie_ci' => 'CI seria TZ nr. 921154',
                    'cnp' => 'CNP 1790907354739',
                    'domiciliu' => 'Sat Beregsau Mic',
                ],
            ],
            'data_start' => '2025-04-01',
            'data_end' => '2026-04-30',
            'chirie' => 270,
            'moneda' => 'EUR',
            'persoane_declarate' => 1,
        ])->assertRedirect('/contracte');

        $contract = Contract::query()->firstOrFail();

        $this->assertSame('activ', $contract->status);
        $this->assertSame('1790907354739', $contract->chirias_date['administrator']['cnp']);
    }

    public function test_contract_pf_cu_serie_ci_lunga_nu_este_marcat_incomplet(): void
    {
        $serieCi = 'CI seria KS nr. 656152, eliberat de SPCLEP Resita';

        $missing = \App\Support\ContractCompleteness::missingFieldKeys([
            'spatiu_id' => 1,
            'locator_id' => 1,
            'numar_contract' => 'Nr. 235 din 20.12.2023',
            'data_start' => '2024-01-01',
            'data_end' => '2026-12-31',
            'chirie' => 230,
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'MARINA DENISA GEORGIA',
                'serie_ci' => $serieCi,
                'numar_ci' => '',
                'cnp' => '1234567890123',
                'domiciliu' => 'Resita',
                'email' => 'marina@example.com',
                'telefon' => '0722000000',
            ],
        ]);

        $this->assertNotContains('chirias_pf.serie_ci', $missing);
    }

    public function test_contract_pf_foloseste_numar_ci_legacy_pentru_completitudine(): void
    {
        $missing = \App\Support\ContractCompleteness::missingFieldKeys([
            'spatiu_id' => 1,
            'locator_id' => 1,
            'numar_contract' => 'Nr. 235 din 20.12.2023',
            'data_start' => '2024-01-01',
            'data_end' => '2026-12-31',
            'chirie' => 230,
            'chirias_tip' => 'pf',
            'chirias_pf' => [
                'nume_complet' => 'MARINA DENISA GEORGIA',
                'serie_ci' => '',
                'numar_ci' => '656152',
                'cnp' => '1234567890123',
                'domiciliu' => 'Resita',
                'email' => 'marina@example.com',
                'telefon' => '0722000000',
            ],
        ]);

        $this->assertNotContains('chirias_pf.serie_ci', $missing);
    }
}
