<?php

namespace App\Support;

use App\Models\CitireContor;
use App\Models\ContorConfigurabil;
use App\Models\Spatiu;
use Carbon\Carbon;

class ContorConfigurabilCalculator
{
    /**
     * @return array{index_vechi: float|null, index_nou: float|null, consum_brut: float|null, cantitate: float|null}
     */
    public static function cantitatePentruSpatiu(
        ContorConfigurabil $regula,
        int $spatiuId,
        string $lunaUtilitati,
        string $lunaFacturare,
    ): array {
        $alocari = $regula->alocariEfectiveIds();

        if (! in_array($spatiuId, $alocari, true) || $alocari === []) {
            return [
                'index_vechi' => null,
                'index_nou' => null,
                'consum_brut' => null,
                'cantitate' => null,
            ];
        }

        $citire = self::citireContorConfigurabil($regula->configurare_anexa_linie_id, $lunaUtilitati, $lunaFacturare);

        if (! $citire) {
            return [
                'index_vechi' => null,
                'index_nou' => null,
                'consum_brut' => null,
                'cantitate' => null,
            ];
        }

        $regula->loadMissing('configurareAnexaLinie');
        $isPausal = TipCalculAnexa::isPausal($regula->configurareAnexaLinie?->tip_calcul);
        $consumBrut = (float) $citire->consum;
        $scaderi = 0.0;

        if ($regula->foloseste_scaderi) {
            foreach ($regula->scaderiNormalizate() as $scadere) {
                $scaderi += self::consumCitireSpatiu(
                    $scadere['spatiu_id'],
                    $scadere['configurare_anexa_linie_id'],
                    $lunaUtilitati,
                    $lunaFacturare,
                );
            }
        }

        $rest = max(0, $consumBrut - $scaderi);
        $linie = $regula->configurareAnexaLinie;
        $cantitate = TipCalculAnexa::isPausalApaCanalizarePePersoana($linie?->tip_calcul, $linie?->denumire)
            ? self::cantitatePausalPePersoanaPentruSpatiu($regula, $spatiuId, $rest)
            : round($rest / count($alocari), 3);

        return [
            'index_vechi' => $isPausal ? null : (float) $citire->index_vechi,
            'index_nou' => $isPausal ? null : (float) $citire->index_nou,
            'consum_brut' => $consumBrut,
            'cantitate' => $cantitate,
        ];
    }

    public static function citireContorConfigurabil(int $linieId, string $lunaUtilitati, string $lunaFacturare): ?CitireContor
    {
        return CitireContor::query()
            ->whereNull('spatiu_id')
            ->where('configurare_anexa_linie_id', $linieId)
            ->whereIn('luna', array_unique([$lunaUtilitati, $lunaFacturare]))
            ->orderByRaw('case when luna = ? then 0 else 1 end', [$lunaUtilitati])
            ->first();
    }

    public static function consumCitireSpatiu(int $spatiuId, int $linieId, string $lunaUtilitati, string $lunaFacturare): float
    {
        $citire = CitireContor::query()
            ->where('spatiu_id', $spatiuId)
            ->where('configurare_anexa_linie_id', $linieId)
            ->whereIn('luna', array_unique([$lunaUtilitati, $lunaFacturare]))
            ->orderByRaw('case when luna = ? then 0 else 1 end', [$lunaUtilitati])
            ->first();

        if (! $citire) {
            return 0.0;
        }

        $consum = (float) $citire->consum;

        if ($consum > 0) {
            return $consum;
        }

        return max(0, (float) $citire->index_nou - (float) $citire->index_vechi);
    }

    public static function lunaFacturareDinUtilitati(string $lunaUtilitati): string
    {
        return Carbon::createFromFormat('Y-m', $lunaUtilitati)->addMonth()->format('Y-m');
    }

    private static function cantitatePausalPePersoanaPentruSpatiu(
        ContorConfigurabil $regula,
        int $spatiuId,
        float $rest,
    ): float {
        $alocari = $regula->alocariEfectiveIds();
        $spatii = Spatiu::query()
            ->whereIn('id', $alocari)
            ->get()
            ->keyBy('id');

        $totalPersoane = (int) $spatii->sum(fn (Spatiu $spatiu): int => $spatiu->persoanePentruAnexa());

        if ($totalPersoane <= 0) {
            return count($alocari) > 0 ? round($rest / count($alocari), 3) : 0.0;
        }

        $spatiu = $spatii->get($spatiuId);

        if (! $spatiu) {
            return 0.0;
        }

        return round(($rest / $totalPersoane) * $spatiu->persoanePentruAnexa(), 3);
    }
}
