<?php

namespace App\Support;

class TipCalculAnexa
{
    public static function normalize(?string $tipCalcul): string
    {
        $tipCalcul = trim((string) $tipCalcul);
        $normalized = str_replace([' ', '*', '×', '_', '-'], '', strtolower($tipCalcul));

        if (str_starts_with($normalized, 'mp') && str_contains($normalized, 'coeficient')) {
            return 'mp_coeficient';
        }

        if (str_contains($normalized, 'contor') && str_contains($normalized, 'configurabil')) {
            return 'contor_configurabil';
        }

        if ($normalized === 'contorfix') {
            return 'contor_fix';
        }

        if ($normalized === 'contor') {
            return 'contor';
        }

        if ($normalized === 'pausal') {
            return 'pausal';
        }

        if ($normalized === 'administrare') {
            return 'administrare';
        }

        if (in_array($normalized, ['mp', 'pemp'], true)) {
            return 'mp';
        }

        if ($normalized === 'persoane') {
            return 'persoane';
        }

        return $tipCalcul ?: 'manual';
    }

    public static function isContor(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'contor';
    }

    public static function isContorFix(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'contor_fix';
    }

    public static function isPausal(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'pausal';
    }

    public static function isPausalApaCanalizarePePersoana(?string $tipCalcul, ?string $denumire): bool
    {
        if (! self::isPausal($tipCalcul)) {
            return false;
        }

        $normalized = self::normalizeDenumireServiciu($denumire);

        if ($normalized === '') {
            return false;
        }

        if (str_contains($normalized, 'canalizare') && str_contains($normalized, 'pers')) {
            return true;
        }

        return str_contains($normalized, 'consumapa')
            || (str_contains($normalized, 'apa') && str_contains($normalized, 'mcpers'));
    }

    private static function normalizeDenumireServiciu(?string $denumire): string
    {
        $value = mb_strtolower(trim((string) $denumire));
        $value = str_replace(['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'], ['a', 'a', 'i', 's', 's', 't', 't'], $value);

        return (string) preg_replace('/[\s\-_\/\.]+/u', '', $value);
    }

    public static function isAdministrare(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'administrare';
    }

    public static function folosesteFacturatDinTemplate(?string $tipCalcul): bool
    {
        return in_array(self::normalize($tipCalcul), ['manual', 'fix', 'administrare', 'zero'], true);
    }

    public static function cantitateDinTemplateLinie(
        ?string $tipCalcul,
        mixed $facturat,
        mixed $pretUnitar,
        mixed $valoare,
    ): ?float {
        if (! self::folosesteFacturatDinTemplate($tipCalcul)) {
            return null;
        }

        if ($facturat !== null && $facturat !== '') {
            return (float) $facturat;
        }

        $pret = (float) ($pretUnitar ?? 0);
        $total = (float) ($valoare ?? 0);

        if ($pret > 0 && $total > 0) {
            return round($total / $pret, 3);
        }

        return null;
    }

    public static function isContorConfigurabil(?string $tipCalcul): bool
    {
        return self::normalize($tipCalcul) === 'contor_configurabil';
    }

    public static function needsConfigurareContoare(?string $tipCalcul): bool
    {
        return self::isContorConfigurabil($tipCalcul) || self::isPausal($tipCalcul);
    }

    public static function isCitire(?string $tipCalcul): bool
    {
        return self::isContor($tipCalcul)
            || self::isContorFix($tipCalcul)
            || self::isPausal($tipCalcul)
            || self::isContorConfigurabil($tipCalcul);
    }

    public static function applyLiniiContorScope($query)
    {
        return $query
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['pausal'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['contor configurabil']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }

    public static function applyLiniiContorSpatiuScope($query)
    {
        return $query
            ->whereRaw('lower(trim(tip_calcul)) = ?', ['contor'])
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }

    public static function applyLiniiContorFixSpatiuScope($query)
    {
        return $query
            ->whereRaw(
                'lower(replace(replace(replace(trim(tip_calcul), " ", ""), "_", ""), "-", "")) = ?',
                ['contorfix']
            )
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }

    public static function applyLiniiConfigurareContoareScope($query)
    {
        return $query
            ->where(function ($query): void {
                $query->whereRaw('lower(trim(tip_calcul)) = ?', ['contor configurabil'])
                    ->orWhereRaw('lower(trim(tip_calcul)) = ?', ['pausal']);
            })
            ->where(function ($query): void {
                $query->whereNull('tip_linie')
                    ->orWhere('tip_linie', '!=', 'header');
            })
            ->where('activ', true);
    }

    public static function applyLiniiContorConfigurabilScope($query)
    {
        return self::applyLiniiConfigurareContoareScope($query);
    }
}
