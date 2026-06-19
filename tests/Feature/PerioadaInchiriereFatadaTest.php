<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\PerioadaInchiriereFatada;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PerioadaInchiriereFatadaTest extends TestCase
{
    use RefreshDatabase;

    public function test_perioada_fatada_se_salveaza_cu_minim_30_zile(): void
    {
        $spatiu = $this->spatiuFatada();
        $start = now()->startOfMonth()->format('Y-m-d');
        $end = now()->startOfMonth()->addDays(29)->format('Y-m-d');
        $an = (int) now()->format('Y');

        $this->post(route('spatii.perioade-fatada.store', $spatiu), [
            'an' => $an,
            'data_start' => $start,
            'data_end' => $end,
            'chirias' => 'Banner SRL',
            'chirie_lunara' => '1200.00',
        ])->assertRedirect(route('spatii.edit', $spatiu));

        $perioada = PerioadaInchiriereFatada::query()->firstOrFail();

        $this->assertSame('Banner SRL', $perioada->chirias);
        $this->assertSame('1200.00', $perioada->chirie_lunara);
        $this->assertSame(30, $perioada->zileInchiriate());
        $this->assertSame('Banner SRL', $spatiu->fresh()->chirias);
    }

    public function test_perioadele_fatada_nu_pot_sa_se_suprapuna(): void
    {
        $spatiu = $this->spatiuFatada();

        PerioadaInchiriereFatada::query()->create([
            'spatiu_id' => $spatiu->id,
            'data_start' => '2026-04-01',
            'data_end' => '2026-05-15',
            'chirias' => 'Primul chiriaș',
            'chirie_lunara' => '900.00',
            'moneda' => 'EUR',
        ]);

        $this->from(route('spatii.edit', $spatiu))
            ->post(route('spatii.perioade-fatada.store', $spatiu), [
                'an' => 2026,
                'data_start' => '2026-05-01',
                'data_end' => '2026-06-15',
                'chirias' => 'Al doilea chiriaș',
                'chirie_lunara' => '950.00',
            ])
            ->assertSessionHasErrors('data_start');

        $this->assertSame(1, PerioadaInchiriereFatada::query()->count());
    }

    public function test_edit_page_include_perioade_fatada_dupa_blocare(): void
    {
        $spatiu = $this->spatiuFatada();

        $this->post(route('spatii.perioade-fatada.store', $spatiu), [
            'an' => 2026,
            'data_start' => '2026-03-01',
            'data_end' => '2026-04-15',
            'chirias' => 'Banner SRL',
            'chirie_lunara' => '1200.00',
        ])->assertRedirect(route('spatii.edit', $spatiu));

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->has('perioadeFatada', 1)
                ->where('perioadeFatada.0.chirias', 'Banner SRL')
                ->where('perioadeFatada.0.data_start', '2026-03-01')
                ->where('perioadeFatada.0.data_end', '2026-04-15')
                ->where('perioadeFatada.0.chirie_lunara', '1200.00'));
    }

    public function test_perioada_fatada_inertia_store_returneaza_calendarul_actualizat(): void
    {
        $spatiu = $this->spatiuFatada();
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Requested-With' => 'XMLHttpRequest',
            ])
            ->post(route('spatii.perioade-fatada.store', $spatiu), [
                'an' => 2026,
                'data_start' => '2026-03-01',
                'data_end' => '2026-04-15',
                'chirias' => 'Banner SRL',
                'chirie_lunara' => '1200.00',
            ])
            ->assertOk()
            ->assertJsonPath('component', 'Spatii/Create')
            ->assertJsonPath('props.perioadeFatada.0.chirias', 'Banner SRL')
            ->assertJsonPath('props.perioadeFatada.0.data_start', '2026-03-01')
            ->assertJsonPath('props.perioadeFatada.0.data_end', '2026-04-15');
    }

    public function test_perioada_fatada_respinge_interval_mai_mic_de_30_zile(): void
    {
        $spatiu = $this->spatiuFatada();

        $this->from(route('spatii.edit', $spatiu))
            ->post(route('spatii.perioade-fatada.store', $spatiu), [
                'an' => 2026,
                'data_start' => '2026-07-01',
                'data_end' => '2026-07-20',
                'chirias' => 'Prea scurt',
                'chirie_lunara' => '500.00',
            ])
            ->assertSessionHasErrors('data_end');

        $this->assertSame(0, PerioadaInchiriereFatada::query()->count());
    }

    public function test_perioada_fatada_se_actualizeaza_fara_suprapunere_cu_sine(): void
    {
        $spatiu = $this->spatiuFatada();

        $perioada = PerioadaInchiriereFatada::query()->create([
            'spatiu_id' => $spatiu->id,
            'data_start' => '2026-04-01',
            'data_end' => '2026-05-15',
            'chirias' => 'Banner SRL',
            'chirie_lunara' => '1200.00',
            'moneda' => 'EUR',
        ]);

        $this->put(route('spatii.perioade-fatada.update', [$spatiu, $perioada]), [
            'an' => 2026,
            'data_start' => '2026-04-05',
            'data_end' => '2026-05-20',
            'chirias' => 'Banner SRL Actualizat',
            'chirie_lunara' => '1300.00',
        ])->assertRedirect(route('spatii.edit', $spatiu));

        $perioada->refresh();

        $this->assertSame('2026-04-05', $perioada->data_start->format('Y-m-d'));
        $this->assertSame('2026-05-20', $perioada->data_end->format('Y-m-d'));
        $this->assertSame('Banner SRL Actualizat', $perioada->chirias);
        $this->assertSame('1300.00', $perioada->chirie_lunara);
        $this->assertSame(1, PerioadaInchiriereFatada::query()->count());
    }

    public function test_actualizarea_perioadei_respinge_suprapunerea_cu_alta_perioada(): void
    {
        $spatiu = $this->spatiuFatada();

        $prima = PerioadaInchiriereFatada::query()->create([
            'spatiu_id' => $spatiu->id,
            'data_start' => '2026-04-01',
            'data_end' => '2026-05-15',
            'chirias' => 'Primul chiriaș',
            'chirie_lunara' => '900.00',
            'moneda' => 'EUR',
        ]);

        $aDoua = PerioadaInchiriereFatada::query()->create([
            'spatiu_id' => $spatiu->id,
            'data_start' => '2026-06-01',
            'data_end' => '2026-07-15',
            'chirias' => 'Al doilea chiriaș',
            'chirie_lunara' => '950.00',
            'moneda' => 'EUR',
        ]);

        $this->from(route('spatii.edit', $spatiu))
            ->put(route('spatii.perioade-fatada.update', [$spatiu, $aDoua]), [
                'an' => 2026,
                'data_start' => '2026-05-01',
                'data_end' => '2026-06-15',
                'chirias' => 'Suprapus',
                'chirie_lunara' => '980.00',
            ])
            ->assertSessionHasErrors('data_start');

        $this->assertSame('2026-06-01', $aDoua->fresh()->data_start->format('Y-m-d'));
        $this->assertSame('2026-04-01', $prima->fresh()->data_start->format('Y-m-d'));
    }

    public function test_chirie_proportionala_foloseste_zilele_lunii_calendaristice(): void
    {
        $totalMartie = PerioadaInchiriereFatada::calculeazaChirieProportionala('2026-03-01', '2026-03-31', '3100.00');
        $totalFebruarie = PerioadaInchiriereFatada::calculeazaChirieProportionala('2026-02-01', '2026-02-28', '2800.00');
        $totalCrossMonth = PerioadaInchiriereFatada::calculeazaChirieProportionala('2026-03-30', '2026-04-02', '3000.00');

        $this->assertSame('3100.00', $totalMartie);
        $this->assertSame('2800.00', $totalFebruarie);
        $this->assertSame('393.55', $totalCrossMonth);
    }

    private function spatiuFatada(): Spatiu
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil fatada',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        return Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Banner 1',
            'etaj' => 'Fațadă',
            'status' => 'inchiriat',
            'regim_incalzire' => 'neincalzit',
            'persoane_declarate' => 0,
            'moneda' => 'EUR',
        ]);
    }
}
