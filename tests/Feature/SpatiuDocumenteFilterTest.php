<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\Contract;
use App\Models\Imobil;
use App\Models\Spatiu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpatiuDocumenteFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{imobil: Imobil, cuAnexa: Spatiu, faraAnexa: Spatiu, cuContract: Spatiu, faraContract: Spatiu}
     */
    private function seedSpatiiDocumente(): array
    {
        $imobil = Imobil::query()->create([
            'nume' => '700 Office',
            'strada' => 'Strada Test',
            'numar' => '1',
            'localitate' => 'Timișoara',
        ]);

        $anexa = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexă utilități',
            'implicit' => true,
            'activ' => true,
        ]);

        $cuAnexa = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'CU-ANEXA',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $anexa->id,
            'ordine' => 1,
        ]);

        $faraAnexa = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'FARA-ANEXA',
            'status' => 'inchiriat',
            'ordine' => 2,
        ]);

        $cuContract = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'CU-CONTRACT',
            'status' => 'inchiriat',
            'ordine' => 3,
        ]);

        Contract::query()->create([
            'spatiu_id' => $cuContract->id,
            'numar_contract' => 'C-1',
            'chirias' => 'Test SRL',
            'data_start' => '2025-01-01',
            'chirie' => 900,
            'moneda' => 'EUR',
            'status' => 'activ',
        ]);

        $faraContract = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'FARA-CONTRACT',
            'status' => 'inchiriat',
            'ordine' => 4,
        ]);

        return compact('imobil', 'cuAnexa', 'faraAnexa', 'cuContract', 'faraContract');
    }

    public function test_spatiile_din_imobil_se_filtreaza_dupa_documente(): void
    {
        $data = $this->seedSpatiiDocumente();

        $this->get("/spatii?imobil_id={$data['imobil']->id}&documente=fara_anexa")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Spatii/Index')
                ->where('filters.documente', 'fara_anexa')
                ->has('spatii', 3)
                ->where('spatii', fn ($spatii) => collect($spatii)->pluck('identificator')->sort()->values()->all()
                    === ['CU-CONTRACT', 'FARA-ANEXA', 'FARA-CONTRACT']));

        $this->get("/spatii?imobil_id={$data['imobil']->id}&documente=fara_contract")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.documente', 'fara_contract')
                ->has('spatii', 3)
                ->where('spatii', fn ($spatii) => collect($spatii)->pluck('identificator')->sort()->values()->all()
                    === ['CU-ANEXA', 'FARA-ANEXA', 'FARA-CONTRACT']));

        $this->get("/spatii?imobil_id={$data['imobil']->id}&documente=cu_contract")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.documente', 'cu_contract')
                ->has('spatii', 1)
                ->where('spatii.0.identificator', 'CU-CONTRACT'));

        $this->get("/spatii?imobil_id={$data['imobil']->id}&documente=cu_anexa")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.documente', 'cu_anexa')
                ->has('spatii', 1)
                ->where('spatii.0.identificator', 'CU-ANEXA'));
    }
}
