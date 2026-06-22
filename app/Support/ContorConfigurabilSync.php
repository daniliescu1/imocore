<?php

namespace App\Support;

use App\Models\CitireContor;
use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Contor;
use App\Models\ContorConfigurabil;
use App\Models\Imobil;
use App\Models\Spatiu;

class ContorConfigurabilSync
{
    public static function syncForImobil(int $imobilId): void
    {
        ConfigurareAnexaImobil::query()
            ->where('imobil_id', $imobilId)
            ->each(fn (ConfigurareAnexaImobil $configurare): mixed => self::syncForConfigurare($configurare));
    }

    public static function syncForConfigurare(ConfigurareAnexaImobil $configurare): void
    {
        $configurare->loadMissing('linii');
        $keepLinieIds = [];

        foreach ($configurare->linii as $linie) {
            if (($linie->tip_linie ?: 'serviciu') === 'header' || ! $linie->activ) {
                continue;
            }

            if (! TipCalculAnexa::needsConfigurareContoare($linie->tip_calcul)) {
                continue;
            }

            $keepLinieIds[] = $linie->id;

            $alocariDefault = Spatiu::query()
                ->where('configurare_anexa_id', $configurare->id)
                ->orderBy('identificator')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $regula = ContorConfigurabil::query()->firstOrNew([
                'configurare_anexa_linie_id' => $linie->id,
            ]);

            $regula->fill([
                'imobil_id' => $configurare->imobil_id,
                'configurare_anexa_id' => $configurare->id,
                'alocari' => $regula->exists && $regula->foloseste_scaderi
                    ? array_values(array_intersect($regula->alocariIds(), $alocariDefault))
                    : $alocariDefault,
                'scaderi' => collect($regula->scaderiNormalizate())
                    ->filter(fn (array $scadere): bool => in_array($scadere['spatiu_id'], $alocariDefault, true))
                    ->values()
                    ->all(),
            ]);
            $regula->save();

            self::ensureContorImobil($configurare->imobil_id, $linie);
            self::eliminaCitiriPeSpatiuPentruLinie($linie->id);
        }

        $sterge = ContorConfigurabil::query()
            ->where('configurare_anexa_id', $configurare->id)
            ->when($keepLinieIds !== [], fn ($query) => $query->whereNotIn('configurare_anexa_linie_id', $keepLinieIds))
            ->when($keepLinieIds === [], fn ($query) => $query)
            ->get();

        foreach ($sterge as $regula) {
            Contor::query()
                ->whereNull('spatiu_id')
                ->where('configurare_anexa_linie_id', $regula->configurare_anexa_linie_id)
                ->delete();
            $regula->delete();
        }
    }

    private static function ensureContorImobil(int $imobilId, ConfigurareAnexaLinie $linie): void
    {
        $imobil = Imobil::query()->find($imobilId);

        Contor::query()->updateOrCreate(
            [
                'spatiu_id' => null,
                'configurare_anexa_linie_id' => $linie->id,
            ],
            [
                'imobil_id' => $imobilId,
                'tip_utilitate' => $linie->denumire,
                'cod_contor' => trim(($imobil?->nume ?: 'Imobil').' · '.$linie->denumire),
                'nivel' => 'imobil_configurabil',
                'activ' => true,
            ]
        );
    }

    private static function eliminaCitiriPeSpatiuPentruLinie(int $linieId): void
    {
        CitireContor::query()
            ->where('configurare_anexa_linie_id', $linieId)
            ->whereNotNull('spatiu_id')
            ->delete();

        Contor::query()
            ->where('configurare_anexa_linie_id', $linieId)
            ->whereNotNull('spatiu_id')
            ->delete();
    }
}
