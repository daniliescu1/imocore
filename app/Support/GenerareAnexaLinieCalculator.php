<?php

namespace App\Support;

use App\Models\CitireContor;
use App\Models\ConfigurareAnexaLinie;
use App\Models\ContorConfigurabil;
use App\Models\ServiciuStandardAnexa;
use App\Models\Spatiu;

class GenerareAnexaLinieCalculator
{
    /**
     * @return array{
     *     ordine: int,
     *     tip_linie: string,
     *     nr_crt: int|null,
     *     denumire: string,
     *     um: string|null,
     *     tip_calcul: string|null,
     *     index_vechi: mixed,
     *     index_nou: mixed,
     *     cantitate: mixed,
     *     coeficient: mixed,
     *     pret_unitar: mixed,
     *     valoare: float,
     *     tva_21: float|null
     * }
     */
    public static function calculate(
        Spatiu $spatiu,
        ConfigurareAnexaLinie $linieConfigurata,
        string $lunaUtilitati,
        string $lunaFacturare,
    ): array {
        $indexVechi = null;
        $indexNou = null;
        $cantitate = null;
        $coeficient = null;
        $tipCalcul = $linieConfigurata->tip_calcul;

        if (self::tipCalculMpCoeficient($tipCalcul)) {
            $suprafataMp = (float) ($spatiu->suprafata_contractuala_mp ?? 0);
            $coeficient = self::coeficientMpPentruLinie($linieConfigurata);
            $indexVechi = $suprafataMp > 0 ? $suprafataMp : null;
            $indexNou = $coeficient > 0 ? $coeficient : null;
            $cantitate = ($suprafataMp > 0 && $coeficient > 0)
                ? round($suprafataMp * $coeficient, 3)
                : null;
        } elseif (self::tipCalculPeMp($tipCalcul)) {
            $suprafataMp = (float) ($spatiu->suprafata_contractuala_mp ?? 0);
            $cantitate = $suprafataMp > 0 ? round($suprafataMp, 3) : null;
        } elseif ($tipCalcul === 'persoane') {
            $cantitate = $spatiu->persoanePentruAnexa();
        } elseif (TipCalculAnexa::isContorConfigurabil($tipCalcul)) {
            if ($spatiu->status === 'inchiriat') {
                $regula = ContorConfigurabil::query()
                    ->where('configurare_anexa_linie_id', $linieConfigurata->id)
                    ->first();

                if ($regula) {
                    $calcul = ContorConfigurabilCalculator::cantitatePentruSpatiu(
                        $regula,
                        $spatiu->id,
                        $lunaUtilitati,
                        $lunaFacturare,
                    );
                    $indexVechi = $calcul['index_vechi'];
                    $indexNou = $calcul['index_nou'];
                    $cantitate = $calcul['cantitate'];
                }
            }
        } elseif (TipCalculAnexa::isPausal($tipCalcul)) {
            if ($spatiu->status === 'inchiriat') {
                $regula = ContorConfigurabil::query()
                    ->where('configurare_anexa_linie_id', $linieConfigurata->id)
                    ->first();

                if ($regula) {
                    $calcul = ContorConfigurabilCalculator::cantitatePentruSpatiu(
                        $regula,
                        $spatiu->id,
                        $lunaUtilitati,
                        $lunaFacturare,
                    );
                    $cantitate = $calcul['cantitate'];
                } else {
                    $citire = self::citirePentruAnexa($spatiu->id, $linieConfigurata->id, $lunaUtilitati, $lunaFacturare);

                    if ($citire) {
                        if (TipCalculAnexa::folosesteIndexLaCitire($tipCalcul, $linieConfigurata->denumire, $citire)) {
                            $indexVechi = $citire->index_vechi;
                            $indexNou = $citire->index_nou;
                            $cantitate = max(0, (float) $citire->index_nou - (float) $citire->index_vechi);
                        } else {
                            $cantitate = $citire->consum;
                        }
                    }
                }
            }
        } elseif ($tipCalcul === 'contor') {
            if ($spatiu->status === 'inchiriat') {
                $citire = self::citirePentruAnexa($spatiu->id, $linieConfigurata->id, $lunaUtilitati, $lunaFacturare);

                if ($citire) {
                    $indexVechi = $citire->index_vechi;
                    $indexNou = $citire->index_nou;
                    $cantitate = $citire->consum;
                }
            }
        } elseif (TipCalculAnexa::folosesteFacturatDinTemplate($tipCalcul)) {
            $cantitate = TipCalculAnexa::cantitateDinTemplateLinie(
                $tipCalcul,
                $linieConfigurata->facturat,
                $linieConfigurata->pret_unitar,
                $linieConfigurata->valoare,
            );
        }

        $pretUnitar = $linieConfigurata->pret_unitar;
        $valoare = $linieConfigurata->valoare;
        $moneda = PretServiciuStandard::normalizeMoneda($linieConfigurata->moneda);

        if ($moneda === PretServiciuStandard::MONEDA_EUR) {
            $pretEur = ServiciuStandardAnexa::pretPentruDenumire((string) $linieConfigurata->denumire)
                ?? $linieConfigurata->facturat;
            $curs = PretServiciuStandard::cursEur();
            $pretUnitar = PretServiciuStandard::pretUnitarLei($pretEur, $moneda, $curs);

            if (TipCalculAnexa::folosesteFacturatDinTemplate($tipCalcul)) {
                $cantitate = $pretEur !== null && $pretEur !== ''
                    ? (float) $pretEur
                    : $cantitate;
                $valoare = (float) (PretServiciuStandard::valoareLeiDinPretEur($pretEur, $curs) ?? 0);
            }
        }

        if ($cantitate !== null && $pretUnitar !== null && $moneda !== PretServiciuStandard::MONEDA_EUR) {
            $valoare = (float) $cantitate * (float) $pretUnitar;
        } elseif ($cantitate !== null && $pretUnitar !== null && $moneda === PretServiciuStandard::MONEDA_EUR && ! TipCalculAnexa::folosesteFacturatDinTemplate($tipCalcul)) {
            $valoare = (float) $cantitate * (float) $pretUnitar;
        }

        $valoare = (float) ($valoare ?? 0);
        $procentTva = (float) ($linieConfigurata->tva_21 ?? 0);
        $sumaTva = $procentTva > 0 ? round($valoare * $procentTva / 100, 2) : null;

        return [
            'ordine' => $linieConfigurata->ordine,
            'tip_linie' => 'serviciu',
            'nr_crt' => $linieConfigurata->nr_crt,
            'denumire' => $linieConfigurata->denumire,
            'um' => $linieConfigurata->um,
            'tip_calcul' => $linieConfigurata->tip_calcul,
            'index_vechi' => $indexVechi,
            'index_nou' => $indexNou,
            'cantitate' => $cantitate,
            'coeficient' => $coeficient,
            'pret_unitar' => $pretUnitar,
            'moneda' => $moneda,
            'valoare' => $valoare,
            'tva_21' => $sumaTva,
        ];
    }

    private static function tipCalculPeMp(?string $tipCalcul): bool
    {
        return in_array($tipCalcul, ['mp', 'pe_mp'], true);
    }

    private static function tipCalculMpCoeficient(?string $tipCalcul): bool
    {
        $normalized = str_replace([' ', '*', '×', '_', '-'], '', strtolower((string) $tipCalcul));

        return str_starts_with($normalized, 'mp') && str_contains($normalized, 'coeficient');
    }

    private static function coeficientMpPentruLinie(ConfigurareAnexaLinie $linieConfigurata): float
    {
        $coeficient = (float) ($linieConfigurata->coeficient ?? 0);

        if ($coeficient > 0 && $coeficient <= 1) {
            return $coeficient;
        }

        $indexNou = (float) ($linieConfigurata->index_nou ?? 0);

        return $indexNou > 0 && $indexNou <= 1 ? $indexNou : 0.09;
    }

    private static function citirePentruAnexa(int $spatiuId, int $linieId, string $lunaUtilitati, string $lunaFacturare): ?CitireContor
    {
        return CitireContor::query()
            ->where('spatiu_id', $spatiuId)
            ->where('configurare_anexa_linie_id', $linieId)
            ->whereIn('luna', array_unique([$lunaUtilitati, $lunaFacturare]))
            ->orderByRaw('case when luna = ? then 0 else 1 end', [$lunaUtilitati])
            ->first();
    }
}
