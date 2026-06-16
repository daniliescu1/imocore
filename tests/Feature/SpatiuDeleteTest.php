<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\PerioadaInchiriereFatada;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpatiuDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_spatiul_poate_fi_sters_si_recalculeaza_imobilul(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil delete',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S-DEL',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S-RAMANE',
            'status' => 'inchiriat',
            'ordine' => 2,
        ]);

        $imobil->recalculeazaSpatii();
        $this->assertSame(2, $imobil->fresh()->spatii_total);
        $this->assertSame(1, $imobil->fresh()->spatii_libere);
        $this->assertSame(1, $imobil->fresh()->spatii_inchiriate);

        $this->delete(route('spatii.destroy', $spatiu))
            ->assertRedirect('/spatii?imobil_id='.$imobil->id)
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('spatii', ['id' => $spatiu->id]);
        $this->assertDatabaseHas('spatii', ['identificator' => 'S-RAMANE']);

        $imobil->refresh();
        $this->assertSame(1, $imobil->spatii_total);
        $this->assertSame(0, $imobil->spatii_libere);
        $this->assertSame(1, $imobil->spatii_inchiriate);
    }

    public function test_stergerea_spatiului_sterge_si_perioadele_de_fatada(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil fațadă',
            'strada' => 'Strada Test',
            'numar' => '2',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'F-1',
            'status' => 'inchiriat',
            'etaj' => 'Fațadă',
            'ordine' => 1,
        ]);

        $perioada = PerioadaInchiriereFatada::query()->create([
            'spatiu_id' => $spatiu->id,
            'data_start' => '2026-01-01',
            'data_end' => '2026-12-31',
            'chirias' => 'Chiriaș test',
            'chirie_lunara' => 500,
            'moneda' => 'EUR',
        ]);

        $this->delete(route('spatii.destroy', $spatiu))
            ->assertRedirect('/spatii?imobil_id='.$imobil->id);

        $this->assertDatabaseMissing('spatii', ['id' => $spatiu->id]);
        $this->assertDatabaseMissing('perioade_inchiriere_fatada', ['id' => $perioada->id]);
    }

    public function test_pagina_editare_expune_can_delete_spatii(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil edit',
            'strada' => 'Strada Test',
            'numar' => '3',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'S-EDIT',
            'status' => 'liber',
            'ordine' => 1,
        ]);

        $this->get(route('spatii.edit', $spatiu))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canDeleteSpatii', true));
    }
}
