<?php

namespace App\Support;

use App\Models\CitireContor;
use App\Models\ConfigurareAnexaImobil;
use App\Models\ConfigurareAnexaLinie;
use App\Models\Contor;

class PropagareLiniiAnexaPersonalizata
{
    public static function syncFromTemplate(ConfigurareAnexaImobil $template): int
    {
        $template->loadMissing('linii');
        $prefix = trim($template->denumire);

        if ($prefix === '') {
            return 0;
        }

        $derivate = ConfigurareAnexaImobil::query()
            ->where('imobil_id', $template->imobil_id)
            ->whereKeyNot($template->id)
            ->where('denumire', 'like', $prefix.' · %')
            ->get();

        foreach ($derivate as $derivata) {
            self::syncLinii($template, $derivata);
            SincronizareContoareDinAnexa::syncForConfigurare($derivata);
            ContorConfigurabilSync::syncForConfigurare($derivata);
        }

        return $derivate->count();
    }

    private static function syncLinii(ConfigurareAnexaImobil $template, ConfigurareAnexaImobil $derivata): void
    {
        $derivata->loadMissing('linii');
        $matchedChildIds = [];
        $headerIndex = 0;

        foreach ($template->linii->sortBy('ordine')->values() as $index => $parentLinie) {
            $tipLinie = ($parentLinie->tip_linie ?: 'serviciu') === 'header' ? 'header' : 'serviciu';
            $childLinie = $tipLinie === 'header'
                ? self::findMatchingHeaderLinie($derivata->linii, $matchedChildIds, $headerIndex++)
                : self::findMatchingLinie($derivata->linii, $parentLinie, $matchedChildIds);

            $values = self::templateValuesFrom($parentLinie, $index + 1);

            if ($childLinie) {
                $childLinie->update($values);
                $matchedChildIds[] = $childLinie->id;
            } else {
                $noua = $derivata->linii()->create($values);
                $matchedChildIds[] = $noua->id;
            }
        }

        $toRemove = $derivata->linii()
            ->when($matchedChildIds !== [], fn ($query) => $query->whereNotIn('id', $matchedChildIds))
            ->pluck('id');

        if ($toRemove->isEmpty()) {
            return;
        }

        self::deleteLiniiDependente($toRemove->all());
        $derivata->linii()->whereIn('id', $toRemove)->delete();
    }

    private static function findMatchingLinie($childLinii, ConfigurareAnexaLinie $parentLinie, array $excludeIds): ?ConfigurareAnexaLinie
    {
        $candidates = $childLinii->filter(fn (ConfigurareAnexaLinie $linie): bool => ! in_array($linie->id, $excludeIds, true));

        $exact = $candidates->first(
            fn (ConfigurareAnexaLinie $linie): bool => self::linieSignature($linie) === self::linieSignature($parentLinie)
        );

        if ($exact) {
            return $exact;
        }

        $partialMatches = $candidates->filter(
            fn (ConfigurareAnexaLinie $linie): bool => self::linieSignatureFaraTipCalcul($linie) === self::linieSignatureFaraTipCalcul($parentLinie)
        );

        return $partialMatches->count() === 1 ? $partialMatches->first() : null;
    }

    private static function findMatchingHeaderLinie($childLinii, array $excludeIds, int $headerIndex): ?ConfigurareAnexaLinie
    {
        $headers = $childLinii
            ->filter(fn (ConfigurareAnexaLinie $linie): bool => ! in_array($linie->id, $excludeIds, true))
            ->filter(fn (ConfigurareAnexaLinie $linie): bool => ($linie->tip_linie ?: 'serviciu') === 'header')
            ->sortBy('ordine')
            ->values();

        return $headers->get($headerIndex);
    }

    private static function linieSignature(ConfigurareAnexaLinie $linie): string
    {
        return self::linieSignatureFaraTipCalcul($linie).'|'.TipCalculAnexa::normalize($linie->tip_calcul);
    }

    private static function linieSignatureFaraTipCalcul(ConfigurareAnexaLinie $linie): string
    {
        $tipLinie = ($linie->tip_linie ?: 'serviciu') === 'header' ? 'header' : 'serviciu';

        return implode('|', [
            $tipLinie,
            (string) ($linie->nr_crt ?? ''),
            mb_strtolower(trim((string) $linie->denumire)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function templateValuesFrom(ConfigurareAnexaLinie $linie, int $ordine): array
    {
        return [
            'ordine' => $ordine,
            'tip_linie' => $linie->tip_linie ?: 'serviciu',
            'denumire' => $linie->denumire,
            'nr_crt' => $linie->nr_crt,
            'index_vechi' => $linie->index_vechi,
            'index_nou' => $linie->index_nou,
            'facturat' => $linie->facturat,
            'coeficient' => $linie->coeficient,
            'um' => $linie->um,
            'pret_unitar' => $linie->pret_unitar,
            'moneda' => $linie->moneda,
            'valoare' => $linie->valoare,
            'tva_21' => $linie->tva_21,
            'tip_calcul' => $linie->tip_calcul,
            'apare_cu_zero' => $linie->apare_cu_zero,
            'activ' => $linie->activ,
            'observatii' => $linie->observatii,
        ];
    }

    /**
     * @param  list<int>  $linieIds
     */
    private static function deleteLiniiDependente(array $linieIds): void
    {
        if ($linieIds === []) {
            return;
        }

        CitireContor::query()->whereIn('configurare_anexa_linie_id', $linieIds)->delete();
        Contor::query()->whereIn('configurare_anexa_linie_id', $linieIds)->delete();
    }
}
