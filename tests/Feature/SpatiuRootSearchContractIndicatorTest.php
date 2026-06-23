<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatiuRootSearchContractIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_cautarea_globala_pastreaza_contractul_activ_pe_indicator(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada A',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => '119',
            'chirias' => 'Chiriaș activ',
            'status' => 'inchiriat',
            'suprafata_contractuala_mp' => 27,
            'pret_lunar' => 470,
            'moneda' => 'EUR',
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiu->id,
            'numar_contract' => 'Nr. 237 din 23.01.2024',
            'chirias' => 'Chiriaș activ',
            'data_start' => '2024-01-23',
            'chirie' => 470,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/spatii?search=119')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('filters.search_spatii', true)
                ->has('spatii', 1)
                ->where('spatii.0.are_contract_inregistrat', true)
                ->where('spatii.0.are_contract_activ', true)
            );
    }
}
