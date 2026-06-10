<?php

namespace Tests\Feature;

use App\Models\Imobil;
use App\Models\Locator;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_locatorii_pot_fi_adaugati_si_listati_global(): void
    {
        $this->post(route('locatori.store'), [
            'nume' => 'Firma Test',
            'imobil_id' => '',
        ])->assertRedirect('/locatori');

        $locator = Locator::query()->where('nume', 'Firma Test')->firstOrFail();

        $this->assertNull($locator->imobil_id);

        $this->get(route('locatori.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Locatori/Index')
                ->where('locatori.0.nume', 'Firma Test')
                ->where('locatori.0.imobil', 'Global')
            );
    }

    public function test_locatorul_adaugat_din_meniu_poate_fi_selectat_pe_spatiu(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil locator global',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Cluj',
        ]);

        $locator = Locator::query()->create([
            'nume' => 'Locator Global',
        ]);

        $this->post(route('spatii.store'), [
            'imobil_id' => $imobil->id,
            'identificator' => 'S1',
            'status' => 'liber',
            'moneda' => 'EUR',
            'locator_id' => $locator->id,
        ])->assertRedirect('/spatii');

        $spatiu = Spatiu::query()->where('identificator', 'S1')->firstOrFail();

        $this->assertSame($locator->id, $spatiu->locator_id);

        $this->get(route('spatii.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Create')
                ->where('locatori.0.nume', 'Locator Global')
            );
    }
}
