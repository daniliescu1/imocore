<?php

namespace Tests\Feature;

use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Contor;
use App\Models\Imobil;
use App\Models\Spatiu;
use App\Support\PropagareLiniiAnexaPersonalizata;
use App\Support\SincronizareContoareDinAnexa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropagareLiniiAnexaPersonalizataTest extends TestCase
{
    use RefreshDatabase;

    public function test_salvarea_anexei_sablon_propaga_linii_contor_in_copia_personalizata(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'localitate' => 'Timișoara',
            'strada' => 'Str. Test',
            'numar' => '1',
        ]);

        $sablon = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa Pers < 50 mp · HQD 110',
            'implicit' => false,
            'activ' => true,
        ]);

        $copie = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa Pers < 50 mp · HQD 110 · HQD 110',
            'implicit' => false,
            'activ' => true,
        ]);

        $this->creeazaLinie($sablon, 'Consum apa - mc / pers', 1, 'Pausal');
        $this->creeazaLinie($sablon, 'Canalizare mc / pers', 2, 'Pausal');
        $this->creeazaLinie($sablon, 'Consum apa - mc / pers', 3, 'contor');
        $this->creeazaLinie($sablon, 'Canalizare mc / pers', 4, 'contor');

        $this->creeazaLinie($copie, 'Consum apa - mc / pers', 1, 'Pausal');
        $this->creeazaLinie($copie, 'Canalizare mc / pers', 2, 'Pausal');

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'HQD 110',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $copie->id,
        ]);

        SincronizareContoareDinAnexa::syncForSpatiu($spatiu);
        $this->assertDatabaseCount('contoare', 0);

        PropagareLiniiAnexaPersonalizata::syncFromTemplate($sablon->fresh('linii'));

        $this->assertSame(4, $copie->fresh()->linii()->count());
        $this->assertSame(2, $copie->fresh()->linii()->whereRaw('lower(trim(tip_calcul)) = ?', ['contor'])->count());
        $this->assertSame(2, Contor::query()->where('spatiu_id', $spatiu->id)->count());
        $this->assertDatabaseHas('contoare', [
            'spatiu_id' => $spatiu->id,
            'tip_utilitate' => 'Consum apa - mc / pers',
        ]);
    }

    public function test_actualizarea_anexei_sablon_propaga_automat_copiile_personalizate(): void
    {
        $imobil = Imobil::query()->create([
            'nume' => 'Imobil test',
            'localitate' => 'Timișoara',
            'strada' => 'Str. Test',
            'numar' => '1',
        ]);

        $sablon = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa sablon',
            'implicit' => false,
            'activ' => true,
        ]);

        $copie = ConfigurareAnexaImobil::query()->create([
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa sablon · A1',
            'implicit' => false,
            'activ' => true,
        ]);

        $liniePausal = $this->creeazaLinie($sablon, 'Consum apa - mc / pers', 1, 'Pausal');
        $this->creeazaLinie($copie, 'Consum apa - mc / pers', 1, 'Pausal');

        $spatiu = Spatiu::query()->create([
            'imobil_id' => $imobil->id,
            'identificator' => 'A1',
            'status' => 'inchiriat',
            'configurare_anexa_id' => $copie->id,
        ]);

        $this->put(route('configurare-anexa.update', $sablon), [
            'imobil_id' => $imobil->id,
            'denumire' => 'Anexa sablon',
            'implicit' => false,
            'activ' => true,
            'linii' => [
                [
                    'id' => $liniePausal->id,
                    'tip_linie' => 'serviciu',
                    'denumire' => 'Consum apa - mc / pers',
                    'nr_crt' => 1,
                    'tip_calcul' => 'Pausal',
                    'um' => 'MC',
                    'activ' => true,
                ],
                [
                    'tip_linie' => 'serviciu',
                    'denumire' => 'Consum apa - mc / pers',
                    'nr_crt' => 2,
                    'tip_calcul' => 'contor',
                    'um' => 'MC',
                    'pret_unitar' => 9.74,
                    'tva_21' => 11,
                    'activ' => true,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(2, $copie->fresh()->linii()->count());
        $this->assertSame(1, $copie->fresh()->linii()->whereRaw('lower(trim(tip_calcul)) = ?', ['contor'])->count());

        SincronizareContoareDinAnexa::syncForSpatiu($spatiu->fresh());
        $this->assertSame(1, Contor::query()->where('spatiu_id', $spatiu->id)->count());
    }

    private function creeazaLinie(
        ConfigurareAnexaImobil $configurare,
        string $denumire,
        int $nrCrt,
        string $tipCalcul,
    ): ConfigurareAnexaLinie {
        return ConfigurareAnexaLinie::query()->create([
            'configurare_anexa_id' => $configurare->id,
            'ordine' => $nrCrt,
            'denumire' => $denumire,
            'nr_crt' => $nrCrt,
            'tip_calcul' => $tipCalcul,
            'um' => 'MC',
            'activ' => true,
        ]);
    }
}
