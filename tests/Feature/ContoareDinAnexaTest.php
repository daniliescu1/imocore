<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\SincronizareContoareDinAnexa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContoareDinAnexaTest extends TestCase
{
    use RefreshDatabase;

    public function test_contoare_se_creeaza_automat_cand_spatiu_are_anexa_cu_linii_contor(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linieApa = $this->creeazaLinieContor($configurare, 'Apă rece');
        $this->creeazaLinieContor($configurare, 'Curent', 2);
        $this->creeazaLinieFix($configurare);

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        SincronizareContoareDinAnexa::syncForSpatiu($spatiu);

        $this->assertDatabaseCount('contoare', 2);
        $this->assertDatabaseHas('contoare', [
            'imobil_id' => $imobil->id,
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linieApa->id,
            'tip_utilitate' => 'Apă rece',
            'cod_contor' => 'A1 · Apă rece',
            'activ' => true,
        ]);
    }

    public function test_contoare_se_creeaza_si_pentru_linii_pausal(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $linieGunoi = $this->creeazaLiniePausal($configurare, 'Gunoi menajer');

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        SincronizareContoareDinAnexa::syncForSpatiu($spatiu);

        $this->assertDatabaseCount('contoare', 1);
        $this->assertDatabaseHas('contoare', [
            'spatiu_id' => $spatiu->id,
            'configurare_anexa_linie_id' => $linieGunoi->id,
            'tip_utilitate' => 'Gunoi menajer',
            'cod_contor' => 'A1 · Gunoi menajer',
        ]);
    }

    public function test_contoare_se_sterg_cand_spatiu_pierde_anexa(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $this->creeazaLinieContor($configurare, 'Apă rece');

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        SincronizareContoareDinAnexa::syncForSpatiu($spatiu);
        $this->assertDatabaseCount('contoare', 1);

        $spatiu->update(['configurare_anexa_id' => null]);
        SincronizareContoareDinAnexa::syncForSpatiu($spatiu->fresh());

        $this->assertDatabaseCount('contoare', 0);
    }

    public function test_salvarea_spatiului_sincronizeaza_contoarele(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = $this->creeazaConfigurare($imobil);
        $this->creeazaLinieContor($configurare, 'Gaz');

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'B2',
            'status' => 'liber',
        ]);

        $this->put(route('spatii.update', $spatiu), [
            'imobil_id' => $imobil->id,
            'identificator' => 'B2',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('contoare', [
            'spatiu_id' => $spatiu->id,
            'tip_utilitate' => 'Gaz',
            'cod_contor' => 'B2 · Gaz',
        ]);
    }

    public function test_actualizarea_anexei_resincronizeaza_contoarele_spatiilor(): void
    {
        $imobil = $this->creeazaImobil();
        $configurare = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'implicit' => true,
            'activ' => true,
        ]);
        $linie = $this->creeazaLinieContor($configurare, 'Apă rece');

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $configurare->id,
        ]);

        SincronizareContoareDinAnexa::syncForSpatiu($spatiu);
        $this->assertDatabaseCount('contoare', 1);

        $this->put(route('configurare-anexa.update', $configurare), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa test',
            'implicit' => true,
            'activ' => true,
            'linii' => [
                [
                    'id' => $linie->id,
                    'tip_linie' => 'serviciu',
                    'denumire' => 'Apă rece',
                    'tip_calcul' => 'contor',
                    'um' => 'mc',
                    'activ' => true,
                ],
                [
                    'tip_linie' => 'serviciu',
                    'denumire' => 'Curent',
                    'tip_calcul' => 'contor',
                    'um' => 'kWh',
                    'activ' => true,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseCount('contoare', 2);
        $this->assertDatabaseHas('contoare', [
            'spatiu_id' => $spatiu->id,
            'tip_utilitate' => 'Curent',
        ]);
    }

    private function creeazaImobil(): Imobil
    {
        return Imobil::query()->create([
            'nume' => 'Imobil test',
            'localitate' => 'Timișoara',
            'strada' => 'Str. Test',
            'numar' => '1',
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

    private function creeazaLinieContor(ConfigurareAnexaImobil $configurare, string $denumire, int $nrCrt = 1): ConfigurareAnexaLinie
    {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => $denumire,
            'nr_crt' => $nrCrt,
            'tip_calcul' => 'contor',
            'um' => 'mc',
            'activ' => true,
        ]);
    }

    private function creeazaLinieFix(ConfigurareAnexaImobil $configurare): ConfigurareAnexaLinie
    {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => 'Administrare',
            'nr_crt' => 3,
            'tip_calcul' => 'fix',
            'um' => 'lună',
            'activ' => true,
        ]);
    }

    private function creeazaLiniePausal(ConfigurareAnexaImobil $configurare, string $denumire, int $nrCrt = 1): ConfigurareAnexaLinie
    {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'denumire' => $denumire,
            'nr_crt' => $nrCrt,
            'tip_calcul' => 'pausal',
            'um' => 'Pers',
            'activ' => true,
        ]);
    }
}
