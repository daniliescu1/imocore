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

    public function test_citiri_contoare_afiseaza_doar_liniile_de_tip_contor_pentru_imobil(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa servicii',
            'implicit' => true,
            'activ' => true,
        ]);

        $linieContor = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Apă rece',
            'nr_crt' => 1,
            'tip_calcul' => 'contor',
            'um' => 'mc',
            'activ' => true,
        ]);

        ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Chirie',
            'nr_crt' => 2,
            'tip_calcul' => 'manual',
            'um' => 'lună',
            'activ' => true,
        ]);
        $spatiu = $this->creeazaSpatiu($imobil, $configurare);

        $this->get(route('citiri-contoare.index', ['imobil_id' => $imobil->id, 'luna' => '2026-06', 'mode' => 'new']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CitiriContoare/Index')
                ->where('selectedImobilId', $imobil->id)
                ->where('mode', 'new')
                ->where('spatii.0.id', $spatiu->id)
                ->where('spatii.0.liniiContor.0.configurare_anexa_linie_id', $linieContor->id)
                ->where('spatii.0.liniiContor.0.denumire', 'Apă rece')
                ->has('spatii.0.liniiContor', 1)
            );
    }

    public function test_citiri_contoare_salvaeaza_indexurile_pe_linia_de_anexa(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa servicii',
            'implicit' => true,
            'activ' => true,
        ]);
        $linie = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Curent',
            'tip_calcul' => 'contor',
            'activ' => true,
        ]);
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
        ])->assertRedirect(route('citiri-contoare.index', [
            'imobil_id' => $imobil->id,
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
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa servicii',
            'implicit' => true,
            'activ' => true,
        ]);
        $linie = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Curent',
            'tip_calcul' => 'contor',
            'activ' => true,
        ]);
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

        $this->get(route('citiri-contoare.index', ['imobil_id' => $imobil->id, 'mode' => 'new']))
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

    private function creeazaImobil(): Imobil
    {
        return Imobil::query()->create([
            'nume' => 'Imobil citiri',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Oradea',
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
