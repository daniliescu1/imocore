<?php

namespace App\Support;

use App\Models\ConfigurareAnexaLinie;
use App\Models\Factura;
use App\Models\ServiciuStandardAnexa;
use App\Models\SetareAplicatie;

class PretServiciuStandard
{
    public const MONEDA_RON = 'RON';

    public const MONEDA_EUR = 'EUR';

    public static function normalizeMoneda(?string $moneda): string
    {
        $moneda = strtoupper(trim((string) $moneda));

        return $moneda === self::MONEDA_EUR ? self::MONEDA_EUR : self::MONEDA_RON;
    }

    public static function cursEur(): float
    {
        $cursSalvat = SetareAplicatie::valoare('curs_eur_facturare');

        if ($cursSalvat) {
            return (float) $cursSalvat;
        }

        return (float) (Factura::query()->latest()->value('curs_eur') ?: 5);
    }

    public static function pretUnitarLei(mixed $coeficient, ?string $moneda, ?float $curs = null): ?string
    {
        if ($coeficient === null || $coeficient === '') {
            return null;
        }

        $pret = (float) $coeficient;

        if (self::normalizeMoneda($moneda) === self::MONEDA_EUR) {
            $curs = $curs ?? self::cursEur();

            return number_format(round($pret * $curs, 4), 4, '.', '');
        }

        return number_format(round($pret, 4), 4, '.', '');
    }

    public static function valoareLeiDinPretEur(mixed $coeficient, ?float $curs = null): ?string
    {
        if ($coeficient === null || $coeficient === '') {
            return null;
        }

        $curs = $curs ?? self::cursEur();

        return number_format(round((float) $coeficient * $curs, 2), 2, '.', '');
    }

    public static function propagateToLinii(
        ServiciuStandardAnexa $pretRecord,
        mixed $coeficient,
        ?string $moneda,
        ?string $tva,
        ?string $um,
        ?float $curs = null,
    ): void {
        $moneda = self::normalizeMoneda($moneda);
        $curs = $curs ?? self::cursEur();
        $pretUnitarLei = self::pretUnitarLei($coeficient, $moneda, $curs);

        $updates = array_filter([
            'moneda' => $moneda,
            'pret_unitar' => $pretUnitarLei,
            'tva_21' => $tva,
            'um' => $um,
        ], fn ($value) => $value !== null && $value !== '');

        if ($updates === []) {
            return;
        }

        $linkedQuery = ConfigurareAnexaLinie::query()
            ->where('serviciu_standard_pret_id', $pretRecord->id);

        if ($linkedQuery->exists()) {
            $linkedQuery->update($updates);
        } else {
            ConfigurareAnexaLinie::query()
                ->where('denumire', $pretRecord->valoare)
                ->whereNull('serviciu_standard_pret_id')
                ->when(
                    ServiciuStandardAnexa::variantLabel($pretRecord) !== 'Standard',
                    fn ($query) => $query->whereRaw('0 = 1'),
                )
                ->update($updates);
        }

        if ($moneda !== self::MONEDA_EUR || $coeficient === null || $coeficient === '') {
            return;
        }

        $valoareLei = self::valoareLeiDinPretEur($coeficient, $curs);
        $eurQuery = ConfigurareAnexaLinie::query()
            ->where(function ($query) use ($pretRecord): void {
                $query->where('serviciu_standard_pret_id', $pretRecord->id)
                    ->orWhere(function ($query) use ($pretRecord): void {
                        $query->where('denumire', $pretRecord->valoare)
                            ->whereNull('serviciu_standard_pret_id');
                    });
            })
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['fix'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['administrare'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['manual'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['zero']);
            });

        $eurQuery->update([
            'facturat' => $coeficient,
            'valoare' => $valoareLei,
        ]);
    }
}
