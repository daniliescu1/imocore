<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracte_index_filters_by_status(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $spatiuActiv = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D101',
            'status' => 'inchiriat',
            'ordine' => 1,
        ]);

        $spatiuIncomplet = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D102',
            'status' => 'liber',
            'ordine' => 2,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiuActiv->id,
            'numar_contract' => 'C-ACTIV',
            'chirias' => 'Chiriaș Activ',
            'data_start' => '2025-01-01',
            'data_end' => '2025-12-31',
            'chirie' => 500,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiuIncomplet->id,
            'numar_contract' => 'C-INCOMPLET',
            'chirias' => 'Chiriaș Incomplet',
            'data_start' => '2025-01-01',
            'chirie' => 400,
            'moneda' => 'EUR',
            'status' => 'incomplet',
        ]);

        $this->get('/contracte?status=activ')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracte/Index')
                ->where('filters.status', 'activ')
                ->has('contracte', 1)
                ->where('contracte.0.numar_contract', 'C-ACTIV'));

        $this->get('/contracte?status=incomplet')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracte/Index')
                ->where('filters.status', 'incomplet')
                ->has('contracte', 1)
                ->where('contracte.0.numar_contract', 'C-INCOMPLET'));

        $this->get('/contracte')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracte/Index')
                ->where('filters.status', '')
                ->has('contracte', 2));
    }

    public function test_contracte_index_filters_by_configurare_anexa(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $anexaA = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa A',
            'implicit' => true,
            'activ' => true,
        ]);

        $anexaB = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa B',
            'implicit' => false,
            'activ' => true,
        ]);

        $spatiuA = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D101',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $anexaA->id,
            'ordine' => 1,
        ]);

        $spatiuB = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'D102',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $anexaB->id,
            'ordine' => 2,
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiuA->id,
            'numar_contract' => 'C-ANEXA-A',
            'chirias' => 'Chiriaș A',
            'data_start' => '2025-01-01',
            'chirie' => 500,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        Contract::query()->create([
            'spatiu_id' => $spatiuB->id,
            'numar_contract' => 'C-ANEXA-B',
            'chirias' => 'Chiriaș B',
            'data_start' => '2025-01-01',
            'chirie' => 400,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $this->get('/contracte?configurare_anexa_id='.$anexaA->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracte/Index')
                ->where('filters.configurare_anexa_id', (string) $anexaA->id)
                ->has('contracte', 1)
                ->where('contracte.0.numar_contract', 'C-ANEXA-A')
                ->has('configurariAnexe', 2));
    }
}
