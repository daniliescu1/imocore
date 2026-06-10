<?php

namespace Tests\Feature;

use App\Models\Imobil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImobilConfigurareAnexaTest extends TestCase
{
    use RefreshDatabase;

    public function test_configurarea_anexei_se_salveaza_si_se_reincarca(): void
    {
        $payload = [
            'nume' => 'Imobil anexa',
            'strada' => 'Strada Test',
            'numar' => '10',
            'localitate' => 'Cluj-Napoca',
            'judet' => 'Cluj',
            'cod_postal' => '400000',
            'numere_cf' => [
                ['numar' => 'CF-123', 'observatii' => 'CF principal'],
            ],
            'observatii' => 'Observatii imobil',
            'configurari_anexe' => [
                [
                    'denumire' => 'Anexa utilitati',
                    'observatii' => 'Configurare completata',
                    'linii' => [
                        [
                            'nr_crt' => 1,
                            'denumire' => 'Apa rece',
                            'index_vechi' => '10',
                            'index_nou' => '15.5',
                            'facturat' => '5.5',
                            'um' => 'mc',
                            'pret_unitar' => '12.3456',
                            'valoare' => '67.90',
                            'tva_21' => '21',
                            'tip_calcul' => 'contor',
                        ],
                    ],
                ],
            ],
        ];

        $this->post(route('imobile.store'), $payload)->assertRedirect('/imobile');

        $imobil = Imobil::query()
            ->where('nume', 'Imobil anexa')
            ->with('configurariAnexe.linii')
            ->firstOrFail();

        $configurare = $imobil->configurariAnexe->first();
        $linie = $configurare->linii->first();

        $this->assertSame('Anexa utilitati', $configurare->denumire);
        $this->assertTrue($configurare->implicit);
        $this->assertTrue($configurare->activ);
        $this->assertSame('Apa rece', $linie->denumire);
        $this->assertSame(1, $linie->nr_crt);
        $this->assertSame('10', $linie->index_vechi);
        $this->assertSame('15.5', $linie->index_nou);
        $this->assertSame('5.500', $linie->facturat);
        $this->assertSame('12.3456', $linie->pret_unitar);
        $this->assertSame('67.90', $linie->valoare);
        $this->assertSame('21.00', $linie->tva_21);
        $this->assertSame('contor', $linie->tip_calcul);
        $this->assertTrue($linie->apare_cu_zero);
        $this->assertTrue($linie->activ);

        $this->get(route('imobile.edit', $imobil))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imobile/Create')
                ->where('imobil.configurari_anexe.0.denumire', 'Anexa utilitati')
                ->where('imobil.configurari_anexe.0.linii.0.denumire', 'Apa rece')
                ->where('imobil.configurari_anexe.0.linii.0.facturat', '5.500')
                ->where('imobil.configurari_anexe.0.linii.0.valoare', '67.90')
                ->where('imobil.configurari_anexe.0.linii.0.tva_21', '21')
            );
    }

    public function test_configurarea_anexei_se_actualizeaza_din_form_data(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil existent',
            'strada' => 'Strada Veche',
            'numar' => '1',
            'localitate' => 'Oradea',
            'numere_cf' => [['numar' => 'CF-1', 'observatii' => '']],
        ]);

        $payload = [
            'nume' => 'Imobil existent',
            'strada' => 'Strada Noua',
            'numar' => '2',
            'localitate' => 'Oradea',
            'configurari_anexe' => [
                [
                    'denumire' => 'Anexa chirie',
                    'linii' => [
                        [
                            'nr_crt' => 2,
                            'denumire' => 'Serviciu fix',
                            'facturat' => '3',
                            'um' => 'buc',
                            'pret_unitar' => '20',
                            'valoare' => '60.00',
                            'tva_21' => '11',
                            'tip_calcul' => 'fix',
                        ],
                    ],
                ],
            ],
        ];

        $this->post(route('imobile.update', $imobil), [...$payload, '_method' => 'put'])->assertRedirect('/imobile');

        $imobil->refresh()->load('configurariAnexe.linii');
        $linie = $imobil->configurariAnexe->first()->linii->first();

        $this->assertSame('Anexa chirie', $imobil->configurariAnexe->first()->denumire);
        $this->assertSame('Serviciu fix', $linie->denumire);
        $this->assertSame('60.00', $linie->valoare);
        $this->assertSame('11.00', $linie->tva_21);
        $this->assertTrue($linie->apare_cu_zero);
        $this->assertTrue($linie->activ);

        $this->get(route('imobile.edit', $imobil))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Imobile/Create')
                ->where('imobil.configurari_anexe.0.denumire', 'Anexa chirie')
                ->where('imobil.configurari_anexe.0.linii.0.denumire', 'Serviciu fix')
                ->where('imobil.configurari_anexe.0.linii.0.facturat', '3.000')
                ->where('imobil.configurari_anexe.0.linii.0.valoare', '60.00')
                ->where('imobil.configurari_anexe.0.linii.0.tva_21', '11')
            );
    }

    public function test_configurarea_anexei_cu_header_gol_salveaza_liniile_completate(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil fara header anexa',
            'strada' => 'Strada Test',
            'numar' => '4',
            'localitate' => 'Timisoara',
            'numere_cf' => [['numar' => 'CF-4', 'observatii' => '']],
        ]);

        $this->post(route('imobile.update', $imobil), [
            '_method' => 'put',
            'nume' => 'Imobil fara header anexa',
            'strada' => 'Strada Test',
            'numar' => '4',
            'localitate' => 'Timisoara',
            'configurari_anexe' => [
                [
                    'denumire' => '',
                    'linii' => [
                        [
                            'nr_crt' => 1,
                            'denumire' => 'Energie Electrica',
                            'facturat' => '1',
                            'um' => 'KW',
                            'pret_unitar' => '1.5280',
                            'valoare' => '1.53',
                            'tva_21' => '21',
                            'tip_calcul' => 'manual',
                        ],
                    ],
                ],
            ],
        ])->assertRedirect('/imobile');

        $imobil->refresh()->load('configurariAnexe.linii');
        $configurare = $imobil->configurariAnexe->first();
        $linie = $configurare->linii->first();

        $this->assertSame('Anexă imobil', $configurare->denumire);
        $this->assertSame('Energie Electrica', $linie->denumire);
        $this->assertSame('KW', $linie->um);
        $this->assertSame('1.5280', $linie->pret_unitar);
        $this->assertSame('21.00', $linie->tva_21);
    }

    public function test_configurarea_anexei_se_poate_salva_separat_fara_campurile_imobilului(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil salvare anexa',
            'strada' => 'Strada Test',
            'numar' => '5',
            'localitate' => 'Brasov',
            'numere_cf' => [['numar' => 'CF-5', 'observatii' => '']],
        ]);

        $this->put(route('imobile.configurari-anexe.update', $imobil), [
            'configurari_anexe' => [
                [
                    'denumire' => 'Anexa salvata separat',
                    'linii' => [
                        [
                            'nr_crt' => 1,
                            'denumire' => 'Serviciu separat',
                            'facturat' => '2',
                            'um' => 'buc',
                            'pret_unitar' => '10',
                            'valoare' => '20.00',
                            'tip_calcul' => 'manual',
                        ],
                    ],
                ],
            ],
        ])->assertRedirect(route('imobile.edit', $imobil));

        $imobil->refresh()->load('configurariAnexe.linii');

        $this->assertSame('Imobil salvare anexa', $imobil->nume);
        $this->assertSame('Anexa salvata separat', $imobil->configurariAnexe->first()->denumire);
        $this->assertSame('Serviciu separat', $imobil->configurariAnexe->first()->linii->first()->denumire);
    }

    public function test_update_fara_configurari_anexe_nu_sterge_datele_existente(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil cu anexa',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Sibiu',
            'numere_cf' => [['numar' => 'CF-3', 'observatii' => '']],
        ]);

        $configurare = $imobil->configurariAnexe()->create([
            'denumire' => 'Anexa pastrata',
            'implicit' => true,
            'activ' => true,
        ]);

        $configurare->linii()->create([
            'denumire' => 'Linie pastrata',
            'valoare' => '100.00',
        ]);

        $this->post(route('imobile.update', $imobil), [
            '_method' => 'put',
            'nume' => 'Imobil cu anexa',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Sibiu',
        ])->assertRedirect('/imobile');

        $imobil->refresh()->load('configurariAnexe.linii');

        $this->assertCount(1, $imobil->configurariAnexe);
        $this->assertSame('Anexa pastrata', $imobil->configurariAnexe->first()->denumire);
        $this->assertSame('Linie pastrata', $imobil->configurariAnexe->first()->linii->first()->denumire);
    }
}
