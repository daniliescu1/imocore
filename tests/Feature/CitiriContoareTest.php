<?php

namespace Tests\Feature;

use App\Models\CitireContor;
use App\Support\ContorConfigurabilSync;
use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CitiriContoareTest extends TestCase
{
    use RefreshDatabase;

    public function test_indexul_citiri_contoare_afiseaza_lista_de_imobile(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $this->creeazaLinieContor($configurare, 'Apă rece');
        $this->creeazaSpatiu($imobil, $configurare);

        $this->get(route('citiri-contoare.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Index')
                ->has('imobile', 1)
                ->where('imobile.0.id', $imobil->id)
                ->where('imobile.0.contoare_count', 1)
            );
    }

    public function test_indexul_citiri_contoare_redirecteaza_la_imobil_cand_cauti_spatiu(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $this->creeazaLinieContor($configurare, 'Apă rece');
        $this->creeazaSpatiu($imobil, $configurare, '105');

        $this->get(route('citiri-contoare.index', ['search' => '105']))
            ->assertRedirect(route('citiri-contoare.imobil', [
                'imobil' => $imobil->id,
                'mode' => 'new',
                'search' => '105',
            ]));
    }

    public function test_prima_vizita_pe_imobil_deschide_modul_de_citire_noua(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $this->creeazaLinieContor($configurare, 'Curent');
        $this->creeazaSpatiu($imobil, $configurare);

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Imobil')
                ->where('mode', 'new')
                ->where('readOnly', false)
            );
    }

    public function test_pagina_imobilului_afiseaza_liniile_de_tip_contor(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linieContor = $this->creeazaLinieContor($configurare, 'Apă rece');

        ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Chirie',
            'nr_crt' => 2,
            'tip_calcul' => 'manual',
            'um' => 'lună',
            'activ' => true,
        ]);

        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'luna' => '2026-06', 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Imobil')
                ->where('imobil.id', $imobil->id)
                ->where('mode', 'new')
                ->where('spatii.0.id', $spatiu->id)
                ->where('spatii.0.liniiContor.0.configurare_anexa_linie_id', $linieContor->id)
                ->where('spatii.0.liniiContor.0.denumire', 'Apă rece')
                ->has('spatii.0.liniiContor', 1)
            );
    }

    public function test_pagina_imobilului_recunoaste_tipul_contor_cu_initiala_mare(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);

        ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Energie Electrica',
            'tip_calcul' => 'Contor',
            'um' => 'Kw',
            'activ' => true,
        ]);

        $this->creeazaSpatiu($imobil, $configurare);

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Imobil')
                ->has('spatii', 1)
                ->has('spatii.0.liniiContor', 1)
                ->where('spatii.0.liniiContor.0.denumire', 'Energie Electrica')
            );
    }

    public function test_contoare_fix_apar_separat_si_salveaza_facturat(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $this->creeazaLinieContor($configurare, 'Energie Electrica');
        $linieFix = $this->creeazaLinieContorFix($configurare, 'Telefon fix');
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Imobil')
                ->has('contoareFix', 1)
                ->where('contoareFix.0.spatiu_id', $spatiu->id)
                ->where('contoareFix.0.denumire', 'Telefon fix')
                ->has('spatii', 1)
                ->has('spatii.0.liniiContor', 1)
            );

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20T14:30',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linieFix->id,
                'consum' => 12.5,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linieFix->id,
            'luna' => '2026-06',
            'consum' => 12.5,
            'index_vechi' => 0,
            'index_nou' => 0,
        ]);
    }

    public function test_citiri_contoare_salvaeaza_indexurile_pe_linia_de_anexa(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linie = $this->creeazaLinieContor($configurare, 'Curent');
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20T14:30',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_nou' => 125.5,
            ]],
        ])->assertRedirect(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'data_citire' => '2026-06-20T14:30',
            'luna' => '2026-06',
        ]));

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20 14:30:00',
            'index_vechi' => 0,
            'index_nou' => 125.5,
            'consum' => 125.5,
        ]);
    }

    public function test_citire_luna_noua_foloseste_indexul_nou_anterior_ca_index_vechi(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linie = $this->creeazaLinieContor($configurare, 'Curent');
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        CitireContor::query()->create([
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20 12:00:00',
            'index_vechi' => 0,
            'index_nou' => 125.5,
            'consum' => 125.5,
        ]);

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('luna', '2026-06')
                ->where('lunaInchisa', false)
                ->where('readOnly', false)
            );

        $this->post(route('citiri-contoare.inchide'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20T12:00',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_vechi' => 0,
                'index_nou' => 125.5,
            ]],
        ])->assertRedirect();

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('luna', '2026-07')
                ->where('spatii.0.liniiContor.0.index_vechi', 125.5)
                ->where('readOnly', false)
            );

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-07',
            'data_citire' => '2026-07-20T12:00',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_nou' => 150,
            ]],
        ]);

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-07',
            'index_vechi' => 125.5,
            'index_nou' => 150,
            'consum' => 24.5,
        ]);
    }

    public function test_pagina_imobilului_afiseaza_liniile_de_tip_pausal(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $liniePausal = $this->creeazaLiniePausal($configurare, 'Servicii Gunoi Menajer');
        $this->creeazaSpatiu($imobil, $configurare);
        ContorConfigurabilSync::syncForConfigurare($configurare);

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Imobil')
                ->has('contoareConfigurabile', 1)
                ->where('contoareConfigurabile.0.configurare_anexa_linie_id', $liniePausal->id)
                ->where('contoareConfigurabile.0.tip_calcul', 'pausal')
                ->where('contoareConfigurabile.0.is_pausal', true)
                ->has('spatii', 0)
            );
    }

    public function test_citiri_pausal_salveaza_cantitatea_direct_in_consum(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linie = $this->creeazaLiniePausal($configurare, 'Gunoi menajer');
        $this->creeazaSpatiu($imobil, $configurare);
        ContorConfigurabilSync::syncForConfigurare($configurare);

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20T14:30',
            'citiri' => [[
                'spatiu_id' => null,
                'configurare_anexa_linie_id' => $linie->id,
                'consum' => 3,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'consum' => 3,
            'index_vechi' => 0,
            'index_nou' => 0,
        ]);
    }

    public function test_liniile_fara_citire_din_istoric_raman_editabile_dupa_anexa_noua(): void
    {
        $imobil = $this->creeazaImobil();
        $configurareVeche = $this->creeazaConfigurare($imobil, 'Anexa veche');
        $linieVeche = $this->creeazaLinieContor($configurareVeche, 'Curent');
        $spatiuVechi = $this->creeazaSpatiu($imobil, $configurareVeche, 'S1');

        CitireContor::query()->create([
            'spatiu_id' => $spatiuVechi->id,
            'configurare_anexa_linie_id' => $linieVeche->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20 12:00:00',
            'index_vechi' => 0,
            'index_nou' => 100,
            'consum' => 100,
        ]);

        CitireContor::query()->create([
            'spatiu_id' => $spatiuVechi->id,
            'configurare_anexa_linie_id' => $linieVeche->id,
            'luna' => '2026-07',
            'data_citire' => '2026-07-20 12:00:00',
            'index_vechi' => 100,
            'index_nou' => 120,
            'consum' => 20,
        ]);

        $configurareNoua = $this->creeazaConfigurare($imobil, 'Anexa Pers < 50 mp');
        $linieNouaContor = $this->creeazaLinieContor($configurareNoua, 'Energie Electrica');
        $linieNouaPausal = $this->creeazaLiniePausal($configurareNoua, 'Consum apa - mc / pers');
        $spatiuNou = $this->creeazaSpatiu($imobil, $configurareNoua, 'S2');
        ContorConfigurabilSync::syncForConfigurare($configurareNoua);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'luna' => '2026-06',
            'mode' => 'history',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'history')
                ->where('spatii.1.id', $spatiuNou->id)
                ->where('spatii.1.liniiContor.0.editabila', true)
                ->has('spatii.1.liniiContor', 1)
                ->where('contoareConfigurabile.0.editabila', true)
                ->where('contoareConfigurabile.0.configurare_anexa_linie_id', $linieNouaPausal->id)
                ->where('spatii.0.liniiContor.0.editabila', false)
            );

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20T15:00',
            'citiri' => [
                [
                    'spatiu_id' => $spatiuNou->id,
                    'configurare_anexa_linie_id' => $linieNouaContor->id,
                    'index_nou' => 55.5,
                ],
                [
                    'spatiu_id' => null,
                    'configurare_anexa_linie_id' => $linieNouaPausal->id,
                    'consum' => 2,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiuNou->id,
            'configurare_anexa_linie_id' => $linieNouaContor->id,
            'luna' => '2026-06',
            'index_nou' => 55.5,
        ]);

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieNouaPausal->id,
            'luna' => '2026-06',
            'consum' => 2,
        ]);
    }

    public function test_citirile_salvate_raman_editabile_pana_la_inchiderea_lunii(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linie = $this->creeazaLinieContor($configurare, 'Curent');
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20T14:30',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_nou' => 125.5,
            ]],
        ])->assertRedirect();

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'luna' => '2026-06',
            'mode' => 'history',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lunaInchisa', false)
                ->where('areCitiriSalvate', true)
                ->where('spatii.0.liniiContor.0.editabila', true)
            );

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-21T10:00',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_vechi' => 0,
                'index_nou' => 140,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'index_nou' => 140,
            'consum' => 140,
        ]);

        $this->post(route('citiri-contoare.inchide'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-21T10:00',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_vechi' => 0,
                'index_nou' => 140,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare_luni_inchise', [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
        ]);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'luna' => '2026-06',
            'mode' => 'history',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lunaInchisa', true)
                ->where('spatii.0.liniiContor.0.editabila', false)
            );

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-22T10:00',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_nou' => 999,
            ]],
        ])->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'index_nou' => 140,
        ]);
    }

    public function test_inchiderea_salveaza_si_inchide_fara_salvare_anterioara(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linie = $this->creeazaLinieContor($configurare, 'Curent');
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->post(route('citiri-contoare.inchide'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-07',
            'data_citire' => '2026-07-20T16:46',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'index_vechi' => 0,
                'index_nou' => 88,
            ]],
        ])->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-07',
            'index_nou' => 88,
            'consum' => 88,
        ]);

        $this->assertDatabaseHas('citiri_contoare_luni_inchise', [
            'imobil_id' => $imobil->id,
            'luna' => '2026-07',
        ]);
    }

    public function test_citirile_contorului_pe_spatiu_raman_la_trecerea_pe_liber(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linie = $this->creeazaLinieContor($configurare, 'Energie electrica');
        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C303',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare);

        $citire = CitireContor::query()->create([
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-05',
            'index_vechi' => 100,
            'index_nou' => 145,
            'consum' => 45,
        ]);

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'C303',
            'status' => 'liber',
            'de_lamurit' => false,
            'marcat_galben' => false,
            'marcat_verde' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare', [
            'id' => $citire->id,
            'spatiu_id' => $spatiu->id,
            'index_nou' => 145,
            'consum' => 45,
        ]);
    }

    public function test_pausal_apa_si_canalizare_apar_doar_la_nivel_imobil_in_citiri(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil, 'Anexa Pers < 50 mp');
        $linieApa = $this->creeazaLiniePausal($configurare, 'Consum apa - mc / pers');
        $linieCanal = $this->creeazaLiniePausal($configurare, 'Canalizare mc / pers');
        $this->creeazaLinieContor($configurare, 'Energie Electrica');
        $spatiu = $this->creeazaSpatiu($imobil, $configurare, 'C 307');
        $spatiu->update(['status' => 'inchiriat']);
        ContorConfigurabilSync::syncForConfigurare($configurare);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'mode' => 'new',
            'search' => 'C 307',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contoareConfigurabile', 2)
                ->where('contoareConfigurabile.0.is_pausal', true)
                ->where('contoareConfigurabile.0.index_vechi', '')
                ->where('contoareConfigurabile.0.index_nou', '')
                ->where('contoareConfigurabile.1.is_pausal', true)
                ->where('searchMatchingSpatii.0.id', $spatiu->id)
                ->where('contoareConfigurabile.0.configurare_anexa_id', $configurare->id)
                ->where('contoareConfigurabile.0.alocari_spatiu_ids', [$spatiu->id])
                ->has('spatii', 1)
                ->has('spatii.0.liniiContor', 1)
                ->where('spatii.0.liniiContor.0.denumire', 'Energie Electrica')
            );
    }

    public function test_cautarea_dupa_spatiu_leaga_contoarele_pausale_chiar_fara_linii_contor_pe_spatiu(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil, 'Anexa Pers < 50 mp · Curent Pausal');
        $this->creeazaLiniePausal($configurare, 'Consum apa - mc / pers');
        $this->creeazaLiniePausal($configurare, 'Canalizare mc / pers');
        $linieCurent = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Energie electrica spatii comune',
            'tip_calcul' => 'Contor configurabil',
            'um' => 'Kw',
            'activ' => true,
        ]);
        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C 307',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);
        ContorConfigurabilSync::syncForConfigurare($configurare);

        $regula = \App\Models\ContorConfigurabil::query()
            ->where('configurare_anexa_linie_id', $linieCurent->id)
            ->firstOrFail();
        $regula->update([
            'foloseste_scaderi' => true,
            'alocari' => [$spatiu->id],
            'scaderi' => [],
        ]);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'mode' => 'new',
            'search' => 'C 307',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('searchMatchingSpatii.0.id', $spatiu->id)
                ->has('spatii', 0)
                ->has('contoareConfigurabile', 3)
                ->where('contoareConfigurabile', fn ($contoare) => collect($contoare)
                    ->contains(fn (array $linie): bool => (int) $linie['configurare_anexa_linie_id'] === (int) $linieCurent->id
                        && in_array($spatiu->id, $linie['alocari_spatiu_ids'], true)))
            );
    }

    public function test_cautarea_spatiu_fara_anexa_nu_leaga_contoarele_anexei_shared(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil, 'Anexa Pers < 50 mp');
        $this->creeazaLiniePausal($configurare, 'Consum apa - mc / pers');
        $this->creeazaLiniePausal($configurare, 'Canalizare mc / pers');
        $configurarePersonalizata = $this->creeazaConfigurare($imobil, 'Anexa Pers < 50 mp · Contor configurabil · C 309 / C310');
        ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurarePersonalizata->id,
            'denumire' => 'Energie Electrica',
            'tip_calcul' => 'Contor configurabil',
            'um' => 'Kw',
            'activ' => true,
        ]);

        $spatiuPeAnexa = $this->creeazaSpatiu($imobil, $configurare, 'C 308');
        $spatiuPeAnexa->update(['status' => 'inchiriat']);
        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C 309',
            'status' => 'inchiriat',
            'configurare_anexa_id' => null,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare);
        ContorConfigurabilSync::syncForConfigurare($configurarePersonalizata);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'mode' => 'new',
            'search' => 'C 309',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('searchMatchingSpatii.0.identificator', 'C 309')
                ->where('searchMatchingSpatii.0.configurare_anexa_id', null)
                ->has('contoareConfigurabile', 0)
            );
    }

    public function test_cautarea_citiri_potriveste_chiriasul_pe_pagina_imobil(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil, 'Anexa Pers < 50 mp');
        $this->creeazaLinieContor($configurare, 'Energie Electrica');

        $spatiu = $this->creeazaSpatiu($imobil, $configurare, 'HQC 00.01');
        $spatiu->update([
            'status' => 'inchiriat',
            'chirias' => 'NEXENT BANK N.V. Amsterdam',
        ]);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'mode' => 'new',
            'search' => 'nexent',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('searchMatchingSpatii', 1)
                ->where('searchMatchingSpatii.0.id', $spatiu->id)
                ->has('spatii', 1)
                ->where('spatii.0.identificator', 'HQC 00.01')
            );
    }

    public function test_cautarea_citiri_nu_potriveste_chiriasul_fara_identificator(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil, 'Anexa Pers < 50 mp');
        $this->creeazaLiniePausal($configurare, 'Consum apa - mc / pers');
        $this->creeazaLiniePausal($configurare, 'Canalizare mc / pers');
        $this->creeazaLinieContor($configurare, 'Energie Electrica');

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C 318',
            'chirias' => '318 Logistics SRL',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);
        ContorConfigurabilSync::syncForConfigurare($configurare);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C 309',
            'chirias' => '318 Logistics SRL',
            'status' => 'inchiriat',
            'configurare_anexa_id' => null,
        ]);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'mode' => 'new',
            'search' => 'C 318',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('searchMatchingSpatii', 1)
                ->where('searchMatchingSpatii.0.id', $spatiu->id)
                ->has('contoareConfigurabile', 2)
                ->has('spatii', 1)
            );
    }

    public function test_citirile_pausal_apa_si_canalizare_raman_la_trecerea_pe_liber(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linieApa = $this->creeazaLiniePausal($configurare, 'Consum apa - mc / pers');
        $linieCanal = $this->creeazaLiniePausal($configurare, 'Canalizare mc / pers');
        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'B101',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'ordine' => 1,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare);

        $citireApa = CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieApa->id,
            'luna' => '2026-05',
            'index_vechi' => 0,
            'index_nou' => 0,
            'consum' => 12,
        ]);

        $citireCanal = CitireContor::query()->create([
            'spatiu_id' => null,
            'configurare_anexa_linie_id' => $linieCanal->id,
            'luna' => '2026-05',
            'index_vechi' => 0,
            'index_nou' => 0,
            'consum' => 12,
        ]);

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'B101',
            'status' => 'liber',
            'de_lamurit' => false,
            'marcat_galben' => false,
            'marcat_verde' => false,
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare', [
            'id' => $citireApa->id,
            'spatiu_id' => null,
            'consum' => 12,
        ]);

        $this->assertDatabaseHas('citiri_contoare', [
            'id' => $citireCanal->id,
            'spatiu_id' => null,
            'consum' => 12,
        ]);
    }

    public function test_contoare_configurabile_afiseaza_numar_spatii_si_persoane_alocate(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $this->creeazaLiniePausal($configurare, 'Consum apa - mc / pers');

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C 419',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'suprafata_contractuala_mp' => 50,
            'persoane_declarate' => 3,
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'C 420',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
            'suprafata_contractuala_mp' => 30,
            'ordine' => 2,
        ]);

        ContorConfigurabilSync::syncForConfigurare($configurare);

        $this->get(route('citiri-contoare.imobil', [
            'imobil' => $imobil->id,
            'mode' => 'new',
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('contoareConfigurabile', 1)
                ->where('contoareConfigurabile.0.alocari_count', 2)
                ->where('contoareConfigurabile.0.alocari_persoane_count', 6));
    }

    private function creeazaImobil(): Imobil
    {
        return Imobil::query()->create([
            'nume' => 'Imobil citiri',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Oradea',
        ]);
    }

    private function creeazaConfigurare(Imobil $imobil, string $denumire = 'Anexa servicii'): ConfigurareAnexaImobil
    {
        return ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => $denumire,
            'implicit' => true,
            'activ' => true,
        ]);
    }

    private function creeazaLinieContor(ConfigurareAnexaImobil $configurare, string $denumire): ConfigurareAnexaLinie
    {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => $denumire,
            'tip_calcul' => 'contor',
            'activ' => true,
        ]);
    }

    private function creeazaLinieContorFix(ConfigurareAnexaImobil $configurare, string $denumire): ConfigurareAnexaLinie
    {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => $denumire,
            'tip_calcul' => 'Contor Fix',
            'um' => 'Kw',
            'pret_unitar' => 1.5,
            'activ' => true,
        ]);
    }

    private function creeazaLiniePausal(ConfigurareAnexaImobil $configurare, string $denumire): ConfigurareAnexaLinie
    {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => $denumire,
            'tip_calcul' => 'pausal',
            'activ' => true,
        ]);
    }

    private function creeazaSpatiu(Imobil $imobil, ConfigurareAnexaImobil $configurare, string $identificator = 'S1'): Spatiu
    {
        return Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => $identificator,
            'status' => 'liber',
            'configurare_anexa_id' => $configurare->id,
            'moneda' => 'EUR',
        ]);
    }
}
