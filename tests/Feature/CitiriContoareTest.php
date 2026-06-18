<?php

namespace Tests\Feature;

use App\Models\CitireContor;
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
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->get(route('citiri-contoare.imobil', ['imobil' => $imobil->id, 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Imobil')
                ->where('spatii.0.liniiContor.0.configurare_anexa_linie_id', $liniePausal->id)
                ->where('spatii.0.liniiContor.0.tip_calcul', 'pausal')
                ->has('spatii.0.liniiContor', 1)
            );
    }

    public function test_citiri_pausal_salveaza_cantitatea_direct_in_consum(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linie = $this->creeazaLiniePausal($configurare, 'Gunoi menajer');
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->post(route('citiri-contoare.store'), [
            'imobil_id' => $imobil->id,
            'luna' => '2026-06',
            'data_citire' => '2026-06-20T14:30',
            'citiri' => [[
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
                'consum' => 3,
            ]],
        ])->assertRedirect();

        $this->assertDatabaseHas('citiri_contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'consum' => 3,
            'index_vechi' => 0,
            'index_nou' => 0,
        ]);
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

    private function creeazaConfigurare(Imobil $imobil): ConfigurareAnexaImobil
    {
        return ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa servicii',
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

    private function creeazaLiniePausal(ConfigurareAnexaImobil $configurare, string $denumire): ConfigurareAnexaLinie
    {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => $denumire,
            'tip_calcul' => 'pausal',
            'activ' => true,
        ]);
    }

    private function creeazaSpatiu(Imobil $imobil, ConfigurareAnexaImobil $configurare): Spatiu
    {
        return Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S1',
            'status' => 'liber',
            'configurare_anexa_id' => $configurare->id,
            'moneda' => 'EUR',
        ]);
    }
}
