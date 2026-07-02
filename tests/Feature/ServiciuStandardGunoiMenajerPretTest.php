<?php

namespace Tests\Feature;

use App\Models\ServiciuStandardAnexa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiciuStandardGunoiMenajerPretTest extends TestCase
{
    use RefreshDatabase;

    public function test_salveaza_pret_persoana_suplimentara_doar_pentru_gunoi_menajer(): void
    {
        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Servicii Gunoi Menajer',
            'label' => 'Servicii Gunoi Menajer',
            'activ' => true,
        ]);

        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Curatenie Spatii Comune / pers',
            'label' => 'Curatenie Spatii Comune / pers',
            'activ' => true,
        ]);

        $gunoi = ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'valoare' => 'Servicii Gunoi Menajer',
            'label' => 'Dumbravita',
            'coeficient' => 51.89,
            'activ' => true,
        ]);

        $curatenie = ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'valoare' => 'Curatenie Spatii Comune / pers',
            'label' => 'Standard',
            'coeficient' => 35.04,
            'activ' => true,
        ]);

        $this->put(route('configurare-anexa.servicii-standard.pret.bulk'), [
            'preturi' => [
                [
                    'id' => $gunoi->id,
                    'label' => 'Dumbravita',
                    'coeficient' => '51.89',
                    'pret_persoana_suplimentara' => '25',
                    'coeficient_cantitate' => '100',
                    'moneda' => 'RON',
                    'tva' => '21',
                    'um' => 'Pers',
                ],
                [
                    'id' => $curatenie->id,
                    'label' => 'Standard',
                    'coeficient' => '35.04',
                    'pret_persoana_suplimentara' => '25',
                    'coeficient_cantitate' => '100',
                    'moneda' => 'RON',
                    'tva' => '21',
                    'um' => 'Pers',
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('servicii_standard_anexa', [
            'id' => $gunoi->id,
            'pret_persoana_suplimentara' => 25,
        ]);

        $this->assertDatabaseHas('servicii_standard_anexa', [
            'id' => $curatenie->id,
            'pret_persoana_suplimentara' => null,
        ]);
    }
}
