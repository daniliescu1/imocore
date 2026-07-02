<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Imobil;
use App\Models\ServiciuStandardAnexa;
use App\Models\Spatiu;
use App\Support\GenerareAnexaLinieCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiciuStandardPretVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_poate_adauga_varianta_pret_cu_coeficient_cantitate(): void
    {
        ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_DENUMIRE,
            'valoare' => 'Energie Electrica',
            'label' => 'Energie Electrica',
            'activ' => true,
        ]);

        $standard = ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'valoare' => 'Energie Electrica',
            'label' => 'Standard',
            'coeficient' => 1.528,
            'coeficient_cantitate' => 1,
            'um' => 'Kw',
            'tva' => '21',
            'activ' => true,
        ]);

        $this->post(route('configurare-anexa.servicii-standard.pret.variant'), [
            'valoare' => 'Energie Electrica',
            'label' => 'Repartizare 20%',
            'coeficient' => '1.528',
            'coeficient_cantitate' => '20',
            'moneda' => 'RON',
            'tva' => '21',
            'um' => 'Kw',
        ])->assertRedirect();

        $this->assertDatabaseHas('servicii_standard_anexa', [
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'valoare' => 'Energie Electrica',
            'label' => 'Repartizare 20%',
            'coeficient' => 1.528,
            'coeficient_cantitate' => 0.2,
        ]);

        $this->assertSame(2, ServiciuStandardAnexa::query()->where('tip', ServiciuStandardAnexa::TIP_PRET)->count());
        $this->assertNotNull($standard->fresh());
    }

    public function test_generarea_anexei_aplica_coeficientul_cantitatii_din_varianta_aleasa(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'localitate' => 'Timișoara',
            'strada' => 'Str. Test',
            'numar' => '1',
        ]);

        $variant = ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'valoare' => 'Energie Electrica',
            'label' => 'Repartizare 20%',
            'coeficient' => 1.528,
            'coeficient_cantitate' => 0.2,
            'activ' => true,
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'activ' => true,
        ]);

        $linie = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Energie Electrica',
            'serviciu_standard_pret_id' => $variant->id,
            'tip_calcul' => 'contor',
            'pret_unitar' => 1.528,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        \App\Models\CitireContor::query()->create([
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'index_vechi' => 1000,
            'index_nou' => 1500,
            'consum' => 500,
        ]);

        $result = GenerareAnexaLinieCalculator::calculate($spatiu, $linie, '2026-06', '2026-07');

        $this->assertSame(100.0, (float) $result['cantitate']);
        $this->assertSame(152.8, (float) $result['valoare']);
    }

    public function test_generarea_anexei_aplica_corect_coeficientul_120_procente(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Dumbravita Office',
            'localitate' => 'Dumbravita',
            'strada' => 'Conac',
            'numar' => '60',
        ]);

        $variant = ServiciuStandardAnexa::query()->create([
            'tip' => ServiciuStandardAnexa::TIP_PRET,
            'valoare' => 'Energie Electrica',
            'label' => '+ 20%',
            'coeficient' => 1.528,
            'coeficient_cantitate' => 1.2,
            'activ' => true,
        ]);

        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'activ' => true,
        ]);

        $linie = ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Energie Electrica',
            'serviciu_standard_pret_id' => $variant->id,
            'tip_calcul' => 'contor',
            'pret_unitar' => 1.528,
            'activ' => true,
        ]);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'Spatiul nr.1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        \App\Models\CitireContor::query()->create([
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linie->id,
            'luna' => '2026-06',
            'index_vechi' => 22425.05,
            'index_nou' => 25731.37,
            'consum' => 3306.32,
        ]);

        $result = GenerareAnexaLinieCalculator::calculate($spatiu, $linie, '2026-06', '2026-07');

        $this->assertSame(3967.584, (float) $result['cantitate']);
        $this->assertSame(6062.468, round((float) $result['valoare'], 3));
    }
}
