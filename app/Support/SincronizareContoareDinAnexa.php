<?php

namespace App\Support;

use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Contor;
use App\Models\Spatiu;

class SincronizareContoareDinAnexa
{
    public static function syncForImobil(int $imobilId): void
    {
        Spatiu::query()
            ->where('imobil_id', $imobilId)
            ->orderBy('id')
            ->each(fn (Spatiu $spatiu): mixed => self::syncForSpatiu($spatiu));
    }

    public static function syncForConfigurare(ConfigurareAnexaImobil $configurare): void
    {
        Spatiu::query()
            ->where('configurare_anexa_id', $configurare->id)
            ->orderBy('id')
            ->each(fn (Spatiu $spatiu): mixed => self::syncForSpatiu($spatiu));
    }

    public static function syncForSpatiu(Spatiu $spatiu): void
    {
        $spatiu->loadMissing(['configurareAnexa.linii']);

        $liniiContor = $spatiu->configurare_anexa_id && $spatiu->configurareAnexa
            ? $spatiu->configurareAnexa->linii->filter(
                fn (ConfigurareAnexaLinie $linie): bool => TipCalculAnexa::isCitire($linie->tip_calcul)
                    && ($linie->tip_linie ?: 'serviciu') !== 'header'
                    && $linie->activ
            )->values()
            : collect();

        $keepIds = [];

        foreach ($liniiContor as $linie) {
            $contor = Contor::query()->firstOrNew([
                'spatiu_id' => $spatiu->id,
                'configurare_anexa_linie_id' => $linie->id,
            ]);

            if (! $contor->exists) {
                $contor->cod_contor = self::codContorFor($spatiu, $linie);
            }

            $contor->fill([
                'imobil_id' => $spatiu->imobil_id,
                'tip_utilitate' => $linie->denumire,
                'nivel' => 'spatiu',
                'activ' => true,
            ]);
            $contor->save();

            $keepIds[] = $contor->id;
        }

        Contor::query()
            ->where('spatiu_id', $spatiu->id)
            ->whereNotNull('configurare_anexa_linie_id')
            ->when($keepIds !== [], fn ($query) => $query->whereNotIn('id', $keepIds))
            ->when($keepIds === [], fn ($query) => $query)
            ->delete();
    }

    private static function codContorFor(Spatiu $spatiu, ConfigurareAnexaLinie $linie): string
    {
        return trim($spatiu->identificator.' · '.$linie->denumire);
    }
}
